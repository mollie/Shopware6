// For a detailed explanation regarding each configuration property, visit:
// https://jestjs.io/docs/en/configuration.html

module.exports = {
    'rootDir': '.',
    'verbose': true,
    'moduleFileExtensions': [
        'js'
    ],
    "transform": {
        ".*.js": "<rootDir>/node_modules/babel-jest",
    },
    "transformIgnorePatterns": [
        "node_modules/(?!variables/.*)",
    ],
    "testMatch": [
        "<rootDir>/tests/**/*.spec.js"
    ],
    "collectCoverage": true,
    "collectCoverageFrom": ["<rootDir>/src/**"],
    "coverageDirectory": "<rootDir>/.reports/jest/"
};
