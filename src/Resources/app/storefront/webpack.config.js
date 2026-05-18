const { resolve, join } = require('path');

module.exports = {
    mode: 'production',
    entry: './src/custom.js',
    output: {
        path: resolve(__dirname, '..', '..', 'public'),
        filename: 'mollie-payments.js',
    },
    resolve: {
        extensions: ['.js'],
    },
};
