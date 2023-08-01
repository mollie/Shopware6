import Shopware from "Services/shopware/Shopware";

const shopware = new Shopware();


export default class AdminSettingsAction {

    /**
     *
     */
    openBusinessEventsPage() {

        cy.visit('/admin#/sw/settings/index');
        cy.wait(4000);
        cy.get('a#sw-event-action').click();
        cy.wait(3000);
    }

    openFirstBusinessEvent(){
        cy.get('.sw-data-grid__body .sw-data-grid__row.sw-data-grid__row--0 .sw-context-button__button').click({force:true});
        cy.get('.sw-context-menu-item.sw-entity-listing__context-menu-edit-action').click();
    }


}
