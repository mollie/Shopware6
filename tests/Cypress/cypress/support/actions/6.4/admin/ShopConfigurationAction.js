import AdminAPIClient from "Services/6.4/shopware/AdminAPIClient";


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
     */
    setupShop(mollieFailureMode) {

        // activate all payment methods
        this._activatePaymentMethods();

        // assign all payment methods to
        // all available sales channels
        this.apiClient.get('/sales-channel').then(channels => {
            channels.forEach(channel => {
                this._configureSalesChannel(channel.id);
            });
        });

        // configure mollie plugin
        this._configureMolliePlugin(mollieFailureMode);
    }

    /**
     *
     * @param mollieFailureMode
     * @private
     */
    _configureMolliePlugin(mollieFailureMode) {
        const data = {
            "null": {
                "MolliePayments.config.testMode": true,
                "MolliePayments.config.shopwareFailedPayment": !mollieFailureMode
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
