import 'regenerator-runtime';

// Import all necessary Storefront plugins and scss files
import MollieCreditCardComponents from './mollie-payments/plugins/creditcard-components.plugin';
import MollieCreditCardComponentsSw64 from './mollie-payments/plugins/creditcard-components-sw64.plugin';
import MollieIDealIssuer from './mollie-payments/plugins/ideal-issuer.plugin';
import MollieApplePayDirect from './mollie-payments/plugins/apple-pay-direct.plugin';
import MollieApplePayPaymentMethod from './mollie-payments/plugins/apple-pay-payment-method.plugin';


// Register them via the existing PluginManager
const PluginManager = window.PluginManager;
PluginManager.register('MollieIDealIssuer', MollieIDealIssuer);
PluginManager.register('MollieApplePayDirect', MollieApplePayDirect);
PluginManager.register('MollieApplePayPaymentMethod', MollieApplePayPaymentMethod, '#mollie_hide_applepay');

// < Sw 6.4 Version
PluginManager.register('MollieCreditCardComponents', MollieCreditCardComponents, '#mollie_components_credit_card');

// >= Sw 6.4 Version
PluginManager.register('MollieCreditCardComponentsSw64', MollieCreditCardComponentsSw64, '#mollie_components_credit_card_sw64');
