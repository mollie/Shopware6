

export default class CancelItemRepository {
    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getQuantityInput(){
        return cy.get('.cy-cancel-item-quantity input');
    }

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getItemLabel(){
        return cy.get('.cy-cancel-item-label');
    }

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getResetStockToggle(){
        return cy.get('.cy-cancel-item-stock .sw-field')
    }

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getConfirmButton(){
        return cy.get('.cy-cancel-item-confirm .sw-button__content');
    }
}