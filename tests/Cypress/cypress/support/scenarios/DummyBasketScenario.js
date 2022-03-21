import TopMenuAction from "Actions/storefront/navigation/TopMenuAction";
import ListingAction from "Actions/storefront/products/ListingAction";
import PDPAction from "Actions/storefront/products/PDPAction";
import CheckoutAction from "Actions/storefront/checkout/CheckoutAction";
import LoginAction from "Actions/storefront/account/LoginAction";
import Session from "Services/utils/Session";
import RegisterAction from "Actions/storefront/account/RegisterAction";


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
     */
    constructor(quantity) {
        this.quantity = quantity;
    }

    /**
     *
     */
    execute() {

        const user_email = "dev@localhost.de";
        const user_pwd = "MollieMollie111";

        cy.visit('/');

        cy.session('register', () => {
            register.doRegister(user_email, user_pwd);
        });

        session.resetBrowserSession();

        login.doLogin(user_email, user_pwd);

        // clear previous items
        checkout.clearCart();

        // just refresh
        cy.visit('/');

        topMenu.clickOnClothing();

        listing.clickOnFirstProduct();

        pdp.addToCart(this.quantity);

        checkout.goToCheckoutInOffCanvas();
    }

}
