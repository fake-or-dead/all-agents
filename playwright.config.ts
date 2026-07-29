import { defineConfig, devices } from "@playwright/test";

export default defineConfig({
    testDir: "tests/Browser",
    fullyParallel: true,
    // Browser journeys intentionally exercise shared PostgreSQL and Redis
    // state, including one client-address rate-limit bucket.
    workers: 1,
    forbidOnly: Boolean(process.env.CI),
    retries: process.env.CI ? 2 : 0,
    reporter: process.env.CI
        ? [["github"], ["html", { open: "never" }]]
        : "list",
    use: {
        baseURL: process.env.PLAYWRIGHT_BASE_URL ?? "http://127.0.0.1:8080",
        locale: "th-TH",
        trace: "retain-on-failure",
    },
    projects: [
        {
            name: "chromium",
            use: { ...devices["Desktop Chrome"] },
        },
    ],
});
