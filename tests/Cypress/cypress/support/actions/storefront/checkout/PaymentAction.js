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
        } else {
            this.openPaymentsModal();
            this.selectPaymentMethod(paymentName);
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
        cy.wait(1000);

        // we can insert nothing
        // but cypress would throw an error with .type()
        if (name !== "") {
            cy.get('iframe[name="cardHolder-input"]').then($element => {
                const $body = $element.contents().find('body')
                cy.wrap($body).find('#cardHolder').eq(0).click().type(name);
            })
        }

        cy.get('iframe[name="cardNumber-input"]').then($element => {
            const $body = $element.contents().find('body')
            cy.wrap($body).find('#cardNumber').eq(0).click().type(number);
        })

        cy.get('iframe[name="expiryDate-input"]').then($element => {
            const $body = $element.contents().find('body')
            cy.wrap($body).find('#expiryDate').eq(0).click().type(expiryDate);
        })

        cy.get('iframe[name="verificationCode-input"]').then($element => {
            const $body = $element.contents().find('body')
            cy.wrap($body).find('#verificationCode').eq(0).click().type(cvc);
        })
    }

    /**
     *
     * @param issuer
     */
    selectIDealIssuer(issuer) {
        cy.get('#iDealIssuer').select(issuer);
    }

}
