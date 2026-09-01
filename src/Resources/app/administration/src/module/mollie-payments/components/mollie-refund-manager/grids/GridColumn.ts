/**
 * Column definition for a Shopware sw-data-grid.
 */
export interface GridColumn {
    property: string;
    label: string;
    width?: string;
    align?: 'left' | 'center' | 'right';
    sortable?: boolean;
}
