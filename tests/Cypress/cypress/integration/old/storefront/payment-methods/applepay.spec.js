import Devices from "Services/Devices";
import Session from "Actions/utils/Session"
// ------------------------------------------------------
import ShopConfigurationAction from "Actions/old/admin/ShopConfigurationAction";
// ------------------------------------------------------
import TopMenuAction from 'Actions/old/storefront/navigation/TopMenuAction';
import LoginAction from 'Actions/old/storefront/account/LoginAction';
import RegisterAction from 'Actions/old/storefront/account/RegisterAction';
import ListingAction from 'Actions/old/storefront/products/ListingAction';
import PDPAction from 'Actions/old/storefront/products/PDPAction';
import CheckoutAction from 'Actions/old/storefront/checkout/CheckoutAction';


const devices = new Devices();
const session = new Session();

const configAction = new ShopConfigurationAction();

const topMenu = new TopMenuAction();
const register = new RegisterAction();
const login = new LoginAction();
const listing = new ListingAction();
const pdp = new PDPAction();
const checkout = new CheckoutAction();


const user_email = "dev@localhost.de";
const user_pwd = "MollieMollie111";

const device = devices.getFirstDevice();


context("Apple Pay", () => {

    before(function () {

        devices.setDevice(device);

        configAction.setupShop(true);

        register.doRegister(user_email, user_pwd);
    })

    beforeEach(() => {
        session.resetBrowserSession();
        devices.setDevice(device);
    });


    it('Apple Pay hidden if not available in browser', () => {

        cy.visit('/');

        login.doLogin(user_email, user_pwd);

        topMenu.clickOnHome();
        listing.clickOnFirstProduct();
        pdp.addToCart(1);

        checkout.goToCheckoutInOffCanvas();

        checkout.openPaymentSelectionOnConfirm();

        // wait a bit, because the client side
        // code for the ApplePay recognition needs to
        // be executed first
        cy.wait(2000);

        cy.contains('Apple Pay').should('not.exist');
    })

})
