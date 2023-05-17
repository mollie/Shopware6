import 'regenerator-runtime';

import MollieCreditCardComponents from './mollie-payments/plugins/creditcard-components.plugin';
import MollieCreditCardComponentsSw64 from './mollie-payments/plugins/creditcard-components-sw64.plugin';
import MollieIDealIssuer from './mollie-payments/plugins/ideal-issuer.plugin';
import MollieApplePayDirect from './mollie-payments/plugins/apple-pay-direct.plugin';
import MollieApplePayPaymentMethod from './mollie-payments/plugins/apple-pay-payment-method.plugin';
import MollieCreditCardMandateManage from './mollie-payments/plugins/creditcard-mandate-manage.plugin';


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
        // this is just the iDEAL dropdown..not quite sure why its not bound to the DOM -> TODO?
        pluginManager.register('MollieIDealIssuer', MollieIDealIssuer);


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
    }

}

