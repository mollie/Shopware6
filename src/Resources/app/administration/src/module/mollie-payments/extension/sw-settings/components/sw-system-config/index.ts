// No import is needed in this override, so mark the file as a module explicitly
// (otherwise TS treats it as a global script and `const Component` collides).
export {};

const { Component } = Shopware;

interface SwSystemConfigOverride {
    [key: string]: any;
}

const overrideConfig: ThisType<SwSystemConfigOverride> = {
    provide() {
        return {
            actualConfigData: this.actualConfigData,
            // TODO: currentSalesChannelId is not reactive and does not change when a new
            // sales channel is selected in the config, so the preview only works for "all
            // sales channels", not for a specific one. To be fixed later.
            currentSalesChannelId: this.currentSalesChannelId,
        };
    },
};

Component.override('sw-system-config', overrideConfig);
