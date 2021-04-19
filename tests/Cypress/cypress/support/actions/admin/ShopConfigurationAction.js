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
     * @returns {Promise<*>}
     */
    setupShop() {

        this._activatePaymentMethods();

        this.apiClient.get('/sales-channel').then(channels => {
            channels.forEach(channel => {
                this._configureSalesChannel(channel.id);
            });
        });
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
