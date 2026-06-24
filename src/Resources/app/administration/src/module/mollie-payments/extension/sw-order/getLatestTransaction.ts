export interface OrderTransactionLike {
    createdAt?: string | null;

    [key: string]: any;
}

/**
 * Returns the most recently created transaction from a list of order
 * transactions (an array or a DAL EntityCollection), or null when the list is
 * empty. Both the sw-order-detail override and the mollie-order-tab view need
 * the latest transaction, so the logic lives here to stay testable and DRY.
 */
export default function getLatestTransaction(
    transactions: Iterable<OrderTransactionLike> | null | undefined,
): OrderTransactionLike | null {
    if (!transactions) {
        return null;
    }

    return Array.from(transactions).reduce<OrderTransactionLike | null>((latest, current) => {
        if (latest === null) {
            return current;
        }

        return new Date(current.createdAt ?? 0) > new Date(latest.createdAt ?? 0) ? current : latest;
    }, null);
}
