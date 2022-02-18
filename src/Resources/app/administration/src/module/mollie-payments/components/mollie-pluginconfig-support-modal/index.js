import template from './mollie-pluginconfig-support-modal.html.twig';
import './mollie-pluginconfig-support-modal.scss';

// eslint-disable-next-line no-undef
const {Component, Context, Mixin, State} = Shopware;

Component.register('mollie-pluginconfig-support-modal', {
    template,

    inject: [
        'shopwareExtensionService',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            contactName: '',
            contactEmail: '',
            subject: '',
            message: '',
        }
    },

    computed: {
        isLoading() {
            return State.get('shopwareExtensions').myExtensions.loading;
        },

        shopwareVersion() {
            return this.humanReadableVersion(Context.app.config.version);
        },

        user() {
            return State.get('session').currentUser;
        },

        userName() {
            return `${this.user?.firstName} ${this.user?.lastName}`.trim() ?? this.user?.userName ?? '';
        },

        molliePlugin() {
            return State.get('shopwareExtensions').myExtensions.data
                .find(plugin => plugin.name === 'MolliePayments');
        },

        mollieVersion() {
            return this.molliePlugin
                ? this.humanReadableVersion(this.molliePlugin.version)
                : '';
        },
    },

    mounted() {
        if(!this.molliePlugin) {
            this.shopwareExtensionService.updateExtensionData();
        }
    },

    methods: {
        humanReadableVersion(version) {
            const match = version.match(/(\d+\.?\d+\.?\d+\.?\d*)-?([a-z]+)?(\d+(.\d+)*)?/i);

            if (match === null) {
                return version;
            }

            let output = `v${match[1]}`;

            if (match[2]) {
                output += ` ${this.getHumanReadableText(match[2])}`;
            } else {
                output += ' Stable Version';
            }

            if (match[3]) {
                output += ` ${match[3]}`;
            }

            return output;
        },

        getHumanReadableText(text) {
            if (text === 'dp') {
                return 'Developer Preview';
            }

            if (text === 'rc') {
                return 'Release Candidate';
            }

            if (text === 'dev') {
                return 'Developer Version';
            }

            if (text === 'ea') {
                return 'Early Access';
            }

            return text;
        },
    },
});
