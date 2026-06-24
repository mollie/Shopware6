import template from './mollie-pluginconfig-section-info.html.twig';
import './mollie-pluginconfig-section-info.scss';
import VersionCompare from './../../../../core/service/utils/version-compare.utils';
import { getStore } from './../../../../core/service/utils/store.utils';

const { Component, Mixin } = Shopware;

interface SectionInfoComponent {
    versionCompare: any;

    [key: string]: any;
}

const componentConfig: ThisType<SectionInfoComponent> = {
    template,

    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            isSupportOpen: false,
            versionCompare: null,
        };
    },

    shortcuts: {
        'SYSTEMKEY+i': 'openConfigImport',
    },

    created() {
        this.versionCompare = new VersionCompare();
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
            return this.versionCompare.greaterOrEqual(Shopware.Context.app.config.version, '6.4.2');
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
