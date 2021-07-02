export default class Shopware {

    /*

     */
    getVersion() {
        return Cypress.env().SHOPWARE;
    }

}