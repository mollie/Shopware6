export default class CreditCardScreen {
    /**
     *
     */
    enterValidCard() {
        const currentYear = parseInt(new Date().getFullYear().toString().substr(-2));

        this.setCardHolder('Cypress Test');
        this.setCardNumber('3782 822463 10005');
        this.setExpiryDate('12' + (currentYear + 1));
        this.setVerificationCode('1234');
    }

    /**
     *
     * @param name
     */
    setCardHolder(name) {
        cy.wait(500); // there is a weird iFrame reloading...with wait it works good
        cy.get('iframe[name="cardHolder-input"],  #card-form iframe').then(($element) => {
            const $body = $element.contents().find('body');
            cy.wrap($body).find('#cardHolder').eq(0).click();
            cy.wait(10);
            cy.wrap($body).find('#cardHolder').eq(0).type(name);
        });
    }

    /**
     *
     * @param number
     */
    setCardNumber(number) {
        cy.wait(500); // there is a weird iFrame reloading...with wait it works good
        cy.get('iframe[name="cardNumber-input"],  #card-form iframe').then(($element) => {
            const $body = $element.contents().find('body');
            cy.wrap($body).find('#cardNumber').eq(0).click();
            cy.wait(10);
            cy.wrap($body).find('#cardNumber').eq(0).type(number);
        });
    }

    /**
     *
     * @param expiryDate
     */
    setExpiryDate(expiryDate) {
        cy.wait(500); // there is a weird iFrame reloading...with wait it works good
        cy.get('iframe[name="expiryDate-input"],  #card-form iframe').then(($element) => {
            const $body = $element.contents().find('body');
            cy.wrap($body).find('#expiryDate,#cardExpiryDate').eq(0).click();
            cy.wait(10);
            cy.wrap($body).find('#expiryDate,#cardExpiryDate').eq(0).type(expiryDate);
        });
    }

    /**
     *
     * @param cvc
     */
    setVerificationCode(cvc) {
        cy.wait(500); // there is a weird iFrame reloading...with wait it works good
        cy.get('iframe[name="verificationCode-input"],  #card-form iframe').then(($element) => {
            const $body = $element.contents().find('body');
            cy.wrap($body).find('#verificationCode,#cardCvv').eq(0).click();
            cy.wait(10);
            cy.wrap($body).find('#verificationCode,#cardCvv').eq(0).type(cvc);
        });
    }

    /**
     *
     */
    submitForm() {
        cy.get('#card-form iframe').then(($element) => {
            const $body = $element.contents().find('body');
            cy.wrap($body).find('button[type="submit"]').eq(0).click();
            cy.wait(10);
        });
        //cy.get('#submit-button').click();
    }
}