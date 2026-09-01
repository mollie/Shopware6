import template from './mollie-pluginconfig-section-payments-format.html.twig';
import './mollie-pluginconfig-section-payments-format.scss';
import OrderNumberFormatService from './services/OrderNumberFormatService';

const { Component } = Shopware;

interface SectionPaymentsFormatComponent {
    formatService: OrderNumberFormatService;

    [key: string]: any;
}

const componentConfig: ThisType<SectionPaymentsFormatComponent> = {
    template,

    inject: ['actualConfigData', 'currentSalesChannelId'],

    data() {
        return {
            formatService: null,
        };
    },

    created() {
        this.formatService = new OrderNumberFormatService();
    },

    computed: {
        sample1() {
            return this.getFormat('1000', '5000');
        },

        sample2() {
            return this.getFormat('5023', '2525');
        },
    },

    methods: {
        getFormat(orderNumber: string, customerNumber: string) {
            const formatTemplate =
                this.actualConfigData?.[this.currentSalesChannelId]?.['MolliePayments.config.formatOrderNumber'] || '';

            return this.formatService.format(formatTemplate, orderNumber, customerNumber);
        },
    },
};

Component.register('mollie-pluginconfig-section-payments-format', componentConfig);
