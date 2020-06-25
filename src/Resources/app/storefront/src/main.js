import 'regenerator-runtime';

// Import all necessary Storefront plugins and scss files
import MollieCreditCardComponents
    from './mollie-payments/plugins/creditcard-components.plugin';

import MollieIDealIssuer
    from './mollie-payments/plugins/ideal-issuer.plugin';

import MollieApplePayPaymentMethod
    from './mollie-payments/plugins/apple-pay-payment-method.plugin';

// Register them via the existing PluginManager
const PluginManager = window.PluginManager;
PluginManager.register('MollieCreditCardComponents', MollieCreditCardComponents);
PluginManager.register('MollieIDealIssuer', MollieIDealIssuer);
PluginManager.register('MollieApplePayPaymentMethod', MollieApplePayPaymentMethod);