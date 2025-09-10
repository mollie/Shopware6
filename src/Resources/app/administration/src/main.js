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
//import './init/credit-card-components.init';
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
import plPL from './snippet/pl-PL';
import csCZ from './snippet/cs-CZ';
import slSL from './snippet/sl-SL';
import huHU from './snippet/hu-HU';
import fiFI from './snippet/fi-FI';
import daDK from './snippet/da-DK';
import elGR from './snippet/el-GR';
import hrHR from './snippet/hr-HR';
import etEE from './snippet/et-EE';
import isIS from './snippet/is-IS';
import ltLT from './snippet/lt-LT';

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
// eslint-disable-next-line no-undef
Shopware.Locale.extend('pl-PL', plPL);
// eslint-disable-next-line no-undef
Shopware.Locale.extend('cs-CZ', csCZ);
// eslint-disable-next-line no-undef
Shopware.Locale.extend('sl-SL', slSL);
// eslint-disable-next-line no-undef
Shopware.Locale.extend('hu-HU', huHU);
// eslint-disable-next-line no-undef
Shopware.Locale.extend('fi-FI', fiFI);
// eslint-disable-next-line no-undef
Shopware.Locale.extend('da-DK', daDK);
// eslint-disable-next-line no-undef
Shopware.Locale.extend('el-GR', elGR);
// eslint-disable-next-line no-undef
Shopware.Locale.extend('hr-HR', hrHR);
// eslint-disable-next-line no-undef
Shopware.Locale.extend('et-EE', etEE);
// eslint-disable-next-line no-undef
Shopware.Locale.extend('is-IS', isIS);
// eslint-disable-next-line no-undef
Shopware.Locale.extend('lt-LT', ltLT);
