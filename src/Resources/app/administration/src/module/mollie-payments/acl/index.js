// eslint-disable-next-line no-undef
Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'mollie',
    key: 'mollie_refund_manager',
    roles: {
        viewer: {
            privileges: [
                'mollie_refund_manager:read',
            ],
            dependencies: [],
        },
        editor: {
            privileges: [
                'mollie_refund_manager:write',
            ],
            dependencies: [
                'mollie_refund_manager.viewer',
            ],
        },
        deleter: {
            privileges: [
                'mollie_refund_manager:delete',
            ],
            dependencies: [
                'mollie_refund_manager.editor',
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
            ],
            dependencies: [],
        },
        editor: {
            privileges: [
                'mollie_subscription:update',
            ],
            dependencies: [
                'mollie_subscription.viewer',
            ],
        },
        deleter: {
            privileges: [
                'mollie_subscription:delete',
            ],
            dependencies: [
                'mollie_subscription.viewer',
            ],
        },
    },
});