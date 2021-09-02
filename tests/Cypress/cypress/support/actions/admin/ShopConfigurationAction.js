import AdminAPIClient from "Services/shopware/AdminAPIClient";


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
     */
    setupShop(mollieFailureMode, creditCardComponents) {

        this._activatePaymentMethods();

        this._activateShippingMethods();
      
        // assign all payment methods to
        // all available sales channels
        this.apiClient.get('/sales-channel').then(channels => {
            channels.forEach(channel => {
                this._configureSalesChannel(channel.id);
            });
        });

        // configure mollie plugin
        this._configureMolliePlugin(mollieFailureMode, creditCardComponents);
    }

    /**
     *
     * @param mollieFailureMode
     * @param creditCardComponents
     * @private
     */
    _configureMolliePlugin(mollieFailureMode, creditCardComponents) {
        const data = {
            "null": {
                "MolliePayments.config.testMode": true,
                "MolliePayments.config.debugMode": true,
                // ------------------------------------------------------------------
                "MolliePayments.config.shopwareFailedPayment": !mollieFailureMode,
                "MolliePayments.config.enableCreditCardComponents": creditCardComponents,
                "MolliePayments.config.enableApplePayDirect": true,
                "MolliePayments.config.paymentMethodBankTransferDueDateDays": 2,
                "MolliePayments.config.orderLifetimeDays": 4,
                // ------------------------------------------------------------------
                "MolliePayments.config.orderStateWithAAuthorizedTransaction": 'in_progress',
                "MolliePayments.config.orderStateWithAPaidTransaction": 'completed',
                "MolliePayments.config.orderStateWithAFailedTransaction": 'open',
                "MolliePayments.config.orderStateWithACancelledTransaction": 'cancelled',
            }
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

                const data = {
                    "id": element.id,
                    "active": true,
                };

                this.apiClient.patch('/payment-method/' + element.id, data);
            });
        });
    }

    /**
     * Make sure no availability rules are set
     * that could block our shipping method from being used.
     * @private
     */
    _activateShippingMethods() {

        this.apiClient.get('/rule').then(rules => {

            rules.forEach(rule => {

                // get the all customers rule
                // so we allow our shipping methods to be used by everybody
                if (rule.attributes.name === 'All customers') {

                    this.apiClient.get('/shipping-method').then(payments => {

                        payments.forEach(element => {

                            const data = {
                                "id": element.id,
                                "active": true,
                                "availabilityRuleId": rule.id,
                            };

                            this.apiClient.patch('/shipping-method/' + element.id, data);
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
