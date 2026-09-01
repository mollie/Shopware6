export default class SubscriptionService {
    private readonly _app: any;

    constructor(shopwareApp: any) {
        this._app = shopwareApp;
    }

    getStatusTranslation(status: string | null): string {
        if (status === '' || status === null) {
            status = '';
        }

        if (
            ['pending', 'active', 'canceled', 'suspended', 'completed', 'paused', 'resumed', 'skipped'].includes(status)
        ) {
            return this._app.$tc('mollie-payments.subscriptions.status.' + status);
        }

        return status;
    }

    getStatusColor(status: string | null): string {
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

    isCancellationAllowed(status: string): boolean {
        return status !== 'canceled' && status !== 'pending';
    }

    isSkipAllowed(status: string): boolean {
        return status === 'active' || status === 'resumed';
    }

    isPauseAllowed(status: string): boolean {
        return status === 'active' || status === 'resumed';
    }

    isResumeAllowed(status: string): boolean {
        return status === 'paused' || status === 'canceled';
    }
}
