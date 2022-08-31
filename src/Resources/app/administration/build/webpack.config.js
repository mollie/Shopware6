const { resolve, join } = require('path');

module.exports = ({ config }) => {
    // Find the url loader rule
    const urlLoaderRule = config.module.rules.find((rule) => {
        return rule.loader === 'url-loader';
    });

    // Add our svg logos
    urlLoaderRule.exclude.push(
        resolve(join(__dirname, '../src/assets/creditcard_logos')),
    );

    return {
        module: {
            rules: [{
                test: /\.svg$/,
                include: [
                    resolve(join(__dirname, '../src/assets/creditcard_logos')),
                ],
                loader: resolve(join(__dirname, '..', 'node_modules', 'raw-loader')),
            }],
        },
    };
};
