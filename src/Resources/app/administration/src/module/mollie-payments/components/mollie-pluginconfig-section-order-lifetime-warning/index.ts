import template from './mollie-pluginconfig-section-order-lifetime-warning.twig';
import './mollie-pluginconfig-section-order-lifetime-warning.scss';
import OrderLifeTimeLimitsDetectorService from './services/OderLifeTimeLimitDetectorService';

const { Component, Mixin } = Shopware;

interface OrderLifetimeWarningComponent {
    limitDetector: OrderLifeTimeLimitsDetectorService;
    oderLifeTimeLimitReached: boolean;
    klarnaOrderLifeTimeReached: boolean;

    [key: string]: any;
}

const componentConfig: ThisType<OrderLifetimeWarningComponent> = {
    template,

    mixins: [Mixin.getByName('notification')],

    inject: ['MolliePaymentsConfigService'],

    data() {
        return {
            limitDetector: null,
            oderLifeTimeLimitReached: false,
            klarnaOrderLifeTimeReached: false,
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.limitDetector = new OrderLifeTimeLimitsDetectorService();

            // The config input is rendered later, so poll until it is in the DOM.
            const interval = setInterval(() => {
                const orderLifeTimeElement = document.querySelector<HTMLInputElement>(
                    'input[name="MolliePayments.config.orderLifetimeDays"],[class*="mollie-payments-config-order-lifetime-days"] input',
                );

                if (orderLifeTimeElement === null) {
                    return;
                }
                clearInterval(interval);

                this.updateWarnings(parseInt(orderLifeTimeElement.value, 10));

                orderLifeTimeElement.addEventListener(
                    'keyup',
                    (event) => {
                        this.updateWarnings(parseInt((event.target as HTMLInputElement).value, 10));
                    },
                    true,
                );
            }, 500);
        },

        updateWarnings(orderLifeTime: number) {
            this.oderLifeTimeLimitReached = this.limitDetector.isOderLifeTimeLimitReached(orderLifeTime);
            this.klarnaOrderLifeTimeReached = this.limitDetector.isKlarnaOrderLifeTimeReached(orderLifeTime);
        },
    },
};

Component.register('mollie-pluginconfig-section-order-lifetime-warning', componentConfig);
