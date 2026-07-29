import assert from "node:assert/strict";
import { spawn } from "node:child_process";
import { createServer } from "node:http";
import { test } from "node:test";
import { setTimeout as delay } from "node:timers/promises";

const smokeEnvironment = {
    SMOKE_CONNECT_TIMEOUT_SECONDS: "1",
    SMOKE_REQUEST_TIMEOUT_SECONDS: "1",
    SMOKE_RETRY_INTERVAL_SECONDS: "1",
};

const listen = (server, port = 0) =>
    new Promise((resolve, reject) => {
        server.once("error", reject);
        server.listen(port, "127.0.0.1", () => {
            server.off("error", reject);
            resolve();
        });
    });

const close = (server) =>
    new Promise((resolve, reject) => {
        if (!server.listening) {
            resolve();
            return;
        }

        server.close((error) => {
            if (error) {
                reject(error);
                return;
            }

            resolve();
        });
    });

const reservePort = async () => {
    const server = createServer();
    await listen(server);
    const address = server.address();
    assert.notEqual(address, null);
    assert.equal(typeof address, "object");
    const port = address.port;
    await close(server);

    return port;
};

const runSmoke = (baseUrl, environment) =>
    new Promise((resolve, reject) => {
        const child = spawn("bin/smoke", [baseUrl], {
            cwd: process.cwd(),
            env: {
                ...process.env,
                ...smokeEnvironment,
                ...environment,
            },
        });
        let stdout = "";
        let stderr = "";

        child.stdout.setEncoding("utf8");
        child.stderr.setEncoding("utf8");
        child.stdout.on("data", (chunk) => {
            stdout += chunk;
        });
        child.stderr.on("data", (chunk) => {
            stderr += chunk;
        });
        child.once("error", reject);
        child.once("close", (code, signal) => {
            resolve({ code, signal, stderr, stdout });
        });
    });

test(
    "bounded smoke retries delayed web and readiness until they succeed",
    { timeout: 10_000 },
    async (context) => {
        const port = await reservePort();
        const startedAt = Date.now();
        let readinessAttempts = 0;
        const smoke = runSmoke(`http://127.0.0.1:${port}`, {
            SMOKE_OVERALL_TIMEOUT_SECONDS: "6",
        });

        await delay(400);
        const server = createServer((request, response) => {
            response.setHeader("content-type", "application/json");

            if (request.url === "/health/live") {
                response.end('{"status":"ok"}');
                return;
            }

            if (request.url === "/health/ready") {
                readinessAttempts += 1;

                if (Date.now() - startedAt < 1_800) {
                    response.writeHead(503);
                    response.end('{"status":"degraded"}');
                    return;
                }

                response.end('{"status":"ready"}');
                return;
            }

            if (request.url === "/") {
                response.setHeader("content-type", "text/html");
                response.end('<!doctype html><html lang="th"></html>');
                return;
            }

            if (request.url === "/recover/caddy-smoke-non-secret") {
                response.setHeader("referrer-policy", "no-referrer");
                response.setHeader("cache-control", "no-store");
                response.writeHead(404);
                response.end('{"status":"missing"}');
                return;
            }

            response.writeHead(404);
            response.end('{"status":"missing"}');
        });
        await listen(server, port);
        context.after(() => close(server));

        const result = await smoke;
        const elapsed = Date.now() - startedAt;

        assert.equal(result.signal, null);
        assert.equal(result.code, 0, result.stderr);
        assert.match(result.stdout, /Smoke checks passed/);
        assert.match(
            result.stdout,
            /Smoke check passed: recovery-token headers/,
        );
        assert.ok(readinessAttempts >= 2, result.stdout);
        assert.ok(
            elapsed >= 1_800,
            `smoke returned too early after ${elapsed}ms`,
        );
        assert.ok(elapsed < 6_000, `smoke exceeded its deadline: ${elapsed}ms`);
    },
);

test(
    "bounded smoke fails an unavailable target with connection diagnostics",
    { timeout: 6_000 },
    async () => {
        const port = await reservePort();
        const startedAt = Date.now();
        const result = await runSmoke(`http://127.0.0.1:${port}`, {
            SMOKE_OVERALL_TIMEOUT_SECONDS: "2",
        });
        const elapsed = Date.now() - startedAt;

        assert.notEqual(result.code, 0);
        assert.match(result.stderr, /timed out after 2s: liveness/);
        assert.match(result.stderr, /curl exit 7/);
        assert.ok(
            elapsed < 4_000,
            `unavailable target exceeded bound: ${elapsed}ms`,
        );
    },
);

test(
    "bounded smoke fails permanently degraded readiness within its deadline",
    { timeout: 7_000 },
    async (context) => {
        const server = createServer((request, response) => {
            response.setHeader("content-type", "application/json");

            if (request.url === "/health/live") {
                response.end('{"status":"ok"}');
                return;
            }

            if (request.url === "/health/ready") {
                response.writeHead(503);
                response.end('{"status":"degraded"}');
                return;
            }

            response.setHeader("content-type", "text/html");
            response.end('<!doctype html><html lang="th"></html>');
        });
        await listen(server);
        context.after(() => close(server));
        const address = server.address();
        assert.notEqual(address, null);
        assert.equal(typeof address, "object");

        const startedAt = Date.now();
        const result = await runSmoke(`http://127.0.0.1:${address.port}`, {
            SMOKE_OVERALL_TIMEOUT_SECONDS: "3",
        });
        const elapsed = Date.now() - startedAt;

        assert.notEqual(result.code, 0);
        assert.match(result.stdout, /Smoke check passed: liveness/);
        assert.match(result.stderr, /timed out after 3s: readiness/);
        assert.match(result.stderr, /HTTP 503/);
        assert.ok(
            elapsed < 5_000,
            `never-ready target exceeded bound: ${elapsed}ms`,
        );
    },
);
