export default class OrderLifeTimeLimitsDetector {
    /**
     *
     */
    constructor() {
        this.maximumOrderLifeTimeKlarna = 28;
        this.maximumOrderLifeTime = 100;
    }

    /**
     *
     * @param orderLifeTime
     * @returns {boolean}
     */
    isOderLifeTimeLimitReached(orderLifeTime) {
        return orderLifeTime > this.maximumOrderLifeTime;
    }

    /**
     *
     * @param orderLifeTime
     * @returns {boolean}
     */
    isKlarnaOrderLifeTimeReached(orderLifeTime) {
        return orderLifeTime > this.maximumOrderLifeTimeKlarna && orderLifeTime <= this.maximumOrderLifeTime;
    }
}
