import MolliePaymentsConfigService from '../core/service/api/mollie-payments-config.service';

import MolliePaymentsOrderService from '../core/service/api/mollie-payments-order.service';

import MolliePaymentsRefundService from '../core/service/api/mollie-payments-refund.service';

import MolliePaymentsShippingService from '../core/service/api/mollie-payments-shipping.service';

// eslint-disable-next-line no-undef
const { Application } = Shopware;

Application.addServiceProvider('MolliePaymentsConfigService', (container) => {
    const initContainer = Application.getContainer('init');

    return new MolliePaymentsConfigService(initContainer.httpClient, container.loginService);
});

Application.addServiceProvider('MolliePaymentsOrderService', (container) => {
    const initContainer = Application.getContainer('init');

    return new MolliePaymentsOrderService(initContainer.httpClient, container.loginService);
});

Application.addServiceProvider('MolliePaymentsRefundService', (container) => {
    const initContainer = Application.getContainer('init');

    return new MolliePaymentsRefundService(initContainer.httpClient, container.loginService);
});

Application.addServiceProvider('MolliePaymentsShippingService', (container) => {
    const initContainer = Application.getContainer('init');

    return new MolliePaymentsShippingService(initContainer.httpClient, container.loginService);
});
