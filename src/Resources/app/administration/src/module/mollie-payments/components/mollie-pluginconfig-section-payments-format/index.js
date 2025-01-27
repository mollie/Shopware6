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

            const templateElement =  document.querySelector('input[name="MolliePayments.config.formatOrderNumber"]');
            let template = ''
            if(templateElement instanceof HTMLInputElement){
                template = templateElement.value;
            }
            const stringUtils = new StringUtils();

            let text = stringUtils.replace('{ordernumber}', ordernumber, template);

            text = stringUtils.replace('{customernumber}', customerNumber, text);

            return text;
        },
    },

});