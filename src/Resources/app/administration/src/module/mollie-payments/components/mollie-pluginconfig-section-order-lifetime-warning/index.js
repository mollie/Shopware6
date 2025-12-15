import template from './mollie-pluginconfig-section-order-lifetime-warning.twig';
import OrderLifeTimeLimitsDetectorService from './services/OderLifeTimeLimitDetectorService';

// eslint-disable-next-line no-undef
const { Component, Mixin } = Shopware;
import './mollie-pluginconfig-section-order-lifetime-warning.scss';

Component.register('mollie-pluginconfig-section-order-lifetime-warning', {
    template,

    inject: ['MolliePaymentsConfigService'],

    mixins: [Mixin.getByName('notification')],
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
            const limitDetector = new OrderLifeTimeLimitsDetectorService();
            /**
             * The input element is displayed later, so we have to wait until it is inside the dom document
             */
            const interval = setInterval(() => {
                const orderLifeTimeElement = document.querySelector(
                    'input[name="MolliePayments.config.orderLifetimeDays"],[class*="mollie-payments-config-order-lifetime-days"] input',
                );

                if (orderLifeTimeElement === null) {
                    return;
                }
                clearInterval(interval);
                const value = parseInt(orderLifeTimeElement.value);

                this.oderLifeTimeLimitReached = limitDetector.isOderLifeTimeLimitReached(value);
                this.klarnaOrderLifeTimeReached = limitDetector.isKlarnaOrderLifeTimeReached(value);

                orderLifeTimeElement.addEventListener(
                    'keyup',
                    (event) => {
                        const value = parseInt(event.target.value);

                        this.oderLifeTimeLimitReached = limitDetector.isOderLifeTimeLimitReached(value);
                        this.klarnaOrderLifeTimeReached = limitDetector.isKlarnaOrderLifeTimeReached(value);
                    },
                    true,
                );
            }, 500);
        },
    },
});
