import template from './mollie-pluginconfig-section-info.html.twig';
import './mollie-pluginconfig-section-info.scss';
import VersionCompare from './../../../../core/service/utils/version-compare.utils';

// eslint-disable-next-line no-undef
const { Component, Mixin } = Shopware;

Component.register('mollie-pluginconfig-section-info', {
    template,

    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            isSupportOpen: false,
            versionCompare:null,
        };
    },

    shortcuts: {
        'SYSTEMKEY+i': 'openConfigImport',
    },
    created() {
        this.versionCompare = new VersionCompare();
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
            return this.versionCompare.greaterOrEqual(Shopware.Context.app.config.version, '6.4.2');
        },
    },

    methods: {
        openConfigImport() {
            // TODO create and open a configuration import modal
        },
        getCurrentUser(){
            // eslint-disable-next-line no-undef
            let session = Shopware.State.get('session');
            if(session === undefined){
                session = Shopware.Store.get('session')
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
