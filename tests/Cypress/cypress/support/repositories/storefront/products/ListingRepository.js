export default class ListingRepository {

    /**
     *
     * @returns {*}
     */
    getFirstProduct() {
        return cy.get(':nth-child(1) > .card > .card-body > .product-image-wrapper');
    }

    /**
     *
     * @param n
     * @returns {*}
     */
    getNthProduct(n) {
        return cy.get(':nth-child(' + n + ') > .card > .card-body > .product-image-wrapper');
    }

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getApplePayDirectButton() {
        return cy.get('.mollie-apple-pay-direct-listing > div > .js-apple-pay');
    }

}
