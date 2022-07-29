export default class CreditCardScreenAction {

    /**
     *
     * @param name
     */
    setCardHolder(name) {
        cy.wait(200);
        cy.get('iframe[name="cardHolder-input"]').then($element => {
            const $body = $element.contents().find('body')
            cy.wrap($body).find('#cardHolder').eq(0).click();
            cy.wait(10);
            cy.wrap($body).find('#cardHolder').eq(0).type(name);
        })
    }

    /**
     *
     * @param number
     */
    setCardNumber(number) {
        cy.wait(200);
        cy.get('iframe[name="cardNumber-input"]').then($element => {
            const $body = $element.contents().find('body')
            cy.wrap($body).find('#cardNumber').eq(0).click();
            cy.wait(10);
            cy.wrap($body).find('#cardNumber').eq(0).type(number);
        })
    }

    /**
     *
     * @param expiryDate
     */
    setExpiryDate(expiryDate) {
        cy.wait(200);
        cy.get('iframe[name="expiryDate-input"]').then($element => {
            const $body = $element.contents().find('body')
            cy.wrap($body).find('#expiryDate').eq(0).click();
            cy.wait(10);
            cy.wrap($body).find('#expiryDate').eq(0).type(expiryDate);
        })
    }

    /**
     *
     * @param cvc
     */
    setVerificationCode(cvc) {
        cy.wait(200);
        cy.get('iframe[name="verificationCode-input"]').then($element => {
            const $body = $element.contents().find('body')
            cy.wrap($body).find('#verificationCode').eq(0).click();
            cy.wait(10);
            cy.wrap($body).find('#verificationCode').eq(0).type(cvc);
        })
    }

    /**
     *
     */
    enterValidCard() {
        const currentYear = new Date().getFullYear().toString().substr(-2);

        this.setCardHolder('Cypress Shopware');
        this.setCardNumber('3782 822463 10005');
        this.setExpiryDate('12' + (currentYear + 1));
        this.setVerificationCode('1234');
    }

    /**
     *
     */
    submitForm() {
        cy.get('#submit-button').click();
    }

}
