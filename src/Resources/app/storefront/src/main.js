import MollieRegistration from "./register";

// this file will register all plugins according to shopware standard
// we use our custom webpack so that it's available for Shopware 6.4 and 6.5.
// if it's however necessary to use the built-in all.js, then this would
// also work, as long as its built in the Shopware version that uses it.

if (window.mollie_javascript_use_shopware !== undefined && window.mollie_javascript_use_shopware === '1') {

    const molliePlugins = new MollieRegistration();

    molliePlugins.register();
}
