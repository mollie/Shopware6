import template from './mollie-pluginconfig-section-payments-format.html.twig';
import './mollie-pluginconfig-section-payments-format.scss';
import StringUtils from '../../../../core/service/utils/string-utils.service';

// eslint-disable-next-line no-undef
const {Component} = Shopware;

Component.register('mollie-pluginconfig-section-payments-format', {
    template,
    inject:['actualConfigData','currentSalesChannelId'],
    computed: {

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

            const template = this.actualConfigData[this.currentSalesChannelId]['MolliePayments.config.formatOrderNumber'];
            const stringUtils = new StringUtils();

            let text = stringUtils.replace('{ordernumber}', ordernumber, template);

            text = stringUtils.replace('{customernumber}', customerNumber, text);

            return text;
        },
    },

});