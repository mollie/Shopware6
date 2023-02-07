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
            return (this.getFormat('10000') !== '');
        },

        /**
         *
         * @returns {string}
         */
        sample1() {
            return this.getFormat('1000');
        },

        /**
         *
         * @returns {string}
         */
        sample2() {
            return this.getFormat('5023');
        },

    },

    methods: {

        /**
         *
         * @param ordernumber
         * @returns {string|*}
         */
        getFormat(ordernumber) {

            const actualConfigData = this.getConfigData();

            if (actualConfigData === undefined || actualConfigData === null) {
                return '';
            }

            var text = actualConfigData.null['MolliePayments.config.formatOrderNumber'];

            const stringUtils = new StringUtils();
            text = stringUtils.replace('{ordernumber}', ordernumber, text);

            return text;
        },

        /**
         *
         * @returns {*}
         */
        getConfigData() {
            // Shopware > 6.4.7.0
            const configRootNew = this.$parent.$parent.$parent.$parent.$parent;
            // Shopware <= 6.4.7.0
            const configRootOld = this.$parent.$parent.$parent.$parent;

            if (configRootNew !== null && configRootNew.actualConfigData !== undefined) {
                return configRootNew.actualConfigData;
            }

            if (configRootOld !== null && configRootOld.actualConfigData !== undefined) {
                return configRootOld.actualConfigData;
            }

            // does not exist e.g. in Shopware 6.3.5.2
            return null;
        },

    },

});
