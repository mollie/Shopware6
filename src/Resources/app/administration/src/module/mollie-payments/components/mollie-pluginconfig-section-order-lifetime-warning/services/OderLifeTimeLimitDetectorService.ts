/**
 * Detects whether the configured order lifetime exceeds the Mollie limits.
 * Kept free of any Shopware/Vue/DOM dependency so it can be unit tested.
 */
export default class OrderLifeTimeLimitsDetector {
    private readonly maximumOrderLifeTimeKlarna = 28;
    private readonly maximumOrderLifeTime = 100;

    isOderLifeTimeLimitReached(orderLifeTime: number): boolean {
        return orderLifeTime > this.maximumOrderLifeTime;
    }

    isKlarnaOrderLifeTimeReached(orderLifeTime: number): boolean {
        return orderLifeTime > this.maximumOrderLifeTimeKlarna && orderLifeTime <= this.maximumOrderLifeTime;
    }
}
