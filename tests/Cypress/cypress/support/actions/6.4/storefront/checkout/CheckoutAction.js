import OffCanvasRepository from 'Repositories/6.4/storefront/checkout/OffCanvasRepository';
import ConfirmRepository from 'Repositories/6.4/storefront/checkout/ConfirmRepository';
import PaymentsRepository from 'Repositories/6.4/storefront/checkout/PaymentsRepository';


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

        // expand our collapsed view of payment methods
        // then we see the full list afterwards
        repoConfirm.getShowMorePaymentButtonsLabel().click();

        // click on the name of the payment
        cy.contains(paymentName).click({force: true});
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

