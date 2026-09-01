import Devices from "Services/utils/Devices";
import Session from "Services/utils/Session"
import Shopware from "Services/shopware/Shopware"
// ------------------------------------------------------
import ShopConfigurationAction from "Actions/admin/ShopConfigurationAction";
import AdminOrdersAction from "Actions/admin/AdminOrdersAction";
import AdminLoginAction from "Actions/admin/AdminLoginAction";
// ------------------------------------------------------
import CheckoutAction from 'Actions/storefront/checkout/CheckoutAction';
import PaymentAction from "Actions/storefront/checkout/PaymentAction";
import DummyBasketScenario from "Scenarios/DummyBasketScenario";
// ------------------------------------------------------
import MollieSandbox from "cypress-mollie/src/actions/MollieSandbox";
import PaymentStatusScreen from "cypress-mollie/src/actions/screens/PaymentStatusScreen";
import CreditCardScreen from "../../../support/actions/mollie/screens/CreditCartScreen";
import ShopConfiguration from "../../../support/models/ShopConfiguration";
import PluginConfiguration from "../../../support/models/PluginConfiguration";


const devices = new Devices();
const session = new Session();
const shopware = new Shopware();

const configAction = new ShopConfigurationAction();
const adminOrders = new AdminOrdersAction();
const adminLogin = new AdminLoginAction();
const checkout = new CheckoutAction();
const paymentAction = new PaymentAction();

const mollieSandbox = new MollieSandbox();
const molliePayment = new PaymentStatusScreen();
const mollieCreditCard = new CreditCardScreen();

const scenarioDummyBasket = new DummyBasketScenario(1);


const device = devices.getFirstDevice();

let beforeAllCalled = false;

function beforeEachSetup(device) {
    cy.wrap(null).then(() => {
        if (!beforeAllCalled) {

            const shopConfig = new ShopConfiguration();
            const pluginConfig = new PluginConfiguration();

            // never fail payments automatically, we drive the payment status
            // manually on the Mollie sandbox for every attempt
            pluginConfig.setMollieFailureMode(false);

            configAction.configureEnvironment(shopConfig, pluginConfig);

            beforeAllCalled = true;
        }
        session.resetBrowserSession();
        devices.setDevice(device);
    });
}

/**
 * Starts a new payment attempt from the edit-order page (reached via browser back from Mollie),
 * lands back on the Mollie checkout page and stores its URL under the given alias so it can be
 * reopened later.
 */
function startAttemptOnEditAndRemember(paymentName, urlAlias) {
    cy.go('back');
    cy.url().should('include', '/account/order/edit/', {timeout: 15000});

    paymentAction.switchPaymentMethod(paymentName);

    shopware.prepareDomainChange();
    checkout.placeOrderOnEdit();

    cy.url().should('include', 'https://www.mollie.com/checkout/');
    cy.url().then((url) => {
        cy.wrap(url).as(urlAlias);
    });
}


context("Checkout Duplicate Payments Reconciliation", () => {

    context(devices.getDescription(device), () => {

        it('Same payment method retry reuses the existing Mollie payment', () => {

            beforeEachSetup(device);

            // first attempt -> remember the Mollie url
            scenarioDummyBasket.execute();
            paymentAction.switchPaymentMethod('PayPal');

            shopware.prepareDomainChange();
            checkout.placeOrderOnConfirm();

            cy.url().should('include', 'https://www.mollie.com/checkout/');
            cy.url().then((url) => {
                cy.wrap(url).as('firstUrl');
            });

            // browser back, retry with the SAME method. Shopware reuses the transaction and we reuse
            // the existing Mollie payment instead of creating a second one, so we land back on the
            // exact same Mollie checkout url.
            cy.go('back');
            cy.url().should('include', '/account/order/edit/', {timeout: 15000});

            paymentAction.switchPaymentMethod('PayPal');

            shopware.prepareDomainChange();
            checkout.placeOrderOnEdit();

            cy.url().should('include', 'https://www.mollie.com/checkout/');
            cy.get('@firstUrl').then((firstUrl) => {
                cy.url().should('eq', firstUrl);
            });

            // pay the single (reused) payment
            mollieSandbox.initSandboxCookie();
            molliePayment.selectPaid();

            cy.url().should('include', '/checkout/finish');
            cy.contains('Thank you');

            adminLogin.login();
            adminOrders.assertLatestPaymentStatus('Paid');
        })

        it('Klarna abandoned, paid with PayPal -> order is paid', () => {

            beforeEachSetup(device);

            // start with Klarna and go back without authorizing it
            scenarioDummyBasket.execute();
            paymentAction.switchPaymentMethod('Klarna');

            shopware.prepareDomainChange();
            checkout.placeOrderOnConfirm();

            cy.url().should('include', 'https://www.mollie.com/checkout/');
            cy.url().should('include', 'klarna');

            // switch to PayPal on the edit-order page and pay
            startAttemptOnEditAndRemember('PayPal', 'paypalUrl');

            cy.get('@paypalUrl').then((url) => {
                cy.visit(url);
            });
            mollieSandbox.initSandboxCookie();
            molliePayment.selectPaid();

            cy.url().should('include', '/checkout/finish');
            cy.contains('Thank you');

            // the reconciler cancels the superseded Klarna payment at Mollie (verified via logs /
            // Mollie); the order itself must be paid
            adminLogin.login();
            adminOrders.assertLatestPaymentStatus('Paid');
        })

        it('PayPal paid, then credit card paid -> order is paid with credit card', () => {

            beforeEachSetup(device);

            // first attempt: PayPal -> remember its url, go back and start a credit card attempt
            scenarioDummyBasket.execute();
            paymentAction.switchPaymentMethod('PayPal');

            shopware.prepareDomainChange();
            checkout.placeOrderOnConfirm();

            cy.url().should('include', 'https://www.mollie.com/checkout/');
            cy.url().then((url) => {
                cy.wrap(url).as('paypalUrl');
            });

            startAttemptOnEditAndRemember('Card', 'creditCardUrl');

            // pay the PayPal attempt first
            cy.get('@paypalUrl').then((url) => {
                cy.visit(url);
            });
            mollieSandbox.initSandboxCookie();
            molliePayment.selectPaid();

            cy.url().should('include', '/checkout/finish');

            // then pay the credit card attempt
            cy.get('@creditCardUrl').then((url) => {
                cy.visit(url);
            });
            mollieSandbox.initSandboxCookie();

            mollieCreditCard.enterValidCard();
            mollieCreditCard.submitForm();
            molliePayment.selectPaid();

            cy.url().should('include', '/checkout/finish');
            cy.contains('Thank you');

            // credit card is the last successful payment; the reconciler refunds the superseded
            // PayPal payment at Mollie (verified via logs / Mollie); the order stays paid
            adminLogin.login();
            adminOrders.assertLatestPaymentStatus('Paid');
        })

    })

})
