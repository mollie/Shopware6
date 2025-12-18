import TopMenuAction from "Actions/storefront/navigation/TopMenuAction";
import ListingAction from "Actions/storefront/products/ListingAction";
import PDPAction from "Actions/storefront/products/PDPAction";
import CheckoutAction from "Actions/storefront/checkout/CheckoutAction";
import LoginAction from "Actions/storefront/account/LoginAction";
import Session from "Services/utils/Session";
import RegisterAction from "Actions/storefront/account/RegisterAction";
import MollieProductsAction from "Actions/storefront/products/MollieProductsAction";

const mollieProductsAction = new MollieProductsAction();
const topMenu = new TopMenuAction();
const listing = new ListingAction();
const pdp = new PDPAction();
const checkout = new CheckoutAction();
const login = new LoginAction();

const session = new Session();

const register = new RegisterAction();


export default class DummyBasketScenario {

    /**
     *
     * @param quantity
     * @param lineItemCount
     */
    constructor(quantity, lineItemCount = 1) {
        this.quantity = quantity;
        this.lineItemCount = lineItemCount;
    }

    /**
     *
     */
    execute() {

        const user_email = 'cypress@mollie.com';
        const user_pwd = 'cypress123';

        cy.visit('/');
        
        session.resetBrowserSession();

        login.doLogin(user_email, user_pwd);

        // clear previous items
        checkout.clearCart();

        // just refresh
        cy.visit('/');


        for (let i = 0; i < this.lineItemCount; i++) {

            mollieProductsAction.openListingRegularProducts();

            listing.clickOnNthProduct(i + 1);

            pdp.addToCart(this.quantity);

            if (i < this.lineItemCount - 1) {
                checkout.closeOffcanvasCart();
            }
        }

        checkout.goToCheckout();
        checkout.changeBillingCountry('Germany');
        checkout.changeToMollieShippingMethod();
    }

}
