import MollieCreditCardComponents from './mollie-payments/plugins/creditcard-components.plugin';
import MollieCreditCardComponentsSw64 from './mollie-payments/plugins/creditcard-components-sw64.plugin';
import MollieApplePayDirect from './mollie-payments/plugins/apple-pay-direct.plugin';
import MollieApplePayPaymentMethod from './mollie-payments/plugins/apple-pay-payment-method.plugin';
import MollieCreditCardMandateManage from './mollie-payments/plugins/creditcard-mandate-manage.plugin';
import MolliePosTerminalPlugin from './mollie-payments/plugins/pos-terminal.plugin';
import MollieBancomatPlugin from './mollie-payments/plugins/bancomat-plugin';


export default class MolliRegistration {

    /**
     *
     */
    register() {

        const pluginManager = window.PluginManager;

        // global plugins
        // -----------------------------------------------------------------------------
        // hide apple pay direct buttons across the whole shop, if not available
        pluginManager.register('MollieApplePayDirect', MollieApplePayDirect);


        // hiding the standard Apple Pay method in the checkout and account area
        // -----------------------------------------------------------------------------
        pluginManager.register('MollieApplePayPaymentMethod', MollieApplePayPaymentMethod, '[data-mollie-template-applepay-account]');
        pluginManager.register('MollieApplePayPaymentMethod', MollieApplePayPaymentMethod, '[data-mollie-template-applepay-checkout]');


        // showing credit card components in the checkout
        // we have 2 versions for < Shopware 6.4 and >= Shopware 6.4
        // -----------------------------------------------------------------------------
        pluginManager.register('MollieCreditCardComponents', MollieCreditCardComponents, '[data-mollie-template-creditcard-components]');
        pluginManager.register('MollieCreditCardComponentsSw64', MollieCreditCardComponentsSw64, '[data-mollie-template-creditcard-components-sw64]');

        // manage credit card mandate
        // -----------------------------------------------------------------------------
        pluginManager.register('MollieCreditCardMandateManage', MollieCreditCardMandateManage, '[data-mollie-credit-card-mandate-manage]');

        // POS Terminal
        // -----------------------------------------------------------------------------
        pluginManager.register('MolliePosTerminal', MolliePosTerminalPlugin, '[data-mollie-template-pos-terminal]');

        pluginManager.register('MollieBancomatPlugin',MollieBancomatPlugin);
    }

}

