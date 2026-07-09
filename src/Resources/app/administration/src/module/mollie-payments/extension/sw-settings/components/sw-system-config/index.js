// eslint-disable-next-line no-undef
const { Component } = Shopware;

Component.override('sw-system-config', {
    // Shopware 6.7 (Vue 3) reassigns this.actualConfigData in createdComponent(), which orphans a
    // plain provided reference and leaves the preview stuck on the empty initial object. Providing
    // computed refs keeps the injected values live (Vue 3 auto-unwraps them in the Options API of
    // the injecting components) and also makes currentSalesChannelId reactive on channel switch.
    // Shopware 6.5 (Vue 2.7) mutates actualConfigData in place, so the plain reference works there
    // — and Vue 2.7 does not unwrap injected refs, so computed() must NOT be used on that version.
    provide() {
        const vue = Shopware.Vue;

        if (vue && parseInt(vue.version, 10) >= 3) {
            return {
                actualConfigData: vue.computed(() => this.actualConfigData),
                currentSalesChannelId: vue.computed(() => this.currentSalesChannelId),
            };
        }

        return {
            actualConfigData: this.actualConfigData,
            currentSalesChannelId: this.currentSalesChannelId,
        };
    },
});
