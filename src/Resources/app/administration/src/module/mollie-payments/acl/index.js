// eslint-disable-next-line no-undef
Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'mollie',
    key: 'refund_manager',
    roles: {
        viewer: {
            privileges: [
                'refund_manager:read',
            ],
            dependencies: [],
        },
        editor: {
            privileges: [
                'refund_manager:write',
            ],
            dependencies: [
                'refund_manager.viewer',
            ],
        },
        deleter: {
            privileges: [
                'refund_manager:delete',
            ],
            dependencies: [
                'refund_manager.editor',
            ],
        },
    },
});