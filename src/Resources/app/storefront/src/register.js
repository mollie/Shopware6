import MollieCreditCardComponentsSw64 from './mollie-payments/plugins/creditcard-components-sw64.plugin';
import MollieApplePayDirect from './mollie-payments/plugins/express/apple-pay-direct.plugin';
import MollieApplePayPaymentMethod from './mollie-payments/plugins/apple-pay-payment-method.plugin';
import MollieCreditCardMandateManage from './mollie-payments/plugins/creditcard-mandate-manage.plugin';
import MolliePosTerminalPlugin from './mollie-payments/plugins/pos-terminal.plugin';
import PayPalExpressPlugin from './mollie-payments/plugins/express/paypal-express.plugin';
import MolliePhonePlugin from './mollie-payments/plugins/phone-plugin';

export default class MollieRegistration {
    /**
     *
     */
    register() {
        const pluginManager = window.PluginManager;

        // global plugins - registered on 'body' so Shopware's PluginManager initializes
        // them exactly once per page regardless of version
        // -----------------------------------------------------------------------------
        // hide apple pay direct buttons across the whole shop, if not available
        pluginManager.register('MollieApplePayDirect', MollieApplePayDirect, 'body');

        // fix quantity select on PDP Page
        pluginManager.register('PayPalExpressPlugin', PayPalExpressPlugin, 'body');

        // hiding the standard Apple Pay method in the checkout and account area
        // -----------------------------------------------------------------------------
        pluginManager.register(
            'MollieApplePayPaymentMethod',
            MollieApplePayPaymentMethod,
            '[data-mollie-template-applepay-account]',
        );
        pluginManager.register(
            'MollieApplePayPaymentMethod',
            MollieApplePayPaymentMethod,
            '[data-mollie-template-applepay-checkout]',
        );

        // showing credit card components in the checkout
        // -----------------------------------------------------------------------------
        pluginManager.register(
            'MollieCreditCardComponentsSw64',
            MollieCreditCardComponentsSw64,
            '[data-mollie-template-creditcard-components-sw64]',
        );

        // manage credit card mandate
        // -----------------------------------------------------------------------------
        pluginManager.register(
            'MollieCreditCardMandateManage',
            MollieCreditCardMandateManage,
            '[data-mollie-credit-card-mandate-manage]',
        );

        // POS Terminal
        // -----------------------------------------------------------------------------
        pluginManager.register('MolliePosTerminal', MolliePosTerminalPlugin, '[data-mollie-template-pos-terminal]');

        // UNIVERSAL PHONE VALIDATION
        // -----------------------------------------------------------------------------
        pluginManager.register('MolliePhonePlugin', MolliePhonePlugin, '[data-mollie-phone-validation]');

        // Our bundle loads after Shopware's main initializePlugins() call, so we
        // must trigger another initialization round for our newly registered plugins.
        pluginManager.initializePlugins();
    }
}
