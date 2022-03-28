import './init/api-service.init';
import './extension/sw-flow-sequence-action';
import './component/flow-sequence/action-order-ship-modal';
import './component/flow-sequence/action-order-refund-modal';
import './module/mollie-payments';
import './module/sw-product';


import deDE from './snippet/de-DE';
import enGB from './snippet/en-GB';
import nlNL from './snippet/nl-NL';

// eslint-disable-next-line no-undef
Shopware.Locale.extend('de-DE', deDE);
// eslint-disable-next-line no-undef
Shopware.Locale.extend('en-GB', enGB);
// eslint-disable-next-line no-undef
Shopware.Locale.extend('nl-NL', nlNL);
