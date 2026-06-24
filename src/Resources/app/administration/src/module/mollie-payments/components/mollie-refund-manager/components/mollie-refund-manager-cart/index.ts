import template from './mollie-refund-manager-cart.html.twig';
import ShopwareOrderGrid from '../../grids/ShopwareOrderGrid';
import RefundItemService from '../../services/RefundItemService';

const { Component, Filter } = Shopware;

interface RefundManagerCartComponent {
    itemService: RefundItemService;

    [key: string]: any;
}

const componentConfig: ThisType<RefundManagerCartComponent> = {
    template,

    props: {
        order: {
            type: Object,
            required: true,
        },
        orderItems: {
            type: Array,
            required: true,
        },
        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
        roundingDiff: {
            type: Number,
            required: false,
            default: 0,
        },
        tutorialPartialQuantityVisible: {
            type: Boolean,
            required: false,
            default: false,
        },
        tutorialPartialPromotionsVisible: {
            type: Boolean,
            required: false,
            default: false,
        },
        tutorialRefundShipping: {
            type: Boolean,
            required: false,
            default: false,
        },
        tutorialResetStock: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            itemService: null,
        };
    },

    computed: {
        currencyFilter() {
            return Filter.getByName('currency');
        },

        cardTitle() {
            const title = this.$tc('mollie-payments.refund-manager.cart.title');
            return title.replace('##orderNumber##', this.order.orderNumber);
        },

        columns() {
            return new ShopwareOrderGrid().buildColumns();
        },
    },

    created() {
        this.itemService = new RefundItemService();
    },

    methods: {
        formatCurrency(value: number) {
            return this.currencyFilter(value, this.order.currency.isoCode, this.order.totalRounding.decimals);
        },

        isItemPromotion(item: any) {
            return this.itemService.isTypePromotion(item);
        },

        isItemDelivery(item: any) {
            return this.itemService.isTypeDelivery(item);
        },

        isItemDiscounted(item: any) {
            return this.itemService.isDiscounted(item);
        },

        isItemRefundable(item: any) {
            return this.itemService.isRefundable(item);
        },

        isTaxStatusGross() {
            return this.order.taxStatus === 'gross';
        },
    },
};

Component.register('mollie-refund-manager-cart', componentConfig);
