import MollieCreditCardComponents from './mollie-payments/plugins/creditcard-components.plugin';
import MollieApplePayDirect from './mollie-payments/plugins/express/apple-pay-direct.plugin';
import MollieApplePayPaymentMethod from './mollie-payments/plugins/apple-pay-payment-method.plugin';
import MollieCreditCardMandateManage from './mollie-payments/plugins/creditcard-mandate-manage.plugin';
import MolliePosTerminalPlugin from './mollie-payments/plugins/pos-terminal.plugin';
import PayPalExpressPlugin from './mollie-payments/plugins/express/paypal-express.plugin';
import MolliePhonePlugin from './mollie-payments/plugins/phone-plugin';
import MollieSubscribeButtonPlugin from './mollie-payments/plugins/subscribe-button.plugin';

export default class MollieRegistration {
    /**
     *
     */
    register() {
        const pluginManager = window.PluginManager;

        // [name, pluginClass, selector]
        // Apple Pay Direct and PayPal Express register on 'body' so they initialize
        // exactly once per page. The rest bind to their template markers: hide the
        // standard Apple Pay method in checkout/account, show the credit card
        // components, manage the credit card mandate, drive the POS terminal and run
        // universal phone validation.
        const registrations = [
            ['MollieApplePayDirect', MollieApplePayDirect, 'body'],
            ['PayPalExpressPlugin', PayPalExpressPlugin, 'body'],
            ['MollieApplePayPaymentMethod', MollieApplePayPaymentMethod, '[data-mollie-template-applepay-account]'],
            ['MollieApplePayPaymentMethod', MollieApplePayPaymentMethod, '[data-mollie-template-applepay-checkout]'],
            [
                'MollieCreditCardComponents',
                MollieCreditCardComponents,
                '[data-mollie-template-creditcard-components-sw64]',
            ],
            [
                'MollieCreditCardMandateManage',
                MollieCreditCardMandateManage,
                '[data-mollie-credit-card-mandate-manage]',
            ],
            ['MolliePosTerminal', MolliePosTerminalPlugin, '[data-mollie-template-pos-terminal]'],
            ['MolliePhonePlugin', MolliePhonePlugin, '[data-mollie-phone-validation]'],
            ['MollieSubscribeButton', MollieSubscribeButtonPlugin, '[data-mollie-subscribe-button]'],
        ];

        registrations.forEach(function (registration) {
            pluginManager.register(registration[0], registration[1], registration[2]);
        });

        // Our bundle loads after Shopware's main initializePlugins() call, so our
        // freshly registered plugins still need to be initialized. We do this per
        // plugin instead of calling the global pluginManager.initializePlugins():
        // that global sweep re-initializes EVERY registered plugin on the page,
        // including async plugins from the core or other extensions. On Shopware 6.6.x
        // the init loop has no guard against still-unresolved async plugins, so it
        // runs `new (() => import(...))()` and throws "is not a constructor", aborting
        // the whole storefront bundle. Scoping to our own (synchronous) plugins avoids
        // touching foreign async plugins entirely.
        registrations.forEach(function (registration) {
            pluginManager.initializePlugin(registration[0], registration[2]);
        });
    }
}
