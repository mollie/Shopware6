export default class SubscriptionService {

    /**
     *
     * @param shopwareApp
     */
    constructor(shopwareApp) {
        this._app = shopwareApp;
    }


    /**
     *
     * @param status
     * @returns {string|*}
     */
    getStatusTranslation(status) {

        if (status === '' || status === null) {
            status = '';
        }

        if (['pending', 'active', 'canceled', 'suspended', 'completed', 'paused', 'resumed', 'skipped'].includes(status)) {
            return this._app.$tc('mollie-payments.subscriptions.status.' + status);
        }

        return status;
    }

    /**
     *
     * @param status
     * @returns {string}
     */
    getStatusColor(status) {

        if (status === '' || status === null) {
            return 'neutral';
        }

        if (status === 'active' || status === 'resumed') {
            return 'success';
        }

        if (status === 'canceled' || status === 'suspended' || status === 'completed') {
            return 'neutral';
        }

        if (status === 'skipped') {
            return 'info';
        }

        if (status === 'pending' || status === 'paused') {
            return 'warning';
        }

        return 'danger';
    }

    /**
     *
     * @param status
     * @returns {boolean}
     */
    isCancellationAllowed(status) {
        return (status !== 'canceled' && status !== 'pending');
    }

    /**
     *
     * @param status
     * @returns {boolean}
     */
    isSkipAllowed(status) {
        return (status === 'active' || status === 'resumed');
    }

    /**
     *
     * @param status
     * @returns {boolean}
     */
    isPauseAllowed(status) {
        return (status === 'active' || status === 'resumed');
    }

    /**
     *
     * @param status
     * @returns {boolean}
     */
    isResumeAllowed(status) {
        return (status === 'paused' || status === 'canceled');
    }

}