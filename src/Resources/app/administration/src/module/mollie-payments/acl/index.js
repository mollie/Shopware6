// eslint-disable-next-line no-undef
Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'mollie',
    key: 'mollie_refund_manager',
    roles: {
        viewer: {
            privileges: [
                'mollie_refund_manager:read',
                'mollie_refund:read',
            ],
            dependencies: [],
        },
        creator: {
            privileges: [
                'mollie_refund_manager:create',
                'mollie_refund:create',
            ],
            dependencies: [
                'mollie_refund_manager.viewer',
            ],
        },
        deleter: {
            privileges: [
                'mollie_refund_manager:delete',
                'mollie_refund:delete',
            ],
            dependencies: [
                'mollie_refund_manager.creator',
            ],
        },
    },
});

// eslint-disable-next-line no-undef
Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'mollie',
    key: 'mollie_subscription',
    roles: {
        viewer: {
            privileges: [
                'mollie_subscription:read',
                // ------------------------------------
                'mollie_subscription_address:read',
                // ------------------------------------
                'mollie_subscription_history:read',
            ],
            dependencies: [],
        },
        editor: {
            privileges: [
                'mollie_subscription:update',
                // ------------------------------------
                'mollie_subscription_address:create',
                'mollie_subscription_address:update',
                // ------------------------------------
                'mollie_subscription_history:create',
                'mollie_subscription_history:update',
            ],
            dependencies: [
                'mollie_subscription.viewer',
            ],
        },
        deleter: {
            privileges: [
                // we don't allow entity-operations on subscriptions.
                // they must not be deleted by anyone!
                // so let's create a custom one for cancellation
                'mollie_subscription_custom:cancel',
            ],
            dependencies: [
                // it's enough to see the detail page
                'mollie_subscription.viewer',
            ],
        },
    },
});