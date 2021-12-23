import template from './mollie-tracking-info.html.twig';

// eslint-disable-next-line no-undef
const {Component} = Shopware;

Component.register('mollie-tracking-info', {
    template,

    props: {
        tracking: {
            type: Object,
            required: true,
            default() {
                return {
                    carrier: '',
                    code: '',
                    url: '',
                }
            }
        },
    }
});
