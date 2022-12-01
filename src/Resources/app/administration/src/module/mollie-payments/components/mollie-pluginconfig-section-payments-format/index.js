import template from './mollie-pluginconfig-section-payments-format.html.twig';
import './mollie-pluginconfig-section-payments-format.scss';
import StringUtils from "../../../../core/service/utils/string-utils.service";

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
         * @returns {*}
         */
        getFormat(ordernumber) {
            const configRoot = this.$parent.$parent.$parent.$parent.$parent;

            var text = configRoot.actualConfigData.null['MolliePayments.config.formatOrderNumber'];

            const stringUtils = new StringUtils();
            text = stringUtils.replace('{ordernumber}', ordernumber, text);

            return text;
        },
    },

});
