// eslint-disable-next-line no-undef
const { Component } = Shopware;

Component.override('sw-system-config', {
    provide() {
        return {
            actualConfigData: this.actualConfigData,
            currentSalesChannelId: this.currentSalesChannelId, //TODO: currentSalesChannelId is not reactive and does not change when you select a new saleschannel in config. because of this the preview does work only for all saleschannels but not for a specific one. we have to fix the preview later
        };
    },
});
