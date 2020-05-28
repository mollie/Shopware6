// Import all necessary Storefront plugins and scss files
import MollieCreditCardComponents
    from './mollie-payments/plugins/creditcard-components.plugin';

import MollieIDealIssuer
    from "./mollie-payments/plugins/ideal-issuer.plugin";

// Register them via the existing PluginManager
const PluginManager = window.PluginManager;
PluginManager.register('MollieCreditCardComponents', MollieCreditCardComponents);
PluginManager.register('MollieIDealIssuer', MollieIDealIssuer);