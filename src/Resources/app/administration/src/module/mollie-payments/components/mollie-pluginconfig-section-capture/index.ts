import template from './mollie-pluginconfig-section-capture.html.twig';
import './mollie-pluginconfig-section-capture.scss';

const { Component } = Shopware;

interface DirectPaymentMethod {
    name: string;
    label: string;
}

interface SectionCaptureComponent {
    paymentMethods: DirectPaymentMethod[];
    isLoading: boolean;

    [key: string]: any;
}

const componentConfig: ThisType<SectionCaptureComponent> = {
    template,

    inject: ['MolliePaymentsPaymentMethodService'],

    emits: ['update:value'],

    props: {
        value: {
            type: Array,
            required: false,
            default: () => [],
        },
    },

    data() {
        return {
            paymentMethods: [],
            isLoading: true,
        };
    },

    created() {
        this.MolliePaymentsPaymentMethodService.getDirectPaymentMethods()
            .then((response: { methods?: DirectPaymentMethod[] }) => {
                this.paymentMethods = response.methods ?? [];
            })
            .finally(() => {
                this.isLoading = false;
            });
    },

    methods: {
        // The configuration holds the methods that are switched off, so anything not listed is on.
        isDirectPaymentEnabled(methodName: string): boolean {
            return !this.disabledMethods().includes(methodName);
        },

        setDirectPaymentEnabled(methodName: string, enabled: boolean): void {
            const disabled = this.disabledMethods().filter((name: string) => name !== methodName);

            if (!enabled) {
                disabled.push(methodName);
            }

            this.$emit('update:value', disabled);
        },

        disabledMethods(): string[] {
            return Array.isArray(this.value) ? [...this.value] : [];
        },
    },
};

Component.register('mollie-pluginconfig-section-capture', componentConfig);
