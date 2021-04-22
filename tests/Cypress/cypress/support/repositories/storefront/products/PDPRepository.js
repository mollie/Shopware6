export default class PDPRepository {

    /**
     *
     * @returns {*}
     */
    getAddToCartButton() {
        return cy.get('.buy-widget-container > .col-8 > .btn');
    }

    /**
     *
     * @returns {*}
     */
    getQuantity() {
        return cy.get('.custom-select');
    }

}
