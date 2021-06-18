import OffCanvasRepository from 'Repositories/old/storefront/checkout/OffCanvasRepository';
import ConfirmRepository from 'Repositories/old/storefront/checkout/ConfirmRepository';
import PaymentsRepository from 'Repositories/old/storefront/checkout/PaymentsRepository';


const repoOffCanvas = new OffCanvasRepository();
const repoConfirm = new ConfirmRepository();
const repoPayments = new PaymentsRepository();


export default class CheckoutAction {

    /**
     *
     */
    goToCheckoutInOffCanvas() {
        repoOffCanvas.getCheckoutButton().click();
    }

    /**
     *
     */
    openPaymentSelectionOnConfirm() {
        repoConfirm.getSwitchPaymentMethodsButton().click();
    }

    /**
     *
     * @param paymentName
     */
    switchPaymentMethod(paymentName) {

        repoConfirm.getSwitchPaymentMethodsButton().click();

        // click on the name of the payment
        cy.contains(paymentName).click({force: true});

        repoPayments.getSubmitButton().click({force: true});
    }

    /**
     *
     * @returns {*}
     */
    getTotalFromConfirm() {
        return repoConfirm.getTotalSum().invoke('text').then((total) => {

            total = total.replace("*", "");
            total = total.replace("€", "");

            return total;
        });
    }

    /**
     *
     */
    placeOrderOnConfirm() {
        repoConfirm.getTerms().click('left');
        repoConfirm.getSubmitButton().click();
    }

}

