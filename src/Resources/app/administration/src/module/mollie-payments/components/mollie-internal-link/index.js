import template from './mollie-internal-link.html.twig';
import './mollie-internal-link.scss'

// eslint-disable-next-line no-undef
const { Component } = Shopware;

if (Component.getComponentRegistry().has('sw-internal-link')) {
    Component.extend('mollie-internal-link', 'sw-internal-link', {});
} else {
    /**
     * This is just a copy from the sw-internal-link component in Shopware 6.4
     */
    Component.register('mollie-internal-link', {
        template,

        props: {
            routerLink: {
                type: Object,
                required: true,
            },

            target: {
                type: String,
                required: false,
                default: null,
            },

            icon: {
                type: String,
                required: false,
                default: 'default-arrow-simple-right',
            },

            inline: {
                type: Boolean,
                required: false,
                default: false,
            },
        },

        computed: {
            componentClasses() {
                return {
                    'mollie-internal-link--inline': this.inline,
                };
            },
        },
    })
}
