import template from './mollie-pluginconfig-section-info.html.twig';
import './mollie-pluginconfig-section-info.scss';
import { getStore } from './../../../../core/service/utils/store.utils';

const { Component, Mixin } = Shopware;

interface SectionInfoComponent {
    [key: string]: any;
}

const componentConfig: ThisType<SectionInfoComponent> = {
    template,

    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            isSupportOpen: false,
        };
    },

    shortcuts: {
        'SYSTEMKEY+i': 'openConfigImport',
    },

    computed: {
        userName() {
            const user = this.getCurrentUser();
            if (!user) {
                return '';
            }
            if (user.firstName === '') {
                return user.username;
            }
            return user.firstName;
        },

        hasSalesChannelList() {
            // Minimum supported Shopware version is >= 6.5.8, so the sales channel list is always available.
            return true;
        },
    },

    methods: {
        openConfigImport() {
            // TODO create and open a configuration import modal
        },

        getCurrentUser() {
            return getStore('session')?.currentUser ?? null;
        },

        openSupport() {
            this.isSupportOpen = true;
        },

        closeSupport() {
            this.isSupportOpen = false;
        },
    },
};

Component.register('mollie-pluginconfig-section-info', componentConfig);
