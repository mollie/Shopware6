import template from './mollie-pluginconfig-section-payments-format.html.twig';
import './mollie-pluginconfig-section-payments-format.scss';
import StringUtils from '../../../../core/service/utils/string-utils.service';

// eslint-disable-next-line no-undef
const {Component} = Shopware;

Component.register('mollie-pluginconfig-section-payments-format', {
    template,

    computed: {

        /**
         *
         * @returns {boolean}
         */
        isVisible() {
            // we know show this all the time for a better UX
            return true;
        },

        /**
         *
         * @returns {string}
         */
        sample1() {
            return this.getFormat('1000', '5000');
        },

        /**
         *
         * @returns {string}
         */
        sample2() {
            return this.getFormat('5023', '2525');
        },

    },

    methods: {

        /**
         *
         * @param ordernumber
         * @param customerNumber
         * @returns {*}
         */
        getFormat(ordernumber, customerNumber) {

            const template = this.getConfigData('MolliePayments.config.formatOrderNumber');

            const stringUtils = new StringUtils();

            let text = stringUtils.replace('{ordernumber}', ordernumber, template);

            text = stringUtils.replace('{customernumber}', customerNumber, text);

            return text;
        },

        /**
         *
         * @returns {*}
         */
        getConfigData(key) {
            // Shopware > 6.4.7.0
            const configRootNew = this.$parent.$parent.$parent.$parent.$parent;
            // Shopware <= 6.4.7.0
            const configRootOld = this.$parent.$parent.$parent.$parent;

            var value = null;

            if (configRootNew !== null) {
                var scId = null;
                if (configRootNew.currentSalesChannelId !== undefined) {
                    scId = configRootNew.currentSalesChannelId;
                }

                if (configRootNew.actualConfigData !== undefined) {

                    // try to grab it from our current sales channel, if existing
                    if (scId !== null) {
                        value = configRootNew.actualConfigData[scId][key];
                    }

                    // if we are in AllSalesChannel, or didnt find a value,
                    // then use the inherited one from our parent (condition => value is NULL)
                    if (value === null) {
                        value = configRootNew.actualConfigData.null[key];
                    }
                }
            }

            if (configRootOld !== null && configRootOld.actualConfigData !== undefined) {
                return configRootOld.actualConfigData.null[key];
            }

            // does not exist e.g. in Shopware 6.3.5.2
            return value;
        },

    },

})
;
