import ListingAction from "Actions/storefront/products/ListingAction";
import PDPAction from "Actions/storefront/products/PDPAction";
import CheckoutAction from "Actions/storefront/checkout/CheckoutAction";
import LoginAction from "Actions/storefront/account/LoginAction";
import MollieProductsAction from "Actions/storefront/products/MollieProductsAction";

const mollieProductsAction = new MollieProductsAction();
const listing = new ListingAction();
const pdp = new PDPAction();
const checkout = new CheckoutAction();
const login = new LoginAction();


export default class DummyBasketScenario {

    /**
     * @param quantity
     * @param lineItemCount
     */
    constructor(quantity, lineItemCount = 1) {
        this.quantity = quantity;
        this.lineItemCount = lineItemCount;
    }

    execute() {
        const user_email = 'cypress@mollie.com';
        const user_pwd = 'cypress123';

        cy.clearAllCookies();
        cy.clearAllLocalStorage();
        cy.clearAllSessionStorage();

        login.doLogin(user_email, user_pwd);

        cy.visit('/');
        checkout.clearCart();

        for (let i = 0; i < this.lineItemCount; i++) {

            mollieProductsAction.openListingRegularProducts();

            listing.clickOnNthProduct(i + 1);

            pdp.addToCart(this.quantity);

            if (i < this.lineItemCount - 1) {
                checkout.closeOffcanvasCart();
            }
        }

        checkout.goToCheckout();
        cy.url().should('include', '/checkout/confirm').then(function (url) {
            cy.log('Checkout reached - current URL: ' + url);
        });
        checkout.changeBillingCountry('Germany');
        checkout.changeToMollieShippingMethod();
    }

}
