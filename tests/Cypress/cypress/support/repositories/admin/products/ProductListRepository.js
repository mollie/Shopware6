export default class ProductListRepository {

    /**
     *
     * @returns {string}
     */
    getFirstProductTitle() {
        return cy.get('.sw-data-grid__row--0 > .sw-data-grid__cell--name > .sw-data-grid__cell-content > :nth-child(2) > a');
    }

}
