import deDE from './snippet/de-DE';
import enGB from './snippet/en-GB';
import nlNL from './snippet/nl-NL';
// eslint-disable-next-line no-undef
Shopware.Locale.extend('de-DE', deDE);
// eslint-disable-next-line no-undef
Shopware.Locale.extend('en-GB', enGB);
// eslint-disable-next-line no-undef
Shopware.Locale.extend('nl-NL', nlNL);

import './init/api-service.init';
import './module/mollie-payments';
import './module/sw-product';
import './module/mollie-subscriptions';
