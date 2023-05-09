const {resolve, join} = require("path");

module.exports = () => {
    return {
        resolve: {
            alias: {
                '@shopware-storefront-sdk': resolve(
                    join(__dirname, '..', 'node_modules_local', 'shopware-storefront-sdk'),
                ),
            },
        },
    };
};