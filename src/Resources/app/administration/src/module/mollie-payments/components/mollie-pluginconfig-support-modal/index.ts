import template from './mollie-pluginconfig-support-modal.html.twig';
import './mollie-pluginconfig-support-modal.scss';
import VersionCompare from './../../../../core/service/utils/version-compare.utils';

const { Application, Component, Context, Mixin, State } = Shopware;
const { Criteria } = Shopware.Data;
const { string: stringUtils } = Shopware.Utils;

interface SupportModalComponent {
    versionCompare: any;
    name: string;
    email: string;
    subject: string;
    message: string;
    recipientLocale: string | null;
    mailSent: boolean;
    isSubmitting: boolean;

    [key: string]: any;
}

const componentConfig: ThisType<SupportModalComponent> = {
    template,

    inject: {
        shopwareExtensionService: { default: null }, // This did not exist before 6.4, so default to null to avoid errors.
        MolliePaymentsSupportService: {},
        repositoryFactory: {},
    },

    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            mailSent: false,
            isSubmitting: false,
            name: '',
            email: '',
            subject: '',
            message: '',
            recipientLocale: '',
            recipientOptions: [
                {
                    label: 'International Support (info@mollie.com)',
                    value: null,
                },
                {
                    label: 'German Support (meinsupport@mollie.com)',
                    value: 'de-DE',
                },
            ],
            versionCompare: null,
        };
    },

    computed: {
        isLoading() {
            if (this.shopwareExtensionService) {
                return this.getShopwareExtensions().loading;
            }

            return this.isLoadingPlugins;
        },

        canSubmit() {
            return (
                !stringUtils.isEmptyOrSpaces(this.contactName) &&
                !stringUtils.isEmptyOrSpaces(this.contactEmail) &&
                !stringUtils.isEmptyOrSpaces(this.subject) &&
                !stringUtils.isEmptyOrSpaces(this.message)
            );
        },

        contactName: {
            get() {
                return !stringUtils.isEmptyOrSpaces(this.name) ? this.name : this.userName;
            },
            set(value: string) {
                this.name = value;
            },
        },

        contactEmail: {
            get() {
                return !stringUtils.isEmptyOrSpaces(this.email) ? this.email : this.user.email;
            },
            set(value: string) {
                this.email = value;
            },
        },

        locale() {
            return Application.getContainer('factory').locale.getLastKnownLocale();
        },

        user() {
            let session = Shopware.State.get('session');
            if (session === undefined) {
                session = Shopware.Store.get('session');
            }
            return session.currentUser;
        },

        userName() {
            if (!this.user) {
                return '';
            }

            const fullName = `${this.user.firstName} ${this.user.lastName}`.trim();

            if (!stringUtils.isEmptyOrSpaces(fullName)) {
                return fullName;
            }

            return this.user.username;
        },

        plugins() {
            // If this is not null, we're in Shopware 6.4 and using the new extension service
            if (this.shopwareExtensionService) {
                return this.getShopwareExtensions().data || [];
            }
            let swPlugin = Shopware.State.get('swPlugin');
            if (swPlugin === undefined) {
                swPlugin = Shopware.Store.get('swPlugin');
            }
            return swPlugin.plugins || [];
        },

        molliePlugin() {
            return this.plugins.find((plugin: any) => plugin.name === 'MolliePayments');
        },

        mollieVersion() {
            return this.molliePlugin ? this.versionCompare.getHumanReadableVersion(this.molliePlugin.version) : '';
        },

        shopwareVersion() {
            return this.versionCompare.getHumanReadableVersion(Context.app.config.version);
        },
    },

    created() {
        this.versionCompare = new VersionCompare();
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        mountedComponent() {
            this.determineDefaultSupportDesk();

            if (this.plugins.length === 0) {
                if (this.shopwareExtensionService) {
                    this.shopwareExtensionService.updateExtensionData();
                } else {
                    this.loadPluginsLegacy();
                }
            }
        },

        getShopwareExtensions() {
            let myExtensions = Shopware.State.get('shopwareExtensions');
            if (myExtensions === undefined) {
                myExtensions = Shopware.Store.get('shopwareExtensions');
            }
            return myExtensions.myExtensions;
        },

        determineDefaultSupportDesk() {
            this.recipientLocale = this.recipientOptions.some((option: any) => option.value === this.locale)
                ? this.locale
                : null;
        },

        loadPluginsLegacy() {
            this.isLoadingPlugins = true;

            const criteria = new Criteria();
            criteria.setTerm('Mollie');

            const searchData = {
                criteria: criteria,
                repository: this.repositoryFactory.create('plugin'),
                context: Context.api,
            };

            State.dispatch('swPlugin/updatePluginList', searchData).finally(() => {
                this.isLoadingPlugins = false;
            });
        },

        onRequestSupport() {
            this.isSubmitting = true;

            this.MolliePaymentsSupportService.request(
                this.contactName,
                this.contactEmail,
                this.recipientLocale,
                this.subject,
                this.message,
            )
                .then((response: any) => {
                    if (!response.success) {
                        this._showNotificationError(this.$tc('mollie-payments.config.support.error'));
                        this.mailSent = false;
                        return;
                    }

                    this.mailSent = true;
                    this._showNotificationSuccess(this.$tc('mollie-payments.config.support.success'));
                })
                .catch((response: any) => {
                    this._showNotificationError(response);
                })
                .finally(() => {
                    this.isSubmitting = false;
                });
        },

        _showNotificationSuccess(message: string) {
            this.createNotificationSuccess({ message });
        },

        _showNotificationError(message: any) {
            this.createNotificationError({ message });
        },
    },
};

Component.register('mollie-pluginconfig-support-modal', componentConfig);
