import Devices from "Services/Devices";
import Session from "Actions/utils/Session"
import PaymentScreenAction from 'Actions/mollie/PaymentScreenAction';
import IssuerScreenAction from 'Actions/mollie/IssuerScreenAction';
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
const mollieIssuer = new IssuerScreenAction();


const user_email = "dev@localhost.de";
const user_pwd = "MollieMollie111";

const device = devices.getFirstDevice();


const payments = [
    {key: 'paypal', name: 'PayPal'},
    {key: 'klarnapaylater', name: 'Pay later'},
    {key: 'klarnasliceit', name: 'Slice it'},
    {key: 'ideal', name: 'iDEAL'},
    {key: 'sofort', name: 'SOFORT'},
    {key: 'eps', name: 'eps'},
    {key: 'giropay', name: 'Giropay'},
    {key: 'mistercash', name: 'Bancontact'},
    {key: 'przelewy24', name: 'Przelewy24'},
    {key: 'kbc', name: 'KBC'},
    {key: 'belfius', name: 'Belfius'},
    {key: 'banktransfer', name: 'Banktransfer'},
];


context("Checkout Tests", () => {

    before(function () {
        devices.setDevice(device);
        configAction.setupShop(true);
        register.doRegister(user_email, user_pwd);
    })

    beforeEach(() => {
        session.resetBrowserSession();
        devices.setDevice(device);
    });

    describe('Successful Checkout', () => {
        context(devices.getDescription(device), () => {
            payments.forEach(payment => {

                it('Pay with ' + payment.name, () => {

                    cy.visit('/');

                    login.doLogin(user_email, user_pwd);

                    topMenu.clickOnHome();
                    listing.clickOnFirstProduct();
                    pdp.addToCart(3);

                    checkout.goToCheckoutInOffCanvas();

                    checkout.switchPaymentMethod(payment.name);

                    let totalSum = 0;
                    // grab the total sum of our order from the confirm page.
                    // we also want to test what the user has to pay in Mollie.
                    // this has to match!
                    checkout.getTotalFromConfirm().then(total => {
                        cy.log("Cart Total: " + total);
                        totalSum = total;
                    });

                    checkout.placeOrderOnConfirm();

                    // verify that we are on the mollie payment screen
                    // and that our payment method is also visible somewhere in that url
                    cy.url().should('include', 'https://www.mollie.com/paymentscreen/');
                    cy.url().should('include', payment.key);
                    cy.get('.header__amount').contains(totalSum);


                    if (payment.key === 'klarnapaylater' || payment.key === 'klarnasliceit') {

                        molliePayment.selectAuthorized();

                    } else {

                        if (payment.key === 'kbc') {
                            mollieIssuer.selectKBC();
                        }

                        molliePayment.selectPaid();
                    }

                    // we should now get back to the shop
                    // with a successful order message
                    cy.url().should('include', '/checkout/finish');
                    cy.contains('Thank you for your order');
                })

            })
        })
    })

})
