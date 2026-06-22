import template from './mollie-tracking-info.html.twig';
import './mollie-tracking-info.scss';

const { Component } = Shopware;
const { string } = Shopware.Utils;

interface TrackingInfoComponent {
    delivery: any;
    tracking: { carrier: string; code: string; url: string };

    [key: string]: any;
}

const componentConfig: ThisType<TrackingInfoComponent> = {
    template,

    props: {
        delivery: {
            type: Object,
            required: true,
            default() {
                return null;
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
                };
            },
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (this.delivery.trackingCodes.length === 1) {
                this.prefillTrackingInfo(this.delivery.trackingCodes[0], this.delivery.shippingMethod);
            }
        },

        prefillTrackingInfo(trackingCode: string, shippingMethod: any) {
            this.tracking.carrier = shippingMethod.name;
            this.tracking.code = trackingCode;

            if (!string.isEmptyOrSpaces(shippingMethod.trackingUrl)) {
                this.tracking.url = this.renderTrackingUrl(trackingCode, shippingMethod);
            }
        },

        /**
         * Copied from src/Administration/Resources/app/administration/src/module/sw-order/component/sw-order-user-card/index.js
         */
        renderTrackingUrl(trackingCode: string, shippingMethod: any): string {
            const urlTemplate = shippingMethod ? shippingMethod.trackingUrl : null;

            return urlTemplate ? urlTemplate.replace('%s', encodeURIComponent(trackingCode)) : '';
        },
    },
};

Component.register('mollie-tracking-info', componentConfig);
