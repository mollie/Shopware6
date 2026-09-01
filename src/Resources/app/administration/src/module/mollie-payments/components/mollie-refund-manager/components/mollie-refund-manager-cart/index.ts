import template from './mollie-refund-manager-cart.html.twig';
import ShopwareOrderGrid from '../../grids/ShopwareOrderGrid';
import RefundItemService from '../../services/RefundItemService';
import RefundCalculator from '../../services/RefundCalculator';

const { Component, Filter } = Shopware;

interface RefundManagerCartComponent {
    itemService: RefundItemService;
    calculator: RefundCalculator;

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
        isOrderFullyRefunded: {
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
            calculator: null,
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
        this.calculator = new RefundCalculator();
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
            if (this.isOrderFullyRefunded) {
                return false;
            }

            if (this.calculator.isItemFullyRefunded(item, this.isTaxStatusGross())) {
                return false;
            }

            return this.itemService.isRefundable(item);
        },

        getItemRemainingRefundable(item: any) {
            return this.calculator.getItemRemainingRefundable(item, this.isTaxStatusGross());
        },

        isTaxStatusGross() {
            return this.order.price.taxStatus === 'gross';
        },
    },
};

Component.register('mollie-refund-manager-cart', componentConfig);
