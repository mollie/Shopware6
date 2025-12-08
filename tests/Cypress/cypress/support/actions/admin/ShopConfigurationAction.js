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
     * @param {ShopConfiguration} shopConfig
     * @param {PluginConfiguration} pluginConfig
     */
    configureEnvironment(shopConfig, pluginConfig) {

        this._configureShop(shopConfig);

        this.configurePlugin(pluginConfig);

        this._clearCache();
    }

    /**
     *
     * @param {PluginConfiguration} pluginConfig
     * @returns {*}
     */
    configurePlugin(pluginConfig) {
        cy.log('Configure Plugin using Shopware API');
        // assign all payment methods to
        // all available sales channels
        cy.wrap(this.apiClient.get('/sales-channel')).then(channels => {

            if (channels === undefined || channels === null) {
                throw new Error('Attention, No Sales Channels found trough Shopware API');
            }

            let systemConfigData = {};

            const mollieConfig = this._buildMollieConfiguration(pluginConfig);

            // assign "all sales channels" to the configuration
            systemConfigData[null] = mollieConfig;

            // Collect all sales channel configuration promises
            const configPromises = channels.map(channel => {
                return this._configureSalesChannel(channel.id);
            });

            // Wait for all sales channel configurations to complete
            return cy.wrap(Promise.all(configPromises)).then(() => {
                // Add mollie config for each channel
                channels.forEach(channel => {
                    systemConfigData[channel.id] = mollieConfig;
                });

                // Save system config and then clear cache
                return cy.wrap(this.apiClient.post('/_action/system-config/batch', systemConfigData));
            });
        });
    }

    /**
     *
     * @param {PluginConfiguration} pluginConfig
     * @private
     */
    _buildMollieConfiguration(pluginConfig) {
        return {
            "MolliePayments.config.testMode": true,
            "MolliePayments.config.debugMode": true,
            // ------------------------------------------------------------------
            "MolliePayments.config.shopwareFailedPayment": !pluginConfig.getMollieFailureMode(),
            "MolliePayments.config.enableCreditCardComponents": pluginConfig.getCreditCardComponents(),
            "MolliePayments.config.enableApplePayDirect": pluginConfig.getApplePayDirectEnabled(),
            "MolliePayments.config.oneClickPaymentsEnabled": false,
            "MolliePayments.config.paymentMethodBankTransferDueDateDays": 2,
            "MolliePayments.config.orderLifetimeDays": 4,
            // ------------------------------------------------------------------
            "MolliePayments.config.orderStateWithAAuthorizedTransaction": 'in_progress',
            "MolliePayments.config.orderStateWithAPaidTransaction": 'completed',
            "MolliePayments.config.orderStateWithAFailedTransaction": 'open',
            "MolliePayments.config.orderStateWithACancelledTransaction": 'cancelled',
            "MolliePayments.config.refundManagerEnabled": true,
            // ------------------------------------------------------------------
            "MolliePayments.config.subscriptionsEnabled": true,
            "MolliePayments.config.subscriptionsShowIndicator": pluginConfig.getSubscriptionIndicator(),
            "MolliePayments.config.subscriptionsAllowAddressEditing": true,
            "MolliePayments.config.subscriptionsAllowPauseResume": true,
            "MolliePayments.config.subscriptionsAllowSkip": true,
            // ---------------------------------------------------------------
            "MolliePayments.config.paypalExpressRestrictions": pluginConfig.getPaypalExpressRestrictions()
        };
    }

    /**
     *
     * @param {ShopConfiguration} shopConfiguration - The shop configuration object
     * @private
     */
    _configureShop(shopConfiguration) {
        cy.log('Configure Shop Environment using Shopware API');
        const data = {};

        const config = {
            "core.loginRegistration.showAccountTypeSelection": true,
            "core.loginRegistration.requireDataProtectionCheckbox": shopConfiguration.getDataPrivacy(),
        };

        data[null] = config;

        return cy.wrap(this.apiClient.post('/_action/system-config/batch', data));
    }

    /**
     *
     * @param id
     * @private
     */
    _configureSalesChannel(id) {
        return this.apiClient.get('/payment-method').then(payments => {

            if (payments === undefined || payments === null) {
                throw new Error('Attention, No payments found through Shopware API');
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

            return this.apiClient.patch('/sales-channel/' + id, data);
        });
    }

    /**
     *
     * @returns {*}
     */
    _clearCache() {
        cy.log('Clear Shopware Cache using Shopware API');
        return cy.wrap(
            this.apiClient.delete('/_action/cache').catch((err) => {
                console.log('Cache could not be cleared')
            })
        );
    }

}
