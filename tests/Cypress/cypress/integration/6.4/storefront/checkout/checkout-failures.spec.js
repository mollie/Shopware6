import Devices from "Services/Devices";
import Session from "Actions/utils/Session"
import PaymentScreenAction from 'Actions/mollie/PaymentScreenAction';
// ------------------------------------------------------
import ShopConfigurationAction from "Actions/6.4/admin/ShopConfigurationAction";
// ------------------------------------------------------
import TopMenuAction from 'Actions/6.4/storefront/navigation/TopMenuAction';
import LoginAction from 'Actions/6.4/storefront/account/LoginAction';
import RegisterAction from 'Actions/6.4/storefront/account/RegisterAction';
import ListingAction from 'Actions/6.4/storefront/products/ListingAction';
import PDPAction from 'Actions/6.4/storefront/products/PDPAction';
import CheckoutAction from 'Actions/6.4/storefront/checkout/CheckoutAction';


const devices = new Devices();
const session = new Session();

const configAction = new ShopConfigurationAction();

const topMenu = new TopMenuAction();
const register = new RegisterAction();
const login = new LoginAction();
const listing = new ListingAction();
const pdp = new PDPAction();
const checkout = new CheckoutAction();
const molliePayment = new PaymentScreenAction();


const user_email = "dev@localhost.de";
const user_pwd = "MollieMollie111";

const device = devices.getFirstDevice();


context("Checkout Failure Tests", () => {

    before(function () {
        devices.setDevice(device);
        configAction.setupShop();
        register.doRegister(user_email, user_pwd);
    })

    beforeEach(() => {
        session.resetBrowserSession();
        devices.setDevice(device);
    });

    describe('Mollie Failure Mode', () => {
        context(devices.getDescription(device), () => {

            it('Paypal failed and retry with Giropay', () => {

                cy.visit('/');

                login.doLogin(user_email, user_pwd);

                topMenu.clickOnHome();
                listing.clickOnFirstProduct();
                pdp.addToCart(1);
                checkout.goToCheckoutInOffCanvas();

                checkout.switchPaymentMethod('PayPal');
                checkout.placeOrderOnConfirm();

                molliePayment.selectFailed();

                // verify that we are back in our shop
                // if the payment fails, the order is finished but
                // we still have the option to change the payment method
                cy.url().should('include', '/mollie/payment/');
                cy.contains('The payment is failed or was canceled.');

                // click on the mollie plugin retry button
                // which brings us to the mollie payment selection page
                checkout.mollieFailureModeRetryPayment();

                cy.url().should('include', '/payscreen/select-method/');


                // select giropay and mark it as "paid"
                molliePayment.selectGiropay();
                molliePayment.selectPaid();

                cy.url().should('include', '/checkout/finish');
                cy.contains('Thank you for your order');
            })

            it('Paypal failed and continue shopping', () => {

                cy.visit('/');

                login.doLogin(user_email, user_pwd);

                topMenu.clickOnHome();
                listing.clickOnFirstProduct();
                pdp.addToCart(1);
                checkout.goToCheckoutInOffCanvas();

                checkout.switchPaymentMethod('PayPal');
                checkout.placeOrderOnConfirm();

                molliePayment.selectFailed();

                // verify that we are back in our shop
                // if the payment fails, the order is finished but
                // we still have the option to change the payment method
                cy.url().should('include', '/mollie/payment/');
                cy.contains('The payment is failed or was canceled.');


                // click on the continue-shopping button on the failure screen
                // which aborts our checkout and brings us to the home page
                checkout.mollieFailureModeContinueShopping();

                cy.url().should('eq', Cypress.config().baseUrl + '/');
            })

        })
    })

})
