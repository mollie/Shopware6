import template from './mollie-pluginconfig-support-modal.html.twig';
import './mollie-pluginconfig-support-modal.scss';

// eslint-disable-next-line no-undef
const {Component, Context, Mixin, State} = Shopware;
// eslint-disable-next-line no-undef
const {string} = Shopware.Utils;

Component.register('mollie-pluginconfig-support-modal', {
    template,

    inject: [
        'shopwareExtensionService',
        'MolliePaymentsSupportService',
    ],

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
        }
    },

    computed: {
        isLoading() {
            return State.get('shopwareExtensions').myExtensions.loading;
        },

        canSubmit() {
            return !string.isEmptyOrSpaces(this.contactName)
                && !string.isEmptyOrSpaces(this.contactEmail)
                && !string.isEmptyOrSpaces(this.subject)
                && !string.isEmptyOrSpaces(this.message)
        },

        shopwareVersion() {
            return this.humanReadableVersion(Context.app.config.version);
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
                    console.log(response);
                    this.mailSent = true;
                })
                .finally(() => this.isSubmitting = false)
            // console.log(
            //     `'${this.contactName}'`,
            //     `'${this.contactEmail}'`,
            //     `'${this.subject}'`,
            //     `'${this.message}'`
            // );

        },
    },
});
