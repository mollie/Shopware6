import ConfirmRepository from 'Repositories/storefront/checkout/ConfirmRepository';
import PaymentsRepository from 'Repositories/storefront/checkout/PaymentsRepository';
import Shopware from "Services/shopware/Shopware";

const shopware = new Shopware();

const repoConfirm = new ConfirmRepository();
const repoPayments = new PaymentsRepository();

export default class PaymentAction {


    /**
     * @version Shopware < 6.4
     */
    openPaymentsModal() {
        repoConfirm.getSwitchPaymentMethodsButton().click();
    }

    /**
     * @version Shopware < 6.4
     */
    closePaymentsModal() {
        // always force, because the save button
        // might not be visible with a larger payments list
        repoPayments.getSubmitButton().click({force: true});
        cy.wait(1000);
    }

    /**
     * @version Shopware >= 6.4
     */
    showAllPaymentMethods() {
        repoConfirm.getShowMorePaymentButtonsLabel().click();
    }

    /**
     * @param paymentName
     * @version All Shopware versions.
     */
    selectPaymentMethod(paymentName) {
        cy.get('.payment-methods').contains(paymentName).click({force: true});
    }

    /**
     * @param paymentName
     * @version All Shopware versions.
     */
    switchPaymentMethod(paymentName) {
        if (shopware.isVersionGreaterEqual(6.4)) {
            // this version has all payment methods
            // directly on the confirm page.
            // but we need to expand the whole list
            // to see all payment methods
            this.showAllPaymentMethods();
            this.selectPaymentMethod(paymentName);

            // we have to select an iDEAL issuer now (required)
            if (paymentName === 'iDEAL') {
                this.selectIDealIssuer('bunq');
            }

            if (paymentName === 'POS Terminal') {
                this.selectPosTerminal();
            }

        } else {
            this.openPaymentsModal();
            this.selectPaymentMethod(paymentName);

            // we have to select an iDEAL issuer now (required)
            if (paymentName === 'iDEAL') {
                this.selectIDealIssuer('bunq');
            }

            if (paymentName === 'POS Terminal') {
                this.selectPosTerminal();
            }

            this.closePaymentsModal();
        }
    }

    /**
     *
     * @param name
     * @param number
     * @param expiryDate
     * @param cvc
     */
    fillCreditCardComponents(name, number, expiryDate, cvc) {

        // always make sure that the iFrame is loaded
        cy.wait(2500);

        // that iframe seems to need a bit. had some missing characters recently
        // so we click in a textfield, wait and then type
        const clickTimeMS = 100;

        // we can insert nothing
        // but cypress would throw an error with .type()
        if (name !== "") {
            cy.get('iframe[name="cardHolder-input"]').then($element => {
                const $body = $element.contents().find('body')
                cy.wrap($body).find('#cardHolder').eq(0).click();
                cy.wait(clickTimeMS);
                cy.wrap($body).find('#cardHolder').eq(0).type(name);
            })
        }

        cy.get('iframe[name="cardNumber-input"]').then($element => {
            const $body = $element.contents().find('body')
            cy.wrap($body).find('#cardNumber').eq(0).click();
            cy.wait(clickTimeMS);
            cy.wrap($body).find('#cardNumber').eq(0).type(number);
        })

        cy.get('iframe[name="expiryDate-input"]').then($element => {
            const $body = $element.contents().find('body')
            cy.wrap($body).find('#expiryDate').eq(0).click();
            cy.wait(clickTimeMS);
            cy.wrap($body).find('#expiryDate').eq(0).type(expiryDate);
        })

        cy.get('iframe[name="verificationCode-input"]').then($element => {
            const $body = $element.contents().find('body')
            cy.wrap($body).find('#verificationCode').eq(0).click();
            cy.wait(clickTimeMS);
            cy.wrap($body).find('#verificationCode').eq(0).type(cvc);
        })

        // just wait a bit
        // after filling the iframes out
        cy.wait(1000);
    }

    /**
     *
     * @param issuer
     */
    selectIDealIssuer(issuer) {
        cy.get('#iDealIssuer').select(issuer);
    }

    /**
     *
     */
    selectPosTerminal() {
        const testTerminalID = 'Test terminal';
        cy.get('#posTerminals').select(testTerminalID);
    }

}
