import MolliePaymentsConfigService from '../core/service/api/mollie-payments-config.service';
import MolliePaymentsOrderService from '../core/service/api/mollie-payments-order.service';
import MolliePaymentsPaymentMethodService from '../core/service/api/mollie-payments-payment-method.service';
import MolliePaymentsRefundService from '../core/service/api/mollie-payments-refund.service';
import MolliePaymentsShippingService from '../core/service/api/mollie-payments-shipping.service';
import MolliePaymentsSupportService from '../core/service/api/mollie-payments-support.service';
import MolliePaymentsSubscriptionService from '../core/service/api/mollie-subscription.service';

import '../module/mollie-payments/rules/mollie-lineitem-subscription-rule';
import '../module/mollie-payments/rules/mollie-cart-subscription-rule';


// eslint-disable-next-line no-undef
const {Application} = Shopware;

Application.addServiceProvider('MolliePaymentsConfigService', (container) => {
    const initContainer = Application.getContainer('init');

    // get the current locale for our config requests
    // this is e.g. de-DE, en-GB, ...
    const currentLocale = Application.getContainer('factory').locale.getLastKnownLocale();

    return new MolliePaymentsConfigService(initContainer.httpClient, container.loginService, currentLocale);
});

Application.addServiceProvider('MolliePaymentsOrderService', (container) => {
    const initContainer = Application.getContainer('init');

    return new MolliePaymentsOrderService(initContainer.httpClient, container.loginService);
});

Application.addServiceProvider('MolliePaymentsPaymentMethodService', (container) => {
    const initContainer = Application.getContainer('init');

    return new MolliePaymentsPaymentMethodService(initContainer.httpClient, container.loginService);
});

Application.addServiceProvider('MolliePaymentsRefundService', (container) => {
    const initContainer = Application.getContainer('init');

    return new MolliePaymentsRefundService(initContainer.httpClient, container.loginService);
});

Application.addServiceProvider('MolliePaymentsShippingService', (container) => {
    const initContainer = Application.getContainer('init');

    return new MolliePaymentsShippingService(initContainer.httpClient, container.loginService);
});

Application.addServiceProvider('MolliePaymentsSupportService', (container) => {
    const initContainer = Application.getContainer('init');

    return new MolliePaymentsSupportService(initContainer.httpClient, container.loginService);
});

Application.addServiceProvider('MolliePaymentsSubscriptionService', (container) => {
    const initContainer = Application.getContainer('init');

    return new MolliePaymentsSubscriptionService(initContainer.httpClient, container.loginService);
});


Application.addServiceProviderDecorator('ruleConditionDataProviderService', (ruleConditionService) => {
    ruleConditionService.addCondition('mollie_lineitem_subscription_rule', {
        component: 'mollie-lineitem-subscription-rule',
        label: 'mollie-payments.rules.itemSubscriptionRule',
        scopes: ['lineitem'],
        group: 'item',
    });

    ruleConditionService.addCondition('mollie_cart_subscription_rule', {
        component: 'mollie-cart-subscription-rule',
        label: 'mollie-payments.rules.cartSubscriptionRule',
        scopes: ['cart'],
        group: 'cart',
    });

    return ruleConditionService;
});
