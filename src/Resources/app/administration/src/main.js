import 'regenerator-runtime/runtime';
import 'core-js/stable';
import './module/mollie-payments/components/mollie-pluginconfig-section-info';
import './module/mollie-payments/components/mollie-pluginconfig-section-api';
import './module/mollie-payments/components/mollie-pluginconfig-section-payments';
import './module/mollie-payments/components/mollie-pluginconfig-section-payments-format';
import './module/mollie-payments/components/mollie-pluginconfig-section-rounding';
import './module/mollie-payments/components/mollie-pluginconfig-support-modal';
import './module/mollie-payments/components/mollie-pluginconfig-section-order-lifetime-warning';
import './module/mollie-payments/components/mollie-pluginconfig-element-orderstate-select';
import './init/api-service.init';
import './init/credit-card-components.init';
import './extension/sw-flow-sequence-action';
import './extension/structure/sw-search-bar-item';
import './component/flow-sequence/action-order-ship-modal';
import './component/flow-sequence/action-order-refund-modal';
import './component/credit-card-logo';
import './module/mollie-payments';
import './module/sw-product';
import './module/mollie-payments/acl';
import './module/mollie-payments/extension/sw-order';
import './module/mollie-payments/extension/sw-settings';
import './module/mollie-payments/components/mollie-tracking-info';
import './module/mollie-payments/components/mollie-refund-manager';
import './module/mollie-payments/components/mollie-ship-order';
import './module/mollie-payments/components/mollie-cancel-item';

import deDE from './snippet/de-DE';
import enGB from './snippet/en-GB';
import nlNL from './snippet/nl-NL';
import itIT from './snippet/it-IT';
import ptPT from './snippet/pt-PT';
import esES from './snippet/es-ES';
import svSE from './snippet/sv-SE';
import nbNO from './snippet/nb-NO';

// eslint-disable-next-line no-undef
Shopware.Locale.extend('de-DE', deDE);
// eslint-disable-next-line no-undef
Shopware.Locale.extend('en-GB', enGB);
// eslint-disable-next-line no-undef
Shopware.Locale.extend('nl-NL', nlNL);
// eslint-disable-next-line no-undef
Shopware.Locale.extend('it-IT', itIT);
// eslint-disable-next-line no-undef
Shopware.Locale.extend('pt-PT', ptPT);
// eslint-disable-next-line no-undef
Shopware.Locale.extend('es-ES', esES);
// eslint-disable-next-line no-undef
Shopware.Locale.extend('sv-SE', svSE);
// eslint-disable-next-line no-undef
Shopware.Locale.extend('nb-NO', nbNO);
