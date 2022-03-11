import template from './mollie-pluginconfig-support-modal.html.twig';
import './mollie-pluginconfig-support-modal.scss';

// eslint-disable-next-line no-undef
const { Component, Context, Mixin, State } = Shopware;
// eslint-disable-next-line no-undef
const { Criteria } = Shopware.Data;
// eslint-disable-next-line no-undef
const { string } = Shopware.Utils;

Component.register('mollie-pluginconfig-support-modal', {
    template,

    inject: {
        shopwareExtensionService: { default: null }, // This did not exist before 6.4, so default to null to avoid errors.
        MolliePaymentsSupportService: {},
        repositoryFactory: {},
    },

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            name: '',
            email: '',
            subject: '',
            message: '',

            isSubmitting: false,
            mailSent: false,

            isLoadingPlugins: false,
        }
    },

    computed: {
        isLoading() {
            if (this.shopwareExtensionService) {
                return State.get('shopwareExtensions').myExtensions.loading;
            }

            return this.isLoadingPlugins;
        },

        canSubmit() {
            return !string.isEmptyOrSpaces(this.contactName)
                && !string.isEmptyOrSpaces(this.contactEmail)
                && !string.isEmptyOrSpaces(this.subject)
                && !string.isEmptyOrSpaces(this.message)
        },


        contactName: {
            get() {
                return !string.isEmptyOrSpaces(name)
                    ? this.name
                    : this.userName;
            },
            set(value) {
                this.name = value;
            },
        },

        contactEmail: {
            get() {
                return !string.isEmptyOrSpaces(this.email)
                    ? this.email
                    : this.user.email;
            },
            set(value) {
                this.email = value;
            },
        },

        user() {
            return State.get('session').currentUser;
        },

        userName() {
            return `${this.user?.firstName} ${this.user?.lastName}`.trim() ?? this.user?.userName ?? '';
        },

        plugins() {
            // If this is not null, we're in Shopware 6.4 and using the new extension service
            if (this.shopwareExtensionService) {
                return State.get('shopwareExtensions').myExtensions.data || [];
            }

            return State.get('swPlugin').plugins || [];
        },

        molliePlugin() {
            return this.plugins.find(plugin => plugin.name === 'MolliePayments');
        },

        mollieVersion() {
            return this.molliePlugin
                ? this.humanReadableVersion(this.molliePlugin.version)
                : '';
        },

        shopwareVersion() {
            return this.humanReadableVersion(Context.app.config.version);
        },
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        mountedComponent() {
            if (this.plugins.length === 0) {
                if (this.shopwareExtensionService) {
                    this.shopwareExtensionService.updateExtensionData();
                } else {
                    this.loadPluginsLegacy();
                }
            }
        },

        loadPluginsLegacy() {
            this.isLoadingPlugins = true;

            const criteria = new Criteria();
            criteria.setTerm('Mollie');

            const searchData = {
                criteria: criteria,
                repository: this.repositoryFactory.create('plugin'),
                context: Context.api,
            }

            State.dispatch('swPlugin/updatePluginList', searchData)
                .finally(() => {
                    this.isLoadingPlugins = false;
                });
        },

        onRequestSupport() {
            this.isSubmitting = true;

            this.MolliePaymentsSupportService
                .request(
                    this.contactName,
                    this.contactEmail,
                    this.subject,
                    this.message,
                )
                .then((response) => {
                    this.mailSent = true;
                })
                .finally(() => this.isSubmitting = false)
        },

        humanReadableVersion(version) {
            const match = version.match(/(\d+\.?\d+\.?\d+\.?\d*)-?([a-z]+)?(\d+(.\d+)*)?/i);

            if (match === null) {
                return version;
            }

            let output = `v${ match[1] }`;

            if (match[2]) {
                output += ` ${ this.getHumanReadableText(match[2]) }`;
            } else {
                output += ' Stable Version';
            }

            if (match[3]) {
                output += ` ${ match[3] }`;
            }

            return output;
        },

        getHumanReadableText(text) {
            switch (text) {
                case 'dp':
                    return 'Developer Preview';
                case 'rc':
                    return 'Release Candidate';
                case 'dev':
                    return 'Developer Version';
                case 'ea':
                    return 'Early Access';
                default:
                    return text;
            }
        },
    },
});
