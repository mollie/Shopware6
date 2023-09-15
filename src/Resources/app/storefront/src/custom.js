import MollieRegistration from './register';

// this file is used as a custom webpack-built JS resource that
// works in all Shopware versions 6.4 and 6.5
// it's consumed in a custom twig base file.
// if this is not wanted, it can be turned off, then the main.js needs to be
// manually built inside the Shopware that wants to use it.

/**
 * ATTENTION:
 * if you want to override our Shopware plugins, please use the following syntax:
 * ==========================================================================================
 *
 * const pluginManager = window.PluginManager;
 *
 * window.addEventListener('mollieLoaded', () => {
 *     pluginManager.override('MollieCreditCardComponentsSw64', CustomCreditCardPlugin, '[data-mollie-template-creditcard-components-sw64]');
 * })
 *
 */
window.addEventListener('load', function () {

    if (window.mollie_javascript_use_shopware !== undefined && window.mollie_javascript_use_shopware !== '1') {

        const molliePlugins = new MollieRegistration();
        molliePlugins.register();

        // fire our loaded events, so that plugin developers can still override our plugins
        // -----------------------------------------------------------------------------
        window.dispatchEvent(new Event('mollieLoaded'));

        // this is required so that our,plugins are existing
        // -----------------------------------------------------------------------------
        window.PluginManager.initializePlugins();
    }
})


