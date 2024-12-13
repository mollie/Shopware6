export default class AdminSettingsAction {

    /**
     *
     */
    openBusinessEventsPage() {
        cy.visit('/admin#/sw/settings/index');
        cy.get('a#sw-event-action').click();
    }

    /**
     *
     */
    openFirstBusinessEvent() {
        cy.get('.sw-data-grid__body .sw-data-grid__row.sw-data-grid__row--0 .sw-context-button__button').click({force: true});
        cy.get('.sw-context-menu-item.sw-entity-listing__context-menu-edit-action').click({force: true});
    }

}
