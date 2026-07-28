import { readFile, writeFile } from "node:fs/promises";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const root = resolve(dirname(fileURLToPath(import.meta.url)), "..");
const sourceNames = [
    "reference",
    "semantic",
    "typography",
    "layout",
    "motion",
];
const generatedDirectory = resolve(root, "generated");

function flatten(value, prefix = [], output = {}) {
    for (const [key, entry] of Object.entries(value)) {
        const path = [...prefix, key];

        if (
            typeof entry === "object" &&
            entry !== null &&
            Object.hasOwn(entry, "value")
        ) {
            output[path.join("-")] = String(entry.value);
            continue;
        }

        flatten(entry, path, output);
    }

    return output;
}

const tokens = {};
for (const name of sourceNames) {
    const source = JSON.parse(
        await readFile(resolve(root, "tokens", `${name}.tokens.json`), "utf8"),
    );
    Object.assign(tokens, flatten(source));
}

const sortedTokens = Object.fromEntries(
    Object.entries(tokens).sort(([left], [right]) => left.localeCompare(right)),
);
const cssDeclarations = Object.entries(sortedTokens)
    .map(([key, value]) => `    --${key}: ${value};`)
    .join("\n");
const tailwindDeclarations = Object.entries(sortedTokens)
    .filter(([key]) =>
        /^(action|border|focus|status|surface|text)-/.test(key),
    )
    .map(([key]) => `    --color-${key}: var(--${key});`)
    .join("\n");
const phpDeclarations = Object.entries(sortedTokens)
    .map(
        ([key, value]) =>
            `    '${key.replaceAll("'", "\\'")}' => '${value.replaceAll("\\", "\\\\").replaceAll("'", "\\'")}',`,
    )
    .join("\n");

const outputs = {
    "tokens.web.css": `/* Generated. Do not edit. */\n:root {\n${cssDeclarations}\n}\n`,
    "tokens.tailwind.css": `/* Generated. Do not edit. */\n@theme {\n${tailwindDeclarations}\n}\n`,
    "tokens.ts": `// Generated. Do not edit.\nexport const tokens = ${JSON.stringify(sortedTokens, null, 4)} as const;\n`,
    "tokens.email.php": `<?php\n\n// Generated. Do not edit.\nreturn [\n${phpDeclarations}\n];\n`,
    "tokens.print.css": `/* Generated. Do not edit. */\n@media print {\n    :root {\n${cssDeclarations}\n    }\n}\n`,
};

const checkOnly = process.argv.includes("--check");
let drift = false;

for (const [name, content] of Object.entries(outputs)) {
    const path = resolve(generatedDirectory, name);

    if (checkOnly) {
        let existing;
        try {
            existing = await readFile(path, "utf8");
        } catch {
            drift = true;
            continue;
        }
        drift ||= existing !== content;
        continue;
    }

    await writeFile(path, content);
}

if (drift) {
    process.stderr.write("Generated design tokens are stale. Run npm run tokens.\n");
    process.exitCode = 1;
}
