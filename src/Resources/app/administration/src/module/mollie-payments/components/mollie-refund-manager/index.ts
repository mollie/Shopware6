import template from './mollie-refund-manager.html.twig';
import './mollie-refund-manager.scss';
import RefundItemService from './services/RefundItemService';
import RefundCalculator from './services/RefundCalculator';
import RefundPayloadBuilder, { type RefundResponse } from './services/RefundPayloadBuilder';

const { Component, Mixin } = Shopware;

/**
 * Describes the `this` context of the refund manager component.
 *
 * Only the service instances and the data we actually interact with in a typed
 * way are listed explicitly. Everything else provided by Shopware at runtime
 * (mixins, injections, $tc, $emit, props, ...) is covered by the index signature,
 * so the Options API keeps working without the official Shopware admin types.
 */
interface RefundManagerComponent {
    itemService: RefundItemService;
    calculator: RefundCalculator;
    payloadBuilder: RefundPayloadBuilder;
    orderItems: any[];
    mollieRefunds: any[];
    refundAmount: number;
    remainingAmount: number;
    isRefunding: boolean;
    isRefundDataLoading: boolean;

    [key: string]: any;
}

const componentConfig: ThisType<RefundManagerComponent> = {
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
            default() {
                return { verifyRefund: true, autoStockReset: true, showInstructions: true };
            },
        },
    },

    data() {
        return {
            // services
            itemService: null,
            calculator: null,
            payloadBuilder: null,
            // basic view state
            isRefundDataLoading: false,
            isRefunding: false,
            // grids
            orderItems: [],
            mollieRefunds: [],
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
            // tutorials
            tutorialFullRefundVisible: false,
            tutorialPartialAmountRefundVisible: false,
            tutorialPartialQuantityVisible: false,
            tutorialPartialPromotionsVisible: false,
            tutorialResetStock: false,
            tutorialRefundShipping: false,
        };
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

        isAclRefundAllowed() {
            return this.acl.can('mollie_refund_manager:create');
        },

        isAclCancelAllowed() {
            return this.acl.can('mollie_refund_manager:delete');
        },

        /**
         * Instruction blocks rendered in the first instructions column.
         * Each block links a title/text snippet to the tutorial it highlights.
         */
        instructionBlocksPrimary() {
            const prefix = 'mollie-payments.refund-manager.instructions';
            return [
                {
                    title: `${prefix}.titleFullRefund`,
                    text: `${prefix}.textFullRefund`,
                    toggle: this.btnToggleTutorialFull_Click,
                },
                {
                    title: `${prefix}.titleRoundingDiff`,
                    text: `${prefix}.textRoundingDiff`,
                    toggle: this.btnToggleTutorialFull_Click,
                },
                {
                    title: `${prefix}.titleStockReset`,
                    text: `${prefix}.textStockReset`,
                    toggle: this.btnToggleTutorialStock_Click,
                },
                {
                    title: `${prefix}.titleShipping`,
                    text: `${prefix}.textShipping`,
                    toggle: this.btnToggleTutorialShipping_Click,
                },
            ];
        },

        /**
         * Instruction blocks rendered in the second instructions column.
         */
        instructionBlocksSecondary() {
            const prefix = 'mollie-payments.refund-manager.instructions';
            return [
                {
                    title: `${prefix}.titlePartialAmount`,
                    text: `${prefix}.textPartialAmount`,
                    toggle: this.btnToggleTutorialPartialAmount_Click,
                },
                {
                    title: `${prefix}.titlePartialItems`,
                    text: `${prefix}.textPartialItems`,
                    toggle: this.btnToggleTutorialPartialQuantities_Click,
                },
                {
                    title: `${prefix}.titlePartialPromotions`,
                    text: `${prefix}.textPartialPromotions`,
                    toggle: this.btnToggleTutorialPartialPromotions_Click,
                },
            ];
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.itemService = new RefundItemService();
            this.calculator = new RefundCalculator();
            this.payloadBuilder = new RefundPayloadBuilder();

            if (this.order) {
                this._fetchFormData();
            }
        },

        // -------------------------------------------------------------------------------------------------
        // ORDER FORM
        // -------------------------------------------------------------------------------------------------

        /**
         * Selects all items by assigning their maximum refundable quantity.
         */
        btnSelectAllItems_Click() {
            this.orderItems.forEach((item) => this.itemService.setFullRefund(item));
            this._calculateFinalAmount();
        },

        /**
         * Resets all line items as well as the verification checkbox and descriptions.
         */
        btnResetCartForm_Click() {
            this.orderItems.forEach((item) => this.itemService.resetRefundData(item));
            this._calculateFinalAmount();

            this.checkVerifyRefund = false;
            this.refundDescription = '';
            this.refundInternalDescription = '';
        },

        onItemQtyChanged(item: any) {
            this.itemService.onQuantityChanged(item);

            // verify if we also need to set the stock automatically
            if (this.configAutoStockReset) {
                this.itemService.setStockReset(item, item.refundQuantity);
            }

            this._calculateFinalAmount();
        },

        onItemAmountChanged(item: any) {
            this.itemService.onAmountChanged(item);
            this._calculateFinalAmount();
        },

        onItemRefundTaxChanged(item: any) {
            this.itemService.onRefundTaxChanged(item);
            this._calculateFinalAmount();
        },

        onItemPromotionDeductionChanged(item: any) {
            this.itemService.onPromotionDeductionChanged(item);
            this._calculateFinalAmount();
        },

        btnResetLine_Click(item: any) {
            this.itemService.resetRefundData(item);
            this._calculateFinalAmount();
        },

        // -------------------------------------------------------------------------------------------------
        // INSTRUCTIONS
        // -------------------------------------------------------------------------------------------------

        btnToggleTutorialFull_Click() {
            this.tutorialFullRefundVisible = !this.tutorialFullRefundVisible;
        },

        btnToggleTutorialPartialAmount_Click() {
            this.tutorialPartialAmountRefundVisible = !this.tutorialPartialAmountRefundVisible;
        },

        btnToggleTutorialPartialQuantities_Click() {
            this.tutorialPartialQuantityVisible = !this.tutorialPartialQuantityVisible;
        },

        btnToggleTutorialPartialPromotions_Click() {
            this.tutorialPartialPromotionsVisible = !this.tutorialPartialPromotionsVisible;
        },

        btnToggleTutorialStock_Click() {
            this.tutorialResetStock = !this.tutorialResetStock;
        },

        btnToggleTutorialShipping_Click() {
            this.tutorialRefundShipping = !this.tutorialRefundShipping;
        },

        btnResetTutorials_Click() {
            this.tutorialFullRefundVisible = false;
            this.tutorialPartialAmountRefundVisible = false;
            this.tutorialPartialQuantityVisible = false;
            this.tutorialPartialPromotionsVisible = false;
            this.tutorialResetStock = false;
            this.tutorialRefundShipping = false;
        },

        // -------------------------------------------------------------------------------------------------
        // SUMMARY
        // -------------------------------------------------------------------------------------------------

        /**
         * Uses the full remaining amount for the refund field ("top up" in case of rounding issues).
         */
        btnFixDiff_Click() {
            this.refundAmount = this.remainingAmount;
        },

        /**
         * Starts a partial refund with everything that has been set up in the cart form.
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

            this.isRefunding = true;
            this.MolliePaymentsRefundService.refund({
                orderId: this.order.id,
                amount: this.refundAmount,
                description: this.refundDescription,
                internalDescription: this.refundInternalDescription,
                items: this.payloadBuilder.buildItems(this.orderItems),
            })
                .then((response: RefundResponse) => {
                    if (this.payloadBuilder.isRefundSuccess(response)) {
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
         * Starts a full refund for the whole order. This does not consider any special
         * line item setup, but only performs a full refund and stock reset.
         */
        btnRefundFull_Click() {
            if (!this.isAclRefundAllowed) {
                return;
            }

            this.isRefunding = true;
            this.MolliePaymentsRefundService.refundAll({
                orderId: this.order.id,
                description: this.refundDescription,
                internalDescription: this.refundInternalDescription,
            })
                .then((response: RefundResponse) => {
                    if (this.payloadBuilder.isRefundSuccess(response)) {
                        this._handleRefundSuccess(response);
                    } else {
                        this._showNotificationError(response.errors?.[0]);
                    }
                })
                .finally(() => {
                    this.isRefunding = false;
                });
        },

        // -------------------------------------------------------------------------------------------------
        // REFUND GRID
        // -------------------------------------------------------------------------------------------------

        btnCancelRefund_Click(item: any) {
            if (!this.isAclCancelAllowed) {
                return;
            }

            this.MolliePaymentsRefundService.cancel({
                orderId: this.order.id,
                refundId: item.id,
            })
                .then((response: any) => {
                    if (response.success) {
                        this._showNotificationSuccess(
                            this.$tc('mollie-payments.refund-manager.notifications.success.refund-canceled'),
                        );
                        this.$emit('refund-cancelled');
                        this.mollieRefunds = this.mollieRefunds.map((refund) => {
                            if (refund.id !== item.id) {
                                return refund;
                            }
                            return { ...refund, status: 'canceled', isPending: false, isQueued: false };
                        });
                    } else {
                        this._showNotificationError(response.errors?.[0]);
                    }
                })
                .catch((response: any) => {
                    this._showNotificationError(response.error);
                });
        },

        // -------------------------------------------------------------------------------------------------
        // PRIVATE METHODS
        // -------------------------------------------------------------------------------------------------

        /**
         * Loads all data from Mollie (or Shopware) for this order and replaces the whole form content.
         */
        _fetchFormData() {
            this.isRefundDataLoading = true;

            this.MolliePaymentsRefundService.getRefundOverview({
                orderId: this.order.id,
            })
                .then((response: any) => {
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

                    this.orderItems = response.cart.map((item: any) => {
                        const localItem = {
                            refunded: item.refunded,
                            shopware: item.shopware,
                        };
                        this.itemService.resetRefundData(localItem);
                        return localItem;
                    });

                    this.isRefundDataLoading = false;
                })
                .catch(() => {
                    this.isRefundDataLoading = false;
                });
        },

        _calculateFinalAmount() {
            this.refundAmount = this.calculator.calculateTotalRefundAmount(this.orderItems);
        },

        _showNotificationWarning(message: string) {
            this.createNotificationWarning({ message });
        },

        _showNotificationSuccess(message: string) {
            this.createNotificationSuccess({ message });
        },

        _showNotificationError(message: any) {
            this.createNotificationError({ message });
        },

        _handleRefundSuccess(refund: RefundResponse) {
            this.isRefunding = false;

            this._showNotificationSuccess(
                this.$tc('mollie-payments.refund-manager.notifications.success.refund-created'),
            );

            this.$emit('refund-success');

            this.mollieRefunds = [refund].concat(this.mollieRefunds);
            this.btnResetCartForm_Click();
        },
    },
};

Component.register('mollie-refund-manager', componentConfig);
