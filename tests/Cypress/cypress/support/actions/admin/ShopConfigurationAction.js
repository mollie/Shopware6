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

        this._prepareShippingMethods();

        // assign all payment methods to
        // all available sales channels
        this.apiClient.get('/sales-channel').then(channels => {
            channels.forEach(channel => {
                this._configureSalesChannel(channel.id);
                this._configureMolliePlugin(channel.id, mollieFailureMode, creditCardComponents, applePayDirect);

            });
        });

        // let's just wait a bit
        cy.wait(10000);

        this._clearCache();
    }

    /**
     *
     * @param voucherValue
     */
    updateProducts(voucherValue) {

        if (voucherValue === 'eco') {
            voucherValue = '1';
        } else if (voucherValue === 'meal') {
            voucherValue = '2';
        } else if (voucherValue === 'gift') {
            voucherValue = '3';
        } else {
            voucherValue = '0';
        }

        let customFields = null;

        if (voucherValue !== '') {
            customFields = {
                'mollie_payments': {
                    'voucher_type': voucherValue,
                }
            }
        }


        this.apiClient.get('/product').then(products => {
            products.forEach(product => {
                const data = {
                    "id": product.id,
                    "customFields": customFields,
                };
                this.apiClient.patch('/product/' + product.id, data);
            });
        });

        // let's just wait a bit
        cy.wait(3000);

        this._clearCache();
    }

    /**
     *
     * @param channelId
     * @param mollieFailureMode
     * @param creditCardComponents
     * @param applePayDirect
     * @private
     */
    _configureMolliePlugin(channelId, mollieFailureMode, creditCardComponents, applePayDirect) {
        const data = {};

        data[channelId] = {
            "MolliePayments.config.testMode": true,
            "MolliePayments.config.debugMode": true,
            // ------------------------------------------------------------------
            "MolliePayments.config.shopwareFailedPayment": !mollieFailureMode,
            "MolliePayments.config.enableCreditCardComponents": creditCardComponents,
            "MolliePayments.config.enableApplePayDirect": applePayDirect,
            "MolliePayments.config.paymentMethodBankTransferDueDateDays": 2,
            "MolliePayments.config.orderLifetimeDays": 4,
            // ------------------------------------------------------------------
            "MolliePayments.config.orderStateWithAAuthorizedTransaction": 'in_progress',
            "MolliePayments.config.orderStateWithAPaidTransaction": 'completed',
            "MolliePayments.config.orderStateWithAFailedTransaction": 'open',
            "MolliePayments.config.orderStateWithACancelledTransaction": 'cancelled',
        };

        this.apiClient.post('/_action/system-config/batch', data);
    }

    /**
     *
     * @private
     */
    _activatePaymentMethods() {

        this.apiClient.get('/payment-method').then(payments => {

            payments.forEach(element => {

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

                const data = {
                    "id": element.id,
                    "active": shouldBeActive,
                };

                this.apiClient.patch('/payment-method/' + element.id, data);
            });
        });
    }

    /**
     * Make sure no availability rules are set
     * that could block our shipping method from being used.
     * Also add some shipping costs for better tests.
     * @private
     */
    _prepareShippingMethods() {

        this.apiClient.get('/rule').then(rules => {

            rules.forEach(rule => {

                // get the all customers rule
                // so we allow our shipping methods to be used by everybody
                if (rule.attributes.name === 'All customers') {

                    this.apiClient.get('/shipping-method').then(shippingMethods => {

                        shippingMethods.forEach(element => {

                            this.apiClient.get('/shipping-method/' + element.id + '/prices').then(price => {

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
                                    ]
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
     * @param id
     * @private
     */
    _configureSalesChannel(id) {

        this.apiClient.get('/payment-method').then(payments => {

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

}
