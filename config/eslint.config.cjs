const js = require('@eslint/js');
const tseslint = require('typescript-eslint');
const prettier = require('eslint-config-prettier');

// Flat config (ESLint 9/10). Written as .cjs so `require` honours the Makefile's
// NODE_PATH=dev/node_modules (the project installs JS dev deps under dev/, not
// next to this config) — an .mjs/import config would not resolve them.
module.exports = tseslint.config(
    {
        ignores: ['**/.tmp/**', '**/node_modules/**', '**/dist/**', '**/build/**'],
    },
    {
        files: ['**/*.{js,mjs,ts}'],
        extends: [js.configs.recommended, ...tseslint.configs.recommended, prettier],
        languageOptions: {
            globals: {
                Shopware: 'readonly',
            },
        },
        rules: {
            'no-console': ['error', { allow: ['warn', 'error'] }],
            'no-debugger': 'error',
            'prefer-const': 'warn',
            // TypeScript already checks for undefined identifiers.
            'no-undef': 'off',
            // The plugin intentionally types Shopware globals/DAL entities as `any`.
            '@typescript-eslint/no-explicit-any': 'off',
            '@typescript-eslint/no-unused-vars': [
                'warn',
                { argsIgnorePattern: '^_', varsIgnorePattern: '^_' },
            ],
        },
    },
);
