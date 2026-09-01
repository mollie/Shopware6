const MOLLIE_HANDLER_PREFIX = 'handler_mollie_';
const PINIA_STORE = 'paymentOverviewCard';
const VUEX_MODULE = 'paymentOverviewCardState';

/**
 * Shopware decides which payment methods belong to a custom overview card by calling
 * paymentMethodHandlers.includes() with the formatted handler identifier. Mollie ships around
 * forty handlers and gains new ones with almost every release, so the answer is given by prefix
 * instead of by a hardcoded list that would silently go stale.
 */
class MolliePaymentMethodHandlers extends Array<string> {
    public includes(formattedHandlerIdentifier: string): boolean {
        return (
            typeof formattedHandlerIdentifier === 'string' &&
            formattedHandlerIdentifier.startsWith(MOLLIE_HANDLER_PREFIX)
        );
    }
}

const overviewCard = {
    positionId: 'mollie-payment-overview-card',
    component: 'mollie-payment-overview-card',
    paymentMethodHandlers: new MolliePaymentMethodHandlers(),
};

/**
 * Shopware 6.7 keeps the overview cards in a Pinia store, 6.5 and 6.6 in a Vuex module.
 */
function registerOverviewCard(): void {
    if (Shopware.Store?.list?.().includes(PINIA_STORE)) {
        Shopware.Store.get(PINIA_STORE).add(overviewCard);

        return;
    }

    Shopware.State?.commit(`${VUEX_MODULE}/add`, overviewCard);
}

registerOverviewCard();
