import AdminAPIClient from "Services/shopware/AdminAPIClient";
import Shopware from "Services/shopware/Shopware"

const shopware = new Shopware();

export default class ShopConfigurationAction {

    /**
     *
     */
    constructor() {
        this.apiClient = new AdminAPIClient();
    }


    /**
     *
     * @param mollieFailureMode
     * @param creditCardComponents
     * @param applePayDirect
     */
    setupShop(mollieFailureMode, creditCardComponents, applePayDirect) {

        this._activatePaymentMethods();

        this._configureShop();

        this.prepareShippingMethods();

        this.setupPlugin(mollieFailureMode, creditCardComponents, applePayDirect, false, []);

        this._clearCache();
    }


    /**
     *
     * @param mollieFailureMode
     * @param creditCardComponents
     * @param applePayDirect
     * @param subscriptionIndicator
     * @param paypalExpressRestrictions
     */
    setupPlugin(mollieFailureMode, creditCardComponents, applePayDirect, subscriptionIndicator, paypalExpressRestrictions) {

        // assign all payment methods to
        // all available sales channels
        this.apiClient.get('/sales-channel').then(channels => {

            if (channels === undefined || channels === null) {
                throw new Error('Attention, No Sales Channels found trough Shopware API');
            }

            channels.forEach(channel => {
                this._configureSalesChannel(channel.id);
                this._configureMolliePlugin(channel.id, mollieFailureMode, creditCardComponents, applePayDirect, subscriptionIndicator, paypalExpressRestrictions);
            });
        });
    }

    /**
     *
     * @param voucherValue
     * @param subscriptionEnabled
     * @param subscriptionInterval
     * @param subscriptionIntervalUnit
     */
    updateProducts(voucherValue, subscriptionEnabled, subscriptionInterval, subscriptionIntervalUnit) {

        cy.log('Configuring Shopware Products');

        if (voucherValue === 'eco') {
            voucherValue = '1';
        } else if (voucherValue === 'meal') {
            voucherValue = '2';
        } else if (voucherValue === 'gift') {
            voucherValue = '3';
        } else {
            voucherValue = '0';
        }

        if (subscriptionInterval === '') {
            subscriptionInterval = null;
        }

        let customFields = null;

        if (voucherValue !== '') {
            customFields = {
                'mollie_payments_product_voucher_type': voucherValue,
                'mollie_payments_product_subscription_enabled': subscriptionEnabled,
                'mollie_payments_product_subscription_interval': subscriptionInterval,
                'mollie_payments_product_subscription_interval_unit': subscriptionIntervalUnit,
            }
        }

        cy.intercept({url: '/api/_action/sync'}).as("updateProducts");

        this.apiClient.get('/product').then(products => {

            if (products === undefined || products === null) {
                console.error('Attention, No products found trough Shopware API');
                // send an empty request so that our cy.wait has something, otherwise the full timeout is consumed
                this.apiClient.bulkUpdate('product', []);
                return;
            }

            // lets wait a few seconds
            // otherwise the call is already sent before we
            // even reach our cy.wait for update products.
            const waitStartMS = 10 * 1000;
            setTimeout(() => {

                const maxChunkSize = 80;
                let data = [];

                for (const product of products) {
                    const row = {
                        "id": product.id,
                        "shippingFree": false,
                        "customFields": customFields,
                    };
                    data.push(row);

                    if (data.length >= maxChunkSize) {
                        this.apiClient.bulkUpdate('product', data);
                        data = [];
                    }
                }

                if (data.length >= 0) {
                    this.apiClient.bulkUpdate('product', data);
                }
            }, waitStartMS);

        });

        cy.wait("@updateProducts", {requestTimeout: 100000});

        cy.log('Products done');

        this._clearCache();
    }

    /**
     *
     * @param channelId
     * @param mollieFailureMode
     * @param creditCardComponents
     * @param applePayDirect
     * @param subscriptionIndicator
     * @param paypalExpressRestrictions
     * @private
     */
    _configureMolliePlugin(channelId, mollieFailureMode, creditCardComponents, applePayDirect, subscriptionIndicator, paypalExpressRestrictions) {
        const data = {};

        const config = {
            "MolliePayments.config.testMode": true,
            "MolliePayments.config.debugMode": true,
            // ------------------------------------------------------------------
            "MolliePayments.config.shopwareFailedPayment": !mollieFailureMode,
            "MolliePayments.config.enableCreditCardComponents": creditCardComponents,
            "MolliePayments.config.enableApplePayDirect": applePayDirect,
            "MolliePayments.config.oneClickPaymentsEnabled": false,
            "MolliePayments.config.paymentMethodBankTransferDueDateDays": 2,
            "MolliePayments.config.orderLifetimeDays": 4,
            // ------------------------------------------------------------------
            "MolliePayments.config.orderStateWithAAuthorizedTransaction": 'in_progress',
            "MolliePayments.config.orderStateWithAPaidTransaction": 'completed',
            "MolliePayments.config.orderStateWithAFailedTransaction": 'open',
            "MolliePayments.config.orderStateWithACancelledTransaction": 'cancelled',
            // ------------------------------------------------------------------
            "MolliePayments.config.subscriptionsEnabled": true,
            "MolliePayments.config.subscriptionsShowIndicator": subscriptionIndicator,
            "MolliePayments.config.subscriptionsAllowPauseResume": true,
            "MolliePayments.config.subscriptionsAllowSkip": true,
            // ---------------------------------------------------------------
            "MolliePayments.config.paypalExpressRestrictions": paypalExpressRestrictions
        };

        data[null] = config;        // also add for "All Sales Channels" otherwise things in admin wouldnt work
        data[channelId] = config;

        this.apiClient.post('/_action/system-config/batch', data);
    }

