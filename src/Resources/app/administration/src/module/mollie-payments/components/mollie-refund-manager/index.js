import template from './mollie-refund-manager.html.twig';
import './mollie-refund-manager.scss';
import ShopwareOrderGrid from './grids/ShopwareOrderGrid';
import MollieRefundsGrid from './grids/MollieRefundsGrid';
import RefundItemService from './services/RefundItemService';

// eslint-disable-next-line no-undef
const { Component, Mixin, Filter } = Shopware;

Component.register('mollie-refund-manager', {
    template,

    mixins: [Mixin.getByName('notification')],

    inject: ['MolliePaymentsRefundService', 'acl'],

    props: {
        order: {
            type: Object,
            required: true,
        },
        refundManagerConfig: {
            type: Object,
            required: false,
            default: function () {
                return { verifyRefund: true, autoStockReset: true, showInstructions: true };
            },
        },
    },

    data() {
        return {
            // -------------------------------
            // services
            itemService: null,
            // -------------------------------
            // basic view stuff
            isRefundDataLoading: false,
            isRefunding: false,
            // -------------------------------
            // grids
            orderItems: [],
            mollieRefunds: [],
            // -------------------------------
            // calculator
            remainingAmount: 0,
            refundAmount: 0,
            refundedAmount: 0,
            voucherAmount: 0,
            pendingRefunds: 0,
            checkVerifyRefund: false,
            refundDescription: '',
            refundInternalDescription: '',
            roundingDiff: 0,
            // -------------------------------
            // tutorials
            tutorialFullRefundVisible: false,
            tutorialPartialAmountRefundVisible: false,
            tutorialPartialQuantityVisible: false,
            tutorialPartialPromotionsVisible: false,
            tutorialResetStock: false,
            tutorialRefundShipping: false,
        };
    },

    created() {
        this.createdComponent();
    },

    computed: {
        configVerifyRefund() {
            return this.refundManagerConfig?.verifyRefund ?? true;
        },

        configAutoStockReset() {
            return this.refundManagerConfig?.autoStockReset ?? true;
        },

        configShowInstructions() {
            return this.refundManagerConfig?.showInstructions ?? true;
        },

        titleCardOrder() {
            let text = this.$tc('mollie-payments.refund-manager.cart.title');
            text = text.replace('##orderNumber##', this.order.orderNumber);
            return text;
        },

        /**
         * Gets a list of columns for the
         * grid of the current order and its line items
         * @returns {[{property: string, label: string, align: string},{property: string, label: string, align: string},{property: string, width: string, label: string, align: string},{property: string, width: string, label: string, align: string},{property: string, width: string, label: string, align: string},null,null,null,null,null]}
         */
        gridCartColumns() {
            const grid = new ShopwareOrderGrid();
            return grid.buildColumns();
        },

        /**
         * Gets a list of columns for the
         * grid of currently existing refunds from the Mollie Dashboard
         * @returns {[{property: string, label: string, align: string},{property: string, label: string, align: string},{property: string, width: string, label: string, align: string},{property: string, width: string, label: string, align: string},{property: string, width: string, label: string, align: string},null,null,null,null,null]}
         */
        gridMollieRefundsColumns() {
            const grid = new MollieRefundsGrid();
            return grid.buildColumns();
        },

        // includes pending refunds, since those amounts cannot be refunded again
        isOrderFullyRefunded() {
            return this.remainingAmount <= 0;
        },

        /**
         *
         * @returns {*}
         */
        isAclRefundAllowed() {
            return this.acl.can('mollie_refund_manager:create');
        },

        /**
         *
         * @returns {*}
         */
        isAclCancelAllowed() {
            return this.acl.can('mollie_refund_manager:delete');
        },

        /**
         * Return the title with a count
         * @returns {*}
         */
        descriptionCharacterCountingTitle() {
            return this.$tc('mollie-payments.refund-manager.summary.lblDescription', 0, {
                characters: this.refundDescription.length,
            });
        },

        currencyFilter() {
            return Filter.getByName('currency');
        },

        dateFilter() {
            return Filter.getByName('date');
        },
    },

    methods: {
        /**
         *
         */
        createdComponent() {
            this.itemService = new RefundItemService();

            if (this.order) {
                this._fetchFormData();
            }
        },

        // ---------------------------------------------------------------------------------------------------------
        // <editor-fold desc="ORDER FORM">
        // ---------------------------------------------------------------------------------------------------------

        /**
         * Gets if that provided item is a promotion line item in Shopware.
         * @param item
         * @returns {boolean}
         */
        isItemPromotion(item) {
            return this.itemService.isTypePromotion(item);
        },

        /**
         * Gets if the provided item is a delivery/shipping item in Shopware.
         * @param item
         * @returns {boolean}
         */
        isItemDelivery(item) {
            return this.itemService.isTypeDelivery(item);
        },

        /**
         * Gets if the provided item is discounted by a promotion.
         * @param item
         * @returns {boolean}
         */
        isItemDiscounted(item) {
            return this.itemService.isDiscounted(item);
        },

        /**
         * Gets if the provided item can still be refunded.
         * @param item
         * @returns {boolean}
         */
        isItemRefundable(item) {
            if (this.isOrderFullyRefunded) {
                return false;
            }

            if (item.refunded > 0 && item.refunded >= item.shopware.quantity) {
                return false;
            }

            return this.itemService.isRefundable(item);
        },

        /**
         * Gets if the order tax status is gross
         */
        isTaxStatusGross() {
            return this.order.price.taxStatus === 'gross';
        },

        /**
         * This automatically selects all items by
         * assigning their maximum quantity to be refunded.
         * We iterate through all items and just mark them
         * to be fully refunded.
         */
        btnSelectAllItems_Click() {
            const me = this;
            this.orderItems.forEach(function (item) {
                me.itemService.setFullRefund(item);
            });
            this._calculateFinalAmount();
        },

        /**
         * Clicking this button will reset all line items
         * to its original values.
         */
        btnResetCartForm_Click() {
            const me = this;
            this.orderItems.forEach(function (item) {
                me.itemService.resetRefundData(item);
            });
            this._calculateFinalAmount();

            // also make sure to uncheck our
            // verification checkbox and clean our text
            this.checkVerifyRefund = false;
            this.refundDescription = '';
            this.refundInternalDescription = '';
        },

        /**
         * This will be executed as soon as the user
         * changes the quantity of an item in the cart grid.
         * @param item
         */
        onItemQtyChanged(item) {
            this.itemService.onQuantityChanged(item);

            // verify if we also need to
            // set the stock automatically
            if (this.configAutoStockReset) {
                this.itemService.setStockReset(item, item.refundQuantity);
            }

            this._calculateFinalAmount();
        },

        /**
         * This will be executed when the user changes
         * the amount text field of a certain cart item
         * @param item
         */
        onItemAmountChanged(item) {
            this.itemService.onAmountChanged(item);
            this._calculateFinalAmount();
        },

        /**
         * This will be executed if the user changes the
         * configuration to either activate or deactivate the
         * Tax Refund in case of Net Orders.
         * @param item
         */
        onItemRefundTaxChanged(item) {
            this.itemService.onRefundTaxChanged(item);
            this._calculateFinalAmount();
        },

        /**
         * This will be executed if the user changes the
         * configuration to either allow or forbid the deduction
         * of a promotion, in case of discounted items.
         * @param item
         */
        onItemPromotionDeductionChanged(item) {
            this.itemService.onPromotionDeductionChanged(item);
            this._calculateFinalAmount();
        },

        /**
         * This will be used, if the user decides to reset a
         * specific line item to its original values
         * @param item
         */
        btnResetLine_Click(item) {
            this.itemService.resetRefundData(item);
            this._calculateFinalAmount();
        },

        // ---------------------------------------------------------------------------------------------------------
        // </editor-fold>
        // ---------------------------------------------------------------------------------------------------------

        // ---------------------------------------------------------------------------------------------------------
        // <editor-fold desc="INSTRUCTIONS">
        // ---------------------------------------------------------------------------------------------------------

        /**
         *
         */
        btnToggleTutorialFull_Click() {
            this.tutorialFullRefundVisible = !this.tutorialFullRefundVisible;
        },

        /**
         *
         */
        btnToggleTutorialPartialAmount_Click() {
            this.tutorialPartialAmountRefundVisible = !this.tutorialPartialAmountRefundVisible;
        },

        /**
         *
         */
        btnToggleTutorialPartialQuantities_Click() {
            this.tutorialPartialQuantityVisible = !this.tutorialPartialQuantityVisible;
        },

        /**
         *
         */
        btnToggleTutorialPartialPromotions_Click() {
            this.tutorialPartialPromotionsVisible = !this.tutorialPartialPromotionsVisible;
        },

        /**
         *
         */
        btnToggleTutorialStock_Click() {
            this.tutorialResetStock = !this.tutorialResetStock;
        },

        /**
         *
         */
        btnToggleTutorialShipping_Click() {
            this.tutorialRefundShipping = !this.tutorialRefundShipping;
        },

        /**
         *
         */
        btnResetTutorials_Click() {
            this.tutorialFullRefundVisible = false;
            this.tutorialPartialAmountRefundVisible = false;
            this.tutorialPartialQuantityVisible = false;
            this.tutorialPartialPromotionsVisible = false;

            this.tutorialResetStock = false;
            this.tutorialRefundShipping = false;
        },

        // ---------------------------------------------------------------------------------------------------------
        // </editor-fold>
        // ---------------------------------------------------------------------------------------------------------

        // ---------------------------------------------------------------------------------------------------------
        // <editor-fold desc="SUMMARY">
        // ---------------------------------------------------------------------------------------------------------

        /**
         * Gets if the button to fix the refund amount
         * is available or not. This should only be available
         * if the amount of the refund and the remaining amount is
         * almost the same but not exactly due to rounding issues.
         * @returns {boolean}
         */
        isButtonFixDiffAvailable() {
            const diff = Math.abs(this.refundAmount - this.remainingAmount);

            // show if 5 cents or less diff
            return diff > 0 && diff <= 0.07;
        },

        /**
         * This click handler makes sure to use the
         * full remaining amount for the refund field.
         * This is an easy "top up" feature in case of
         * rounding issues.
         */
        btnFixDiff_Click() {
            this.refundAmount = this.remainingAmount;
        },

        /**
         * This click handler starts a partial refund
         * with everything that has been set up in our cart form.
         */
        btnRefund_Click() {
            if (!this.isAclRefundAllowed) {
                return;
            }

            if (this.refundAmount <= 0.0) {
                this._showNotificationWarning(
                    this.$tc('mollie-payments.refund-manager.notifications.error.low-amount'),
                );
                return;
            }

            var itemData = [];

            this.orderItems.forEach(function (item) {
                const data = {
                    id: item.shopware.id,
                    label: item.shopware.label,
                    quantity: item.refundQuantity,
                    amount: item.refundAmount,
                    resetStock: item.resetStock,
                };

                itemData.push(data);
            });

            this.isRefunding = true;
            this.MolliePaymentsRefundService.refund({
                orderId: this.order.id,
                amount: this.refundAmount,
                description: this.refundDescription,
                internalDescription: this.refundInternalDescription,
                items: itemData,
            })
                .then((response) => {
                    if (this._isRefundSuccess(response)) {
                        this._handleRefundSuccess(response);
                    } else {
                        this._showNotificationError(response.errors?.[0]);
                    }
                })
                .finally(() => {
                    this.isRefunding = false;
                });
        },

        /**
         * This click handler starts a full refund for the whole order.
         * This will in most cases not consider any special line item setups
         * but only do a full refund and stock reset.
         */
        btnRefundFull_Click() {
            if (!this.isAclRefundAllowed) {
                return;
            }

            this.isRefunding = false;
            this.MolliePaymentsRefundService.refundAll({
                orderId: this.order.id,
                description: this.refundDescription,
                internalDescription: this.refundInternalDescription,
            })
                .then((response) => {
                    if (this._isRefundSuccess(response)) {
                        this._handleRefundSuccess(response);
                    } else {
                        this._showNotificationError(response.errors?.[0]);
                    }
                })
                .finally(() => {
                    this.isRefunding = false;
                });
        },

        // ---------------------------------------------------------------------------------------------------------
        // </editor-fold>
        // ---------------------------------------------------------------------------------------------------------

        // ---------------------------------------------------------------------------------------------------------
        // <editor-fold desc="REFUND GRID">
        // ---------------------------------------------------------------------------------------------------------

        /**
         * Gets the provided status key translated into
         * a snippet of Shopware.
         * @param statusKey
         * @returns {string}
         */
        getRefundStatusName(statusKey) {
            return this.$tc('mollie-payments.refunds.status.' + statusKey);
        },

        /**
         * Gets the translated description of the provided status key.
         * @param statusKey
         * @returns {*}
         */
        getRefundStatusDescription(statusKey) {
            return this.$tc('mollie-payments.refunds.status.description.' + statusKey);
        },

        getRefundCompositions(item) {
            if (!item || !item.metadata || !item.metadata.composition || item.metadata.composition.length <= 0) {
                return [this.$tc('mollie-payments.refund-manager.refunds.grid.lblNoComposition')];
            }

            const me = this;
            const result = [];

            item.metadata.composition.forEach(function (entry) {
                let label = entry.label;
                if (entry.swReference.length > 0) {
                    label = entry.swReference;
                }

                // we also allow line-item specific refunds with qty 0
                // in this case, we should not display it to avoid mathematical confusion
                if (entry.quantity > 0) {
                    result.push(
                        label + ' (' + entry.quantity + ' x ' + entry.amount + ' ' + me.order.currency.symbol + ')',
                    );
                } else {
                    result.push(label + ' (' + entry.amount + ' ' + me.order.currency.symbol + ')');
                }
            });

            return result;
        },

        /**
         * Gets the status badge color (button variant) depending
         * on the provided status key.
         * @param statusKey
         * @returns {string}
         */
        getRefundStatusBadge(statusKey) {
            if (statusKey === 'refunded') {
                return 'success';
            }
            return 'warning';
        },

        /**
         * Gets if the provided refund can be cancelled or not.
         * @param item
         * @returns {*}
         */
        isRefundCancelable(item) {
            return item.isPending || item.isQueued;
        },

        /**
         *
         * @param item
         */
        btnCancelRefund_Click(item) {
            if (!this.isAclCancelAllowed) {
                return;
            }

            this.MolliePaymentsRefundService.cancel({
                orderId: this.order.id,
                refundId: item.id,
            })
                .then((response) => {
                    if (response.success) {
                        this._showNotificationSuccess(
                            this.$tc('mollie-payments.refund-manager.notifications.success.refund-canceled'),
                        );
                        this.$emit('refund-cancelled');
                        this.mollieRefunds = this.mollieRefunds.map(function (r) {
                            if (r.id !== item.id) {
                                return r;
                            }
                            return Object.assign({}, r, { status: 'canceled', isPending: false, isQueued: false });
                        });

                        const totals = response.totals;
                        this.refundedAmount = totals.refunded;
                        this.pendingRefunds = totals.pendingRefunds;
                        this.remainingAmount = totals.remaining;
                        this.voucherAmount = totals.voucherAmount;
                        this.roundingDiff = totals.roundingDiff;

                        this._applyRefundedItems(response.refundedItems);
                    } else {
                        this._showNotificationError(response.errors[0]);
                    }
                })
                .catch((response) => {
                    this._showNotificationError(response.error);
                });
        },

        // ---------------------------------------------------------------------------------------------------------
        // </editor-fold>
        // ---------------------------------------------------------------------------------------------------------

        // ---------------------------------------------------------------------------------------------------------
        // <editor-fold desc="PRIVATE METHODS">
        // ---------------------------------------------------------------------------------------------------------

        /**
         * Loads all data from Mollie (or Shopware) for this order.
         * The whole content of the form is then replaced
         * with that live data.
         */
        _fetchFormData() {
            this.isRefundDataLoading = true;

            const me = this;

            this.MolliePaymentsRefundService.getRefundOverview({
                orderId: this.order.id,
            })
                .then((response) => {
                    if (!response || response.success === false) {
                        this.isRefundDataLoading = false;
                        return;
                    }

                    this.mollieRefunds = response.refunds;
                    this.remainingAmount = response.totals.remaining;
                    this.refundedAmount = response.totals.refunded;
                    this.voucherAmount = response.totals.voucherAmount;
                    this.pendingRefunds = response.totals.pendingRefunds;
                    this.roundingDiff = response.totals.roundingDiff;

                    this.orderItems = [];
                    response.cart.forEach(function (item) {
                        const localItem = {
                            refunded: item.refunded,
                            shopware: item.shopware,
                        };
                        me.itemService.resetRefundData(localItem);
                        me.orderItems.push(localItem);
                    });

                    this.isRefundDataLoading = false;
                })
                .catch(() => {
                    this.isRefundDataLoading = false;
                });
        },

        /**
         *
         */
        _calculateFinalAmount() {
            var totalRefundAmount = 0;

            this.orderItems.forEach(function (lineItem) {
                totalRefundAmount += parseFloat(lineItem.refundAmount);
            });

            this.refundAmount = this._roundToTwo(totalRefundAmount);
        },

        /**
         *
         * @param num
         * @returns {number}
         */
        _roundToTwo(num) {
            return +(Math.round(num + 'e+2') + 'e-2');
        },

        /**
         *
         * @param snippetKey
         * @private
         */
        _showNotification(snippetKey) {
            this.createNotificationWarning({
                message: this.$tc(snippetKey),
            });
        },

        /**
         *
         * @param text
         * @private
         */
        _showNotificationWarning(text) {
            this.createNotificationWarning({
                message: this.$tc(text),
            });
        },

        /**
         *
         * @param text
         * @private
         */
        _showNotificationSuccess(text) {
            this.createNotificationSuccess({
                message: text,
            });
        },

        /**
         *
         * @param text
         * @private
         */
        _showNotificationError(text) {
            this.createNotificationError({
                message: text,
            });
        },

        _isRefundSuccess(response) {
            return typeof response.refund?.id === 'string';
        },

        _applyRefundedItems(refundedItems) {
            if (!refundedItems) {
                return;
            }

            this.orderItems.forEach(function (item) {
                item.refunded = refundedItems[item.shopware.id] ?? 0;
            });
        },

        _handleRefundSuccess(response) {
            this.isRefunding = false;

            this._showNotificationSuccess(
                this.$tc('mollie-payments.refund-manager.notifications.success.refund-created'),
            );

            this.$emit('refund-success');

            this.mollieRefunds = [response.refund].concat(this.mollieRefunds);

            const totals = response.totals;
            this.refundedAmount = totals.refunded;
            this.pendingRefunds = totals.pendingRefunds;
            this.remainingAmount = totals.remaining;
            this.voucherAmount = totals.voucherAmount;
            this.roundingDiff = totals.roundingDiff;

            this.btnResetCartForm_Click();

            this._applyRefundedItems(response.refundedItems);
        },
        // ---------------------------------------------------------------------------------------------------------
        // </editor-fold>
        // ---------------------------------------------------------------------------------------------------------
    },
});
