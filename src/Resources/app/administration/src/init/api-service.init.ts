import MolliePaymentsConfigService from '../core/service/api/mollie-payments-config.service';
import MolliePaymentsOrderService from '../core/service/api/mollie-payments-order.service';
import MollieOrderDetailsService from '../core/service/api/mollie-order-details.service';
import MolliePaymentsPaymentMethodService from '../core/service/api/mollie-payments-payment-method.service';
import MolliePaymentsRefundService from '../core/service/api/mollie-payments-refund.service';
import MolliePaymentsShippingService from '../core/service/api/mollie-payments-shipping.service';
import MolliePaymentsSupportService from '../core/service/api/mollie-payments-support.service';
import MolliePaymentsSubscriptionService from '../core/service/api/mollie-subscription.service';
import MolliePaymentsItemCancelService from '../core/service/api/mollie-payments-item-cancel.service';

import '../module/mollie-payments/rules/mollie-lineitem-subscription-rule';
import '../module/mollie-payments/rules/mollie-cart-subscription-rule';

const { Application } = Shopware;

/**
 * Registers an ApiService that only needs the http client and the login service
 * (the common case). Services with extra constructor args are registered inline.
 */
function registerHttpService(name: string, ServiceClass: any): void {
    Application.addServiceProvider(name, (container: any) => {
        const initContainer = Application.getContainer('init');

        return new ServiceClass(initContainer.httpClient, container.loginService);
    });
}

Application.addServiceProvider('MolliePaymentsConfigService', (container: any) => {
    const initContainer = Application.getContainer('init');

    // get the current locale for our config requests, e.g. de-DE, en-GB, ...
    const currentLocale = Application.getContainer('factory').locale.getLastKnownLocale();

    return new MolliePaymentsConfigService(initContainer.httpClient, container.loginService, currentLocale);
});

registerHttpService('MolliePaymentsOrderService', MolliePaymentsOrderService);
registerHttpService('MollieOrderDetailsService', MollieOrderDetailsService);
registerHttpService('MolliePaymentsPaymentMethodService', MolliePaymentsPaymentMethodService);
registerHttpService('MolliePaymentsRefundService', MolliePaymentsRefundService);
registerHttpService('MolliePaymentsShippingService', MolliePaymentsShippingService);
registerHttpService('MolliePaymentsSupportService', MolliePaymentsSupportService);
registerHttpService('MolliePaymentsSubscriptionService', MolliePaymentsSubscriptionService);
registerHttpService('MolliePaymentsItemCancelService', MolliePaymentsItemCancelService);

Application.addServiceProviderDecorator('ruleConditionDataProviderService', (ruleConditionService: any) => {
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

Application.addServiceProviderDecorator('searchTypeService', (searchTypeService: any) => {
    searchTypeService.upsertType('mollie_subscription', {
        entityName: 'mollie_subscription',
        placeholderSnippet: 'mollie-payments.searchPlaceholder',
        listingRoute: 'mollie.payments.subscriptions',
    });

    return searchTypeService;
});
