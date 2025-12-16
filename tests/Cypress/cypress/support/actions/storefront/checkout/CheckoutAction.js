import OffCanvasRepository from 'Repositories/storefront/checkout/OffCanvasRepository';
import ConfirmRepository from 'Repositories/storefront/checkout/ConfirmRepository';
import TopMenuAction from "Actions/storefront/navigation/TopMenuAction";
import ListingAction from "Actions/storefront/products/ListingAction";
import PDPAction from "Actions/storefront/products/PDPAction";
import Shopware from "Services/shopware/Shopware";
import AddressModalRepository from "Repositories/storefront/checkout/AddressModalRepository";

const repoOffCanvas = new OffCanvasRepository();
const repoConfirm = new ConfirmRepository();
const repoAddressModal = new AddressModalRepository();

const shopware = new Shopware();

export default class CheckoutAction {


    /**
     *
     */
    clearCart() {
        // open off canvas
        cy.get('.header-cart-total').click({force: true});

        const btnRemoveItem = '.cart-item-remove > .btn, .line-item-remove .btn';

        cy.get('body').then((body) => {
            if (body.find(btnRemoveItem).length > 0) {
                cy.get(btnRemoveItem).click({multiple: true});
                cy.wait(300);
            }
        });
    }

    /**
     *
     */
    closeOffcanvasCart() {
        let selector = '.cart-offcanvas.is-open > .offcanvas-close';
        if (shopware.isVersionGreaterEqual('6.5')) {
            selector = '.cart-offcanvas.show .offcanvas-close';
        }
        cy.get(selector).click({force: true});
    }

    /**
     *
     */
    goToCartInOffCanvas() {
        // sometimes cookie banner seems to be on top
        repoOffCanvas.getCartButton().click({force: true});
    }

    goToCheckout() {
        cy.visit('/checkout/confirm');
    }

    /**
     *
     */
    goToCheckoutInOffCanvas() {
        repoOffCanvas.getCheckoutButton().click();
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

    /**
     *
     */
    placeOrderOnEdit() {
        cy.get('#confirmOrderForm .btn').click();
    }

    /**
     *
     */
    mollieFailureModeRetryPayment() {
        cy.get(':nth-child(3) > .btn-primary').click();
    }

    /**
     *
     */
    mollieFailureModeContinueShopping() {
        cy.get(':nth-child(3) > .btn-secondary').click();
    }

    /**
     *
     */
    backToShop() {
        cy.get('.header-minimal-back-to-shop > .btn').click();
    }

    changeToMollieShippingMethod() {
        cy.contains('Mollie Test Shipment').click();
    }

    changeBillingCountry(billingCountry) {

        if (shopware.isVersionGreaterEqual('6.6.10.9')) {
            repoConfirm.getChangeShippingAddressButton().click();
            repoAddressModal.getCurrentAddressEditButton().click();
            repoAddressModal.getEditFormCountryDropdownContainer().click();
        }
        else {
            repoConfirm.getChangeShippingAddressButton().click();
            cy.get('.address-editor-edit').click();
            cy.wait(1000);
        }

        repoAddressModal.getEditFormCountryDropdown().select(billingCountry);

        if (shopware.isVersionGreaterEqual('6.6.10.9')) {
            repoAddressModal.getEditFormSaveAddressButton().click();
        } else {
            cy.get('.address-form-actions:eq(0) button').click();
        }

        // since 6.6.8.0 the edit address modal does not close automatically
        if (shopware.isVersionGreaterEqual('6.6.8.0') && shopware.isVersionLower('6.6.9.0')) {
            cy.wait(1000);
            cy.get('.js-pseudo-modal .modal-dialog .btn-close').click();
        }

        if (shopware.isVersionGreaterEqual('6.6.10.9')) {
            // i think in sw 6.7.0.0 there is some kind of bug
            // so that the selected payment method in the next steps is not taken over.
            // let's try with a simple reload after changing the address
            cy.reload();
        }
    }
}
