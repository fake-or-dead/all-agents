import { readdir, stat } from "node:fs/promises";
import { extname, resolve } from "node:path";

const assetDirectory = resolve("public/build/assets");
const budgets = {
    ".css": 100 * 1024,
    ".js": 400 * 1024,
};
const totals = {
    ".css": 0,
    ".js": 0,
};

for (const name of await readdir(assetDirectory)) {
    const extension = extname(name);

    if (!(extension in totals)) {
        continue;
    }

    totals[extension] += (await stat(resolve(assetDirectory, name))).size;
}

const failures = Object.entries(budgets).filter(
    ([extension, budget]) => totals[extension] > budget,
);

if (failures.length > 0) {
    for (const [extension, budget] of failures) {
        process.stderr.write(
            `${extension} bundle is ${totals[extension]} bytes; budget is ${budget} bytes.\n`,
        );
    }

    process.exitCode = 1;
} else {
    process.stdout.write(
        `Bundle budgets passed: JS ${totals[".js"]} bytes; CSS ${totals[".css"]} bytes.\n`,
    );
}
