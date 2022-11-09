export default class ShipThroughMollieRepository {


    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getShipButton() {
        return cy.get('.sw-modal__footer > .sw-button--primary');
    }

}