    /**
     *
     * @private
     */
    _activatePaymentMethods() {

        const entity = 'payment_method';
        const interceptAlias = 'updatePaymentMethods';

        this._cypressInterceptBulkUpdate(entity, interceptAlias);

        this.apiClient.get('/payment-method').then(payments => {

            if (payments === undefined || payments === null) {
                console.log('Attention, No payments through trough Shopware API');
                return;
            }

            const data = [];

            for (const element of payments) {

                let shouldBeActive = false;

                // starting from Shopware 6.4.3, there is an indicator
                // if we have the payment method of a mollie plugin.
                // to avoid other payment methods (another paypal), etc., we try to
                // only enable mollie payment methods as good as possible
                if (shopware.isVersionGreaterEqual("6.4.3")) {
                    if (element.attributes.distinguishableName.includes('Mollie')) {
                        shouldBeActive = true;
                    }
                } else {
                    shouldBeActive = true;
                }

                const row = {
                    "id": element.id,
                    "active": shouldBeActive,
                };

                data.push(row);
            }

            if (data.length >= 0) {
                this.apiClient.bulkUpdate(entity, data);
            }
        });

        cy.wait('@' + interceptAlias, {requestTimeout: 50000});
    }

    /**
     * Make sure no availability rules are set
     * that could block our shipping method from being used.
     * Also add some shipping costs for better tests.
     * @private
     */
    prepareShippingMethods() {

        this.apiClient.get('/rule').then(rules => {

            if (rules === undefined || rules === null) {
                rules = [];
            }

            rules.forEach(rule => {

                // get the all customers rule
                // so we allow our shipping methods to be used by everybody
                if (rule.attributes.name === 'All customers') {

                    this.apiClient.get('/shipping-method').then(shippingMethods => {

                        if (shippingMethods === undefined || shippingMethods === null) {
                            return;
                            throw new Error('Attention, No shippingMethods trough Shopware API');
                        }

                        shippingMethods.forEach(element => {

                            this.apiClient.get('/shipping-method/' + element.id + '/prices').then(price => {

                                if (price === undefined) {
                                    return;
                                }

                                const shippingData = {
                                    "id": element.id,
                                    "active": true,
                                    "availabilityRuleId": rule.id,
                                    "prices": [
                                        {
                                            "id": price.id,
                                            "currencyPrice": [
                                                {
                                                    "currencyId": price.attributes.currencyPrice[0].currencyId,
                                                    "net": 4.19,
                                                    "gross": 4.99,
                                                    "linked": false
                                                }
                                            ]
                                        }
                                    ],
                                    "translations": {
                                        "de-DE": {
                                            "trackingUrl": "https://www.carrier.com/de/tracking/%s"
                                        },
                                        "en-GB": {
                                            "trackingUrl": "https://www.carrier.com/en/tracking/%s"
                                        }
                                    }
                                };

                                this.apiClient.patch('/shipping-method/' + element.id, shippingData);
                            });
                        });
                    });
                }
            });
        });
    }

    /**
     *
     * @private
     */
    _configureShop() {
        const data = {};

        const config = {
            "core.loginRegistration.showAccountTypeSelection": true,
        };

        data[null] = config;

        this.apiClient.post('/_action/system-config/batch', data);
    }

    /**
     *
     * @param id
     * @private
     */
    _configureSalesChannel(id) {
        this.apiClient.get('/payment-method').then(payments => {

            if (payments === undefined || payments === null) {
                return;
                throw new Error('Attention, No payments trough Shopware API');
            }

            let paymentMethodsIds = [];

            payments.forEach(element => {
                paymentMethodsIds.push({
                    "id": element.id
                });
            });

            const data = {
                "id": id,
                "paymentMethods": paymentMethodsIds
            };

            this.apiClient.patch('/sales-channel/' + id, data);
        });
    }

    /**
     *
     * @returns {*}
     */
    _clearCache() {
        return this.apiClient.delete('/_action/cache').catch((err) => {
            console.log('Cache could not be cleared')
        });
    }

    /**
     *
     * @param entityName
     * @param alias
     * @private
     */
    _cypressInterceptBulkUpdate(entityName, alias) {
        cy.intercept(
            {
                url: '/api/_action/sync',
                headers: {
                    'x-cypress-entity': entityName,
                },
            }
        ).as(alias);
    }

}
