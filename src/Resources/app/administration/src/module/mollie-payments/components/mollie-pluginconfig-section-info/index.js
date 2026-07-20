import template from './mollie-pluginconfig-section-info.html.twig';
import './mollie-pluginconfig-section-info.scss';

// eslint-disable-next-line no-undef
const { Component, Mixin } = Shopware;

Component.register('mollie-pluginconfig-section-info', {
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
        /**
         * @returns {string|*}
         */
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
            // eslint-disable-next-line no-undef
            let session = Shopware.State.get('session');
            if (session === undefined) {
                session = Shopware.Store.get('session');
            }
            return session.currentUser;
        },
        openSupport() {
            this.isSupportOpen = true;
        },

        closeSupport() {
            this.isSupportOpen = false;
        },
    },
});
