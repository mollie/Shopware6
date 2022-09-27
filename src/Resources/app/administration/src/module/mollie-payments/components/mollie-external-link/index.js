import template from './mollie-external-link.html.twig';
import './mollie-external-link.scss'

// eslint-disable-next-line no-undef
const { Component } = Shopware;

if (Component.getComponentRegistry().has('sw-external-link')) {
    Component.extend('mollie-external-link', 'sw-external-link', {});
} else {
    /**
     * This is just a copy from the sw-external-link component in Shopware 6.4
     */
    Component.register('mollie-external-link', {
        template,

        inheritAttrs: false,

        props: {
            small: {
                type: Boolean,
                required: false,
                default: false,
            },

            icon: {
                type: String,
                required: false,
                default: 'small-arrow-small-external',
            },

            rel: {
                type: String,
                required: false,
                default: 'noopener',
            },
        },

        computed: {
            classes() {
                return {
                    'mollie-external-link--small': this.small,
                };
            },

            iconSize() {
                if (this.small) {
                    return '8px';
                }

                return '10px';
            },
        },

        methods: {
            onClick(event) {
                this.$emit('click', event);
            },
        },
    })
}
