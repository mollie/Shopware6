const path = require('path')

module.exports = {
    mode: 'production',
    entry: './src/custom.js',
    output: {
        path: path.resolve(__dirname, '..', '..', 'public'),
        filename: 'mollie-payments.js',
    },
}
