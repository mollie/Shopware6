const path = require('path')
const {resolve, join} = require("path");

module.exports = {
    entry: './src/custom.js',
    output: {
        path: path.resolve(__dirname, '..', '..', 'public', 'js'),
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
