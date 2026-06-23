import template from './mollie-pluginconfig-section-info.html.twig';
import './mollie-pluginconfig-section-info.scss';
import VersionCompare from './../../../../core/service/utils/version-compare.utils';

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

        title() {
            const userName = this.userName;
            const translated = this.$tc('mollie-payments.config.info.title', 0, { userName });

            // Interpolate {userName} ourselves: vue-i18n 9 (Shopware >= 6.6) does not
            // interpolate named values via $tc, while $t is not reliably available on the
            // Vue 2 components of Shopware 6.5. Using $tc + manual replace works on both.
            return translated.includes('{userName}') ? translated.replace('{userName}', userName) : translated;
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
            // Pinia store (Shopware >= 6.6/6.7) first, Vuex state as fallback for older versions.
            return (
                Shopware.Store?.get?.('session')?.currentUser ?? Shopware.State?.get?.('session')?.currentUser ?? null
            );
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
