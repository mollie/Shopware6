import template from './mollie-lineitem-subscription-rule.html.twig';

// eslint-disable-next-line no-undef
Shopware.Component.extend('mollie-lineitem-subscription-rule', 'sw-condition-base', {
    template,

    computed: {

        selectValues() {
            return [
                {
                    label: this.$tc('global.sw-condition.condition.yes'),
                    value: true,
                },
                {
                    label: this.$tc('global.sw-condition.condition.no'),
                    value: false,
                },
            ];
        },

        isSubscription: {

            get() {
                this.ensureValueExist();

                if (this.condition.value.isSubscription == null) {
                    this.condition.value.isSubscription = false;
                }

                return this.condition.value.isSubscription;
            },

            set(isSubscription) {
                this.ensureValueExist();
                this.condition.value = {...this.condition.value, isSubscription};
            },
        },
    },
});
