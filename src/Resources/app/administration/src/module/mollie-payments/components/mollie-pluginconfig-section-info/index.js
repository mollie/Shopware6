import template from './mollie-pluginconfig-section-info.html.twig';
import './mollie-pluginconfig-section-info.scss';


// eslint-disable-next-line no-undef
const {Component, Mixin} = Shopware;

Component.register('mollie-pluginconfig-section-info', {
    template,

    mixins: [
        Mixin.getByName('notification'),
    ],

    computed: {

        /**
         *
         * @returns {*}
         */
        title() {
            let text = this.$tc('mollie-payments.config.info.title');

            text = text.replace('##userName##', this.userName);

            return text;
        },

        /**
         *
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
    },
});
