import 'regenerator-runtime';

// Import all necessary Storefront plugins and scss files
import MollieCreditCardComponents from './mollie-payments/plugins/creditcard-components.plugin';
import MollieCreditCardComponentsSw64 from './mollie-payments/plugins/creditcard-components-sw64.plugin';
import MollieIDealIssuer from './mollie-payments/plugins/ideal-issuer.plugin';
import MollieApplePayDirect from './mollie-payments/plugins/apple-pay-direct.plugin';
import MollieApplePayPaymentMethod from './mollie-payments/plugins/apple-pay-payment-method.plugin';


// Register them via the existing PluginManager
const PluginManager = window.PluginManager;


// global plugins
// -----------------------------------------------------------------------------
// hide apple pay direct buttons across the whole shop, if not available
PluginManager.register('MollieApplePayDirect', MollieApplePayDirect);
// this is just the iDEAL dropdown..not quite sure why its not bound to the DOM -> TODO?
PluginManager.register('MollieIDealIssuer', MollieIDealIssuer);


// hiding the standard apple pay method in the checkout and account area
// -----------------------------------------------------------------------------
PluginManager.register('MollieApplePayPaymentMethod', MollieApplePayPaymentMethod, '[data-mollie-template-applepay-account]');
PluginManager.register('MollieApplePayPaymentMethod', MollieApplePayPaymentMethod, '[data-mollie-template-applepay-checkout]');


// showing credit card components in the checkout
// we have 2 versions for < Shopware 6.4 and >= Shopware 6.4
// -----------------------------------------------------------------------------
PluginManager.register('MollieCreditCardComponents', MollieCreditCardComponents, '[data-mollie-template-creditcard-components]');
PluginManager.register('MollieCreditCardComponentsSw64', MollieCreditCardComponentsSw64, '[data-mollie-template-creditcard-components-sw64]');
