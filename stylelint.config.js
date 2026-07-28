export default {
    extends: ["stylelint-config-standard"],
    ignoreFiles: ["public/build/**", "resources/design/generated/**"],
    rules: {
        "at-rule-no-unknown": [
            true,
            {
                ignoreAtRules: ["source", "theme"],
            },
        ],
        "import-notation": null,
        "no-descending-specificity": null,
    },
};
