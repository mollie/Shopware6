import template from './mollie-refund-manager.html.twig';
import './mollie-refund-manager.scss';
import RefundItemService from './services/RefundItemService';
import RefundCalculator from './services/RefundCalculator';
import RefundPayloadBuilder, { type RefundResponse } from './services/RefundPayloadBuilder';
import './components/mollie-refund-manager-cart';
import './components/mollie-refund-manager-refunds';
import './components/mollie-refund-manager-instructions';
import './components/mollie-refund-manager-summary';

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
            tutorialRoundingDiffVisible: false,
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
         * Each block links a title/text snippet to the tutorial it highlights and
         * the section that should be scrolled into view when it is activated.
         */
        instructionBlocksPrimary() {
            const p = 'mollie-payments.refund-manager.instructions';
            return [
                this.buildInstructionBlock(`${p}.titleFullRefund`, `${p}.textFullRefund`, 'tutorialFullRefundVisible'),
                this.buildInstructionBlock(
                    `${p}.titleRoundingDiff`,
                    `${p}.textRoundingDiff`,
                    'tutorialRoundingDiffVisible',
                ),
                this.buildInstructionBlock(`${p}.titleStockReset`, `${p}.textStockReset`, 'tutorialResetStock'),
                this.buildInstructionBlock(`${p}.titleShipping`, `${p}.textShipping`, 'tutorialRefundShipping'),
            ];
        },

        /**
         * Instruction blocks rendered in the second instructions column.
         */
        instructionBlocksSecondary() {
            const p = 'mollie-payments.refund-manager.instructions';
            return [
                this.buildInstructionBlock(
                    `${p}.titlePartialAmount`,
                    `${p}.textPartialAmount`,
                    'tutorialPartialAmountRefundVisible',
                ),
                this.buildInstructionBlock(
                    `${p}.titlePartialItems`,
                    `${p}.textPartialItems`,
                    'tutorialPartialQuantityVisible',
                ),
                this.buildInstructionBlock(
                    `${p}.titlePartialPromotions`,
                    `${p}.textPartialPromotions`,
                    'tutorialPartialPromotionsVisible',
                ),
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
            this.orderItems.forEach((item: any) => this.itemService.setFullRefund(item));
            this._calculateFinalAmount();
        },

        /**
         * Resets all line items as well as the verification checkbox and descriptions.
         */
        btnResetCartForm_Click() {
            this.orderItems.forEach((item: any) => this.itemService.resetRefundData(item));
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

        /**
         * Builds a single instruction block: resolved title/text, a state-aware
         * label ("Show Tutorial" / "Hide Tutorial") and a toggle handler.
         */
        buildInstructionBlock(titleKey: string, textKey: string, flagKey: string) {
            const prefix = 'mollie-payments.refund-manager.instructions';
            return {
                title: this.$tc(titleKey),
                text: this.$tc(textKey),
                label: this.$tc(this[flagKey] ? `${prefix}.btnHideTutorial` : `${prefix}.btnShowTutorial`),
                toggle: () => this.toggleTutorial(flagKey),
            };
        },

        /**
         * Toggles the given tutorial flag. When it becomes active, the first
         * highlighted element is scrolled into view so the user sees what the
         * tutorial points at.
         */
        toggleTutorial(flagKey: string) {
            this[flagKey] = !this[flagKey];

            if (!this[flagKey]) {
                return;
            }

            this.$nextTick(() => {
                const target = this.$el.querySelector('.tutorial-active');
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            });
        },

        btnResetTutorials_Click() {
            this.tutorialFullRefundVisible = false;
            this.tutorialRoundingDiffVisible = false;
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
                        this.mollieRefunds = this.mollieRefunds.map((refund: any) => {
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
