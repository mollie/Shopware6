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

            /**
             * The input element is displayed later, so we have to wait until it is inside the dom document
             */
            const interval = setInterval(() => {
                const orderLifeTimeElement = document.querySelector('input[name="MolliePayments.config.orderLifetimeDays"]');

                if (orderLifeTimeElement === null) {
                    return;
                }
                clearInterval(interval);

                this.toggleWarning(orderLifeTimeElement);

                orderLifeTimeElement.addEventListener("keyup", (event) => {
                    this.toggleWarning(event.target);
                }, true);

            }, 500);
        },

        toggleWarning(element){
            const maximumOrderLifeTimeKlarna = 28;
            const maximumOrderLifeTime = 100;
            const orderLifeTime = parseInt(element.value);
            this.oderLifeTimeLimitReached = orderLifeTime > maximumOrderLifeTime;
            this.klarnaOrderLifeTimeReached = !this.oderLifeTimeLimitReached && orderLifeTime > maximumOrderLifeTimeKlarna;
        },
    },
});