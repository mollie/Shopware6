const path = require('path')
const {resolve, join} = require("path");

module.exports = {
    mode: 'production',
    entry: './src/custom.js',
    output: {
        path: path.resolve(__dirname, '..', '..', 'public', 'static', 'js'),
        filename: 'mollie-payments.js',
    },
    resolve: {
        extensions: ['.js'],
        alias: {
            '@shopware-storefront-sdk': resolve(
                join(__dirname, 'src_vendor', 'shopware-storefront-sdk'),
            ),
        },
    },
}
