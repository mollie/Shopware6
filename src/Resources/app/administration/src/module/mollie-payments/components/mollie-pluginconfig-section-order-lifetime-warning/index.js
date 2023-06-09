import template from './mollie-pluginconfig-section-order-lifetime-warning.twig';


// eslint-disable-next-line no-undef
const {Component, Mixin} = Shopware;

Component.register('mollie-pluginconfig-section-order-lifetime-warning', {
    template,

    inject: [
        'MolliePaymentsConfigService',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],
    data() {
        return {
            oderLifeTimeLimitReached: false,
            klarnaOrderLifeTimeReached: false,
        };
    },
    created() {
        this.createdComponent();
    },
    methods: {
        createdComponent() {
            const maximumOrderLifeTimeKlarna = 28;
            const maximumOrderLifeTime = 100;
            /**
             * The input element is displayed later, so we have to wait until it is inside the dom document
             */
            const interval = setInterval(() => {
                const orderLifeTimeElement = document.querySelector('input[name="MolliePayments.config.orderLifetimeDays"]');

                if (orderLifeTimeElement === null) {
                    return;
                }
                clearInterval(interval);

                const orderLifeTime = parseInt(orderLifeTimeElement.value);
                this.oderLifeTimeLimitReached = orderLifeTime > maximumOrderLifeTime;
                this.klarnaOrderLifeTimeReached = !this.oderLifeTimeLimitReached && orderLifeTime > maximumOrderLifeTimeKlarna;

                orderLifeTimeElement.addEventListener("keyup", (event) => {
                    const orderLifeTime = parseInt(event.target.value);
                    this.oderLifeTimeLimitReached = orderLifeTime > maximumOrderLifeTime;
                    this.klarnaOrderLifeTimeReached = !this.oderLifeTimeLimitReached && orderLifeTime > maximumOrderLifeTimeKlarna;
                }, true);

            }, 500);

        },

    },
});
