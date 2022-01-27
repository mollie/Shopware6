import template from './mollie-tracking-info.html.twig';
import './mollie-tracking-info.scss';

// eslint-disable-next-line no-undef
const {Component} = Shopware;
// eslint-disable-next-line no-undef
const {string} = Shopware.Utils;

Component.register('mollie-tracking-info', {
    template,

    props: {
        delivery: {
            type: Object,
            required: true,
            default() {
                return null
            },
        },

        tracking: {
            type: Object,
            required: true,
            default() {
                return {
                    carrier: '',
                    code: '',
                    url: '',
                }
            },
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if(this.delivery.trackingCodes.length === 1) {
                this.prefillTrackingInfo(this.delivery.trackingCodes[0], this.delivery.shippingMethod);
            }
        },

        prefillTrackingInfo(trackingCode, shippingMethod) {
            this.tracking.carrier = shippingMethod.name;
            this.tracking.code = trackingCode;

            if(!string.isEmptyOrSpaces(shippingMethod.trackingUrl)) {
                this.tracking.url = this.renderTrackingUrl(trackingCode, shippingMethod);
            }
        },

        /**
         * Copied from src/Administration/Resources/app/administration/src/module/sw-order/component/sw-order-user-card/index.js
         */
        renderTrackingUrl(trackingCode, shippingMethod) {
            const urlTemplate = shippingMethod ? shippingMethod.trackingUrl : null;

            return urlTemplate ? urlTemplate.replace('%s', encodeURIComponent(trackingCode)) : '';
        },
    },
});
