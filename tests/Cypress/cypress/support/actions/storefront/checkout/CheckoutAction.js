import OffCanvasRepository from 'Repositories/storefront/checkout/OffCanvasRepository';
import ConfirmRepository from 'Repositories/storefront/checkout/ConfirmRepository';
import TopMenuAction from "Actions/storefront/navigation/TopMenuAction";
import ListingAction from "Actions/storefront/products/ListingAction";
import PDPAction from "Actions/storefront/products/PDPAction";

const repoOffCanvas = new OffCanvasRepository();
const repoConfirm = new ConfirmRepository();


const topMenu = new TopMenuAction();
const listing = new ListingAction();
const pdp = new PDPAction();
import Shopware from "Services/shopware/Shopware";

const shopware = new Shopware();

export default class CheckoutAction {


    /**
     *
     */
    clearCart() {
        // open off canvas
        cy.get('.header-cart-total').click({force: true});

        const btnRemoveItem = '.cart-item-remove > .btn';

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
        repoOffCanvas.getCartButton().click();
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

    changeBillingCountry(billingCountry){

        cy.get('.js-confirm-overview-addresses .card:eq(0) .card-actions a[data-address-editor]').click();
        cy.wait(2000);
        cy.get('.address-editor-edit').click();
        cy.wait(1000);
        cy.get('select.country-select:eq(0)').select(billingCountry);
        cy.get('.address-form-actions:eq(0) button').click();

        //since 6.6.8.0 the edit address modal does not close automatically
        if(shopware.isVersionGreaterEqual('6.6.8.0') && shopware.isVersionLower('6.6.9.0')){
            cy.wait(1000);
            cy.get('.js-pseudo-modal .modal-dialog .btn-close').click();
        }

    }
}
