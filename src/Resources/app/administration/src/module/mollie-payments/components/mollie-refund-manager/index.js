import template from './mollie-refund-manager.html.twig';
import './mollie-refund-manager.scss';
import MollieRefundItemBuilder from './services/MollieRefundItemBuilder';
import OrderRefundGridBuilder from "./services/OrderRefundGridBuilder";
import MollieRefundsGridBuilder from "./services/MollieRefundsGridBuilder";

// eslint-disable-next-line no-undef
const {Component, Mixin} = Shopware;

Component.register('mollie-refund-manager', {
    template,

    mixins: [
        Mixin.getByName('notification'),
    ],

    inject: [
        'MolliePaymentsRefundService',
    ],

    props: {
        order: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            isRefundDataLoading: false,
            // -------------------------------
            checkRefund: false,
            refundDescription: '',
            // -------------------------------
            refundLineItems: [],
            existingRefunds: [],
            // -------------------------------
            tutorialFullRefundVisible: false,
            tutorialPartialAmountRefundVisible: false,
            tutorialPartialQuantityVisible: false,
            tutorialPartialPromotionsVisible: false,
            tutorialResetStock: false,
            tutorialRefundShipping: false,
            // -------------------------------
            remainingAmount: 0,
            refundAmount: 0,
            refundedAmount: 0,
            voucherAmount: 0,
            pendingRefunds: 0,
        };
    },


    created() {
        this.createdComponent();
    },


    computed: {

        /**
         *
         * @returns {string}
         */
        orderCardTitle() {
            return 'Order ' + this.order.orderNumber;
        },

        /**
         *
         * @returns {[{property: string, label: string, align: string},{property: string, label: string, align: string},{property: string, width: string, label: string, align: string},{property: string, width: string, label: string, align: string},{property: string, width: string, label: string, align: string},null,null,null,null,null]}
         */
        getLineItemColumns() {
            const builder = new OrderRefundGridBuilder();
            return builder.buildColumns();
        },

        /**
         *
         * @returns {[{property: string, label: string, align: string},{property: string, label: string, align: string},{property: string, width: string, label: string, align: string},{property: string, width: string, label: string, align: string},{property: string, width: string, label: string, align: string},null,null,null,null,null]}
         */
        getRefundListColumns() {
            const builder = new MollieRefundsGridBuilder();
            return builder.buildColumns();
        },

    },

    methods: {

        /**
         *
         */
        createdComponent() {
            this.remainingAmount = 0;

            if (this.order) {
                this.fetchMollieData();
            }
        },

        /**
         *
         */
        fetchMollieData() {

            this.isRefundDataLoading = true;

            this.MolliePaymentsRefundService.list({orderId: this.order.id})
                .then((response) => {
                    this.remainingAmount = response.totals.remaining;
                    this.refundedAmount = response.totals.refunded;
                    this.voucherAmount = response.totals.voucherAmount;
                    this.pendingRefunds = response.totals.pendingRefunds;
                    this.existingRefunds = response.refunds;
                    this.refundLineItems = response.cart;

                    this.isRefundDataLoading = false;

                })
                .catch((response) => {
                    this.createNotificationError({
                        message: response.message,
                    });
                    this.isRefundDataLoading = false;
                });
        },

        /**
         *
         * @param item
         */
        onItemQtyChanged(item) {

            const maxQty = item.shopware.quantity - item.refunded;
            if (item.refundQuantity > maxQty) {
                item.refundQuantity = maxQty;
            }

            // do only update if our
            // amount has not yet been adjusted
            if (item.refundMode === 'amount') {
                this.calculateRefundAmount();
                return;
            }

            if (item.refundQuantity === 0) {
                return;
            }

            item.refundMode = 'quantity';
            //  item.refundAmount = (item.shopware.unitPrice * item.refundQuantity) - item.shopware.promotion.discount;

            this.onItemPromotionChanged(item);
            //  item.refundAmount = (item.shopware.unitPrice * item.refundQuantity);

            this.calculateRefundAmount();
        },

        /**
         *
         * @param item
         */
        onItemPromotionChanged(item) {

            if (item.refundMode === 'amount') {
                // only in quantity or NONE mode
                return;
            }

            if (item.refundQuantity === 0) {
                return;
            }

            if (item.refundPromotion) {
                const discountPerQty = item.shopware.promotion.discount / item.shopware.promotion.quantity;
                item.refundAmount = (item.shopware.unitPrice * item.refundQuantity) - (item.refundQuantity * discountPerQty);
            } else {
                item.refundAmount = (item.shopware.unitPrice * item.refundQuantity);
            }

            this.calculateRefundAmount();
        },

        /**
         *
         * @param item
         */
        onItemAmountChanged(item) {

            if (item.refundMode === 'quantity') {
                this.calculateRefundAmount();
                return;
            }

            item.refundMode = 'amount';

            if (item.refundQuantity <= 0) {
                item.refundQuantity = parseInt(item.refundAmount / item.shopware.unitPrice);
            }

            this.calculateRefundAmount();
        },

        /**
         *
         * @param item
         */
        onItemReset(item) {
            item.refundMode = 'none';
            item.refundQuantity = 0;
            item.refundAmount = 0;
            item.resetStock = 0;
            item.refundPromotion = false;

            this.calculateRefundAmount();
        },

        selectAllQty() {
            const me = this;
            this.refundLineItems.forEach(function (item) {
                item.refundQuantity = item.shopware.quantity - item.refunded;
                me.onItemQtyChanged(item);
            });

            this.calculateRefundAmount();
        },

        /**
         *
         */
        resetCartItemsForm() {
            const me = this;
            this.refundLineItems.forEach(function (item) {
                me.onItemReset(item);
            });

            this.checkRefund = false;

            this.calculateRefundAmount();
        },

        /**
         *
         * @param item
         * @returns {*}
         */
        isRefundCancelable(item) {
            return item.isPending || item.isQueued;
        },

        /**
         *
         * @param status
         * @returns {*}
         */
        getStatus(status) {
            return this.$tc('mollie-payments.modals.refund.list.status.' + status);
        },

        isItemPromotion(item) {
            return item.shopware.isPromotion;
        },

        isItemDelivery(item) {
            return item.shopware.isDelivery;
        },

        isItemPromotionDiscounted(item) {
            return item.shopware.promotion.discount > 0;
        },

        /**
         *
         * @param status
         * @returns {string}
         */
        getStatusBadge(status) {
            if (status === 'refunded') {
                return 'success';
            }
            return 'warning';
        },

        /**
         *
         * @param status
         * @returns {*}
         */
        getStatusDescription(status) {
            return this.$tc('mollie-payments.modals.refund.list.status-description.' + status);
        },

        /**
         *
         */
        calculateRefundAmount() {
            var totalRefundAmount = 0;

            this.refundLineItems.forEach(function (lineItem) {
                totalRefundAmount += parseFloat(lineItem.refundAmount);
            });

            this.refundAmount = this.roundToTwo(totalRefundAmount);
        },

        /**
         *
         */
        onRefundAll() {
            this.MolliePaymentsRefundService.refundAll(
                {
                    orderId: this.order.id,
                    amount: this.remainingAmount,
                })
                .then((response) => {
                    if (response.success) {

                        this.createNotificationSuccess({
                            message: this.$tc('mollie-payments.modals.refund.success'),
                        });

                        // fetch new data
                        this.fetchMollieData();

                        // reset existing values
                        this.resetCartItemsForm();

                        this.$emit('refund-success');

                    } else {
                        this.createNotificationError({
                            message: this.$tc('mollie-payments.modals.refund.error'),
                        });
                    }
                })
                .catch((response) => {
                    this.createNotificationError({
                        message: response.message,
                    });
                });
        },

        isItemRefundable(item) {
            if (item.refunded >= item.shopware.quantity) {
                return false;
            }

            return true;
        },

        /**
         *
         */
        onStartRefund() {

            if (this.refundAmount <= 0.0) {
                this.createNotificationWarning({
                    message: this.$tc('mollie-payments.modals.refund.warning.low-amount'),
                });

                return;
            }

            var itemData = [];

            this.refundLineItems.forEach(function (item) {

                const data = {
                    'id': item.shopware.id,
                    'label': item.shopware.label,
                    'quantity': item.refundQuantity,
                    'amount': item.refundAmount,
                    'resetStock': item.resetStock,
                };

                itemData.push(data);
            });


            this.MolliePaymentsRefundService.refund(
                {
                    orderId: this.order.id,
                    amount: this.refundAmount,
                    description: this.refundDescription,
                    items: itemData,
                })
                .then((response) => {
                    if (response.success) {

                        this.createNotificationSuccess({
                            message: this.$tc('mollie-payments.modals.refund.success'),
                        });

                        // fetch new data
                        this.fetchMollieData();

                        // reset existing values
                        this.resetCartItemsForm();

                        this.$emit('refund-success');

                    } else {
                        this.createNotificationError({
                            message: this.$tc('mollie-payments.modals.refund.error'),
                        });
                    }
                })
                .catch((response) => {
                    this.createNotificationError({
                        message: response.message,
                    });
                });
        },

        /**
         *
         * @param item
         */
        cancelRefund(item) {

            this.MolliePaymentsRefundService.cancel(
                {
                    orderId: this.order.id,
                    refundId: item.id,
                })
                .then((response) => {
                    if (response.success) {
                        this.createNotificationSuccess({
                            message: this.$tc('mollie-payments.modals.refund.cancel.success'),
                        });
                    } else {
                        this.createNotificationError({
                            message: this.$tc('mollie-payments.modals.refund.cancel.error'),
                        });
                    }
                })
                .then(() => {
                    this.$emit('refund-cancelled');
                    this.fetchMollieData();
                })
                .catch((response) => {
                    this.createNotificationError({
                        message: response.message,
                    });
                });
        },

        setFullAmount() {
            this.refundAmount = this.remainingAmount;
        },

        isFixButtonAvailable() {

            const diff = Math.abs(this.refundAmount - this.remainingAmount);
            console.log(diff);
            // show if 5 cents or less diff
            return diff > 0 && diff <= 0.05;
        },


        /**
         *
         * @param num
         * @returns {number}
         */
        roundToTwo(num) {
            return +(Math.round(num + "e+2") + "e-2");
        },


        toggleTutorialFull() {
            this.tutorialFullRefundVisible = !this.tutorialFullRefundVisible;
        },

        toggleTutorialPartialAmount() {
            this.tutorialPartialAmountRefundVisible = !this.tutorialPartialAmountRefundVisible;
        },

        toggleTutorialPartialQuantities() {
            this.tutorialPartialQuantityVisible = !this.tutorialPartialQuantityVisible;
        },

        toggleTutorialPartialPromotions() {
            this.tutorialPartialPromotionsVisible = !this.tutorialPartialPromotionsVisible;
        },

        toggleTutorialStock() {
            this.tutorialResetStock = !this.tutorialResetStock;
        },

        toggleTutorialShipping() {
            this.tutorialRefundShipping = !this.tutorialRefundShipping;
        },

        resetTutorials() {
            this.tutorialFullRefundVisible = false;
            this.tutorialPartialAmountRefundVisible = false;
            this.tutorialPartialQuantityVisible = false;
            this.tutorialPartialPromotionsVisible = false;

            this.tutorialResetStock = false;
            this.tutorialRefundShipping = false;
        },

    },
});
