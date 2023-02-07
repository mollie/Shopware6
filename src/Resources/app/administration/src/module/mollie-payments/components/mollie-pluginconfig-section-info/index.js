import template from './mollie-pluginconfig-section-info.html.twig';
import './mollie-pluginconfig-section-info.scss';

const VersionCompare = require('../../../../core/service/utils/version-compare.utils').default;

// eslint-disable-next-line no-undef
const {Component, Context, Mixin} = Shopware;

Component.register('mollie-pluginconfig-section-info', {
    template,

    mixins: [
        Mixin.getByName('notification'),
    ],

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
            // eslint-disable-next-line no-undef
            const user = Shopware.State.get('session').currentUser;

            if (!user) {
                return '';
            }

            if (user.firstName === '') {
                return user.username;
            }

            return user.firstName;
        },

        hasSalesChannelList() {
            return VersionCompare.greaterOrEqual(Context.app.config.version, '6.4.2');
        },
    },

    methods: {
        openConfigImport() {
            // TODO create and open a configuration import modal
        },

        openSupport() {
            this.isSupportOpen = true;
        },

        closeSupport() {
            this.isSupportOpen = false;
        },
    },
});
