interface SubscriptionRuleCondition {
    [key: string]: any;
}

/**
 * Builds the shared sw-condition-base config for the Mollie "is subscription"
 * cart and line-item rules, which only differ by their template.
 */
export default function createSubscriptionRuleConfig(template: any) {
    const config: ThisType<SubscriptionRuleCondition> = {
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

                set(isSubscription: boolean) {
                    this.ensureValueExist();
                    this.condition.value = { ...this.condition.value, isSubscription };
                },
            },
        },
    };

    return config;
}
