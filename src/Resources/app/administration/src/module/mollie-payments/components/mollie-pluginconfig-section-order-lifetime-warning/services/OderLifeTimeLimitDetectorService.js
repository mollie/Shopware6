export default class OrderLifeTimeLimitsDetector {
    maximumOrderLifeTimeKlarna = 28;
    maximumOrderLifeTime = 100;
    oderLifeTimeLimitReached = false;
    klarnaOrderLifeTimeReached = false;

    checkValue(orderLifeTime) {
        this.oderLifeTimeLimitReached = orderLifeTime > this.maximumOrderLifeTime;
        this.klarnaOrderLifeTimeReached = !this.oderLifeTimeLimitReached && orderLifeTime > this.maximumOrderLifeTimeKlarna;
    }

    isOderLifeTimeLimitReached() {
        return this.oderLifeTimeLimitReached;
    }

    isKlarnaOrderLifeTimeReached() {
        return this.klarnaOrderLifeTimeReached;
    }

}