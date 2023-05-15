const {resolve, join} = require("path");

module.exports = () => {
    return {
        resolve: {
            alias: {
                '@shopware-storefront-sdk': resolve(
                    join(__dirname, '..', 'src_vendor', 'shopware-storefront-sdk'),
                ),
            },
        },
    };
};