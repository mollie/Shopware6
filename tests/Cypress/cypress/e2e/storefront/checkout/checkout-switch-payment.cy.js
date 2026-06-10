import Devices from "Services/utils/Devices";
import Session from "Services/utils/Session"
import Shopware from "Services/shopware/Shopware"
// ------------------------------------------------------
import ShopConfigurationAction from "Actions/admin/ShopConfigurationAction";
// ------------------------------------------------------
import CheckoutAction from 'Actions/storefront/checkout/CheckoutAction';
import PaymentAction from "Actions/storefront/checkout/PaymentAction";
import DummyBasketScenario from "Scenarios/DummyBasketScenario";
// ------------------------------------------------------
import MollieSandbox from "cypress-mollie/src/actions/MollieSandbox";
import PaymentStatusScreen from "cypress-mollie/src/actions/screens/PaymentStatusScreen";
import ShopConfiguration from "../../../support/models/ShopConfiguration";
import PluginConfiguration from "../../../support/models/PluginConfiguration";


const devices = new Devices();
const session = new Session();
const shopware = new Shopware();

const configAction = new ShopConfigurationAction();
const checkout = new CheckoutAction();
const paymentAction = new PaymentAction();
const mollieSandbox = new MollieSandbox();
const molliePayment = new PaymentStatusScreen();

const scenarioDummyBasket = new DummyBasketScenario(1);


const device = devices.getFirstDevice();

let beforeAllCalled = false;

function beforeEachSetup(device) {
    cy.wrap(null).then(() => {
        if (!beforeAllCalled) {

            const shopConfig = new ShopConfiguration();
            const pluginConfig = new PluginConfiguration();

            pluginConfig.setMollieFailureMode(false);

            configAction.configureEnvironment(shopConfig, pluginConfig);

            beforeAllCalled = true;
        }
        session.resetBrowserSession();
        devices.setDevice(device);
    });
}


context.skip("No Pending Order Redirect After Failed Payment", () => {

    context(devices.getDescription(device), () => {

        it('Account orders page is not redirected after a failed payment return', () => {

            beforeEachSetup(device);

            scenarioDummyBasket.execute();
            paymentAction.switchPaymentMethod('PayPal');

            shopware.prepareDomainChange();
            checkout.placeOrderOnConfirm();

            cy.url().should('include', 'https://www.mollie.com/checkout/');

            // fail the payment — Mollie redirects back via /mollie/payment/{transactionId}
            // which must clear the pending-order session key (regression for #1258)
            mollieSandbox.initSandboxCookie();
            molliePayment.selectFailed();

            // after a failed payment Shopware lands on /account/order/edit
            // navigate away and then back to /account/order to verify no unwanted redirect occurs
            cy.visit('/account/order');
            cy.url().should('not.include', '/account/order/edit/');
            cy.url().should('include', '/account/order');
        })

    })

})

context.skip("Switch Payment Method After Browser Back", () => {

    context(devices.getDescription(device), () => {

        it('Switch payment method after browser back from Mollie', () => {

            beforeEachSetup(device);

            scenarioDummyBasket.execute();
            paymentAction.switchPaymentMethod('Klarna');

            shopware.prepareDomainChange();
            checkout.placeOrderOnConfirm();

            // verify we are on the Mollie checkout page with Klarna
            cy.url().should('include', 'https://www.mollie.com/checkout/');
            cy.url().should('include', 'klarna');

            // browser back from Mollie
            // the plugin stores a session redirect so that /account/order
            // redirects to the edit order page automatically
            cy.go('back');

            cy.url().should('include', '/account/order/edit/', {timeout: 15000});

            // switch payment method to PayPal
            paymentAction.switchPaymentMethod('PayPal');

            shopware.prepareDomainChange();
            checkout.placeOrderOnEdit();

            // verify we are on the Mollie checkout page with PayPal (not Klarna)
            cy.url().should('include', 'https://www.mollie.com/checkout/');
            cy.url().should('include', 'paypal');

            // browser back from Mollie again
            // since we came from edit order page, browser back lands there directly
            cy.go('back');
            cy.url().should('include', '/account/order/edit/', {timeout: 15000});
            // Cypress does not support bfcache, so the pageshow reload does not trigger.
            // In real browsers the page reloads automatically. We simulate this here.
            cy.reload();

            // switch payment method back to Klarna
            paymentAction.switchPaymentMethod('Klarna');

            shopware.prepareDomainChange();
            checkout.placeOrderOnEdit();

            // verify we are on the Mollie checkout page with Klarna (not PayPal)
            cy.url().should('include', 'https://www.mollie.com/checkout/');
            cy.url().should('include', 'klarna');

            // complete the payment
            mollieSandbox.initSandboxCookie();
            molliePayment.selectAuthorized();

            cy.url().should('include', '/checkout/finish');
            cy.contains('Thank you');
        })

    })

})