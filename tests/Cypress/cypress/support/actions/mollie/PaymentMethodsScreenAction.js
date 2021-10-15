export default class PaymentMethodsScreenAction {

    /**
     *
     */
    selectPaypal() {
        cy.contains('PayPal').click();
    }

}

