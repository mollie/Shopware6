export default class OrderLifeTimeLimitsDetector {
    maximumOrderLifeTimeKlarna = 28;
    maximumOrderLifeTime = 100;

    isOderLifeTimeLimitReached(orderLifeTime) {
        return orderLifeTime > this.maximumOrderLifeTime;
    }

    isKlarnaOrderLifeTimeReached(orderLifeTime) {
        return orderLifeTime > this.maximumOrderLifeTimeKlarna && orderLifeTime <= this.maximumOrderLifeTime;
    }

}