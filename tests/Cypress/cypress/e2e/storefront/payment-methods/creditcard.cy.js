import Devices from "Services/utils/Devices";
import Session from "Services/utils/Session"
import Shopware from "Services/shopware/Shopware";
// ------------------------------------------------------
import ShopConfigurationAction from "Actions/admin/ShopConfigurationAction";
// ------------------------------------------------------
import CheckoutAction from 'Actions/storefront/checkout/CheckoutAction';
import DummyBasketScenario from "Scenarios/DummyBasketScenario";
import PaymentAction from "Actions/storefront/checkout/PaymentAction";
// ------------------------------------------------------
import MollieSandbox from "cypress-mollie/src/actions/MollieSandbox";
import CreditCardScreenAction from "cypress-mollie/src/actions/screens/CreditCardScreen";
import PaymentScreenAction from "cypress-mollie/src/actions/screens/PaymentStatusScreen";
import AdminLoginAction from "Actions/admin/AdminLoginAction";
import AdminOrdersAction from "Actions/admin/AdminOrdersAction";


const devices = new Devices();
const session = new Session();
const shopware = new Shopware();

const mollieSandbox = new MollieSandbox();
const molliePayment = new PaymentScreenAction();
const mollieCreditCardForm = new CreditCardScreenAction();

const configAction = new ShopConfigurationAction();
const checkout = new CheckoutAction();
const payment = new PaymentAction();

const adminLogin = new AdminLoginAction();
const adminOrders = new AdminOrdersAction();

const scenarioDummyBasket = new DummyBasketScenario(1);


const testDevices = [devices.getFirstDevice()];

const validCardNumber = '3782 822463 10005';


describe('Credit Card Components', () => {

    before(function () {
        devices.setDevice(devices.getFirstDevice());
        // we need the Shopware failure mode for some tests in this file
        // so let's just do this here once
        configAction.setupShop(false, true, false);
        configAction.updateProducts('', false, 0, '');
    })

    beforeEach(() => {
        devices.setDevice(devices.getFirstDevice());
        session.resetSessionData();
        session.resetBrowserSession();
    });

    context(devices.getDescription(devices.getFirstDevice()), () => {

        it('C4102: Successful card payment', () => {

            setUp();

            payment.fillCreditCardComponents('Mollie Tester', validCardNumber, '1228', '1234');

            // we are still in our modal, so we
            // have to close it in older versions
            if (shopware.isVersionLower(6.4)) {
                payment.closePaymentsModal();
            }

            shopware.prepareDomainChange();
            checkout.placeOrderOnConfirm();

            cy.url().should('include', 'https://www.mollie.com/checkout/');

            // verify that our component card is really
            // been used by comparing the last 4 digits
            cy.contains('**** ' + validCardNumber.substr(validCardNumber.length - 4));

            mollieSandbox.initSandboxCookie();

            molliePayment.selectPaid();

            cy.url().should('include', '/checkout/finish');
            cy.contains('Thank you for your order');
        })

        it('C4105: Invalid Card Holder (Empty)', () => {

            setUp();

            payment.fillCreditCardComponents('', validCardNumber, '1228', '1234');

            if (shopware.isVersionGreaterEqual(6.4)) {
                checkout.placeOrderOnConfirm();
            } else {
                payment.closePaymentsModal();
            }

            cy.wait(1000);

            assertComponentErrors(false, true, true, true);
        })

        it('C4107: Invalid Card Holder (Invalid Value)', () => {

            setUp();

            payment.fillCreditCardComponents(' ', validCardNumber, '1228', '1234');

            if (shopware.isVersionGreaterEqual(6.4)) {
                checkout.placeOrderOnConfirm();
            } else {
                payment.closePaymentsModal();
            }

            cy.wait(1200);

            cy.contains("The 'cardHolder' field should not contain only whitespace characters");
        })

        it('C4108: Invalid Card Number', () => {

            setUp();

            payment.fillCreditCardComponents('Mollie Tester', '3782', '1228', '1234');

            if (shopware.isVersionGreaterEqual(6.4)) {
                checkout.placeOrderOnConfirm();
            } else {
                payment.closePaymentsModal();
            }

            cy.wait(1000);

            assertComponentErrors(true, false, true, true);
        })

        it('C4109: Invalid Expiry Date', () => {

            setUp();

            payment.fillCreditCardComponents('Mollie Tester', validCardNumber, '12', '1234');

            if (shopware.isVersionGreaterEqual(6.4)) {
                checkout.placeOrderOnConfirm();
            } else {
                payment.closePaymentsModal();
            }

            cy.wait(1000);

            assertComponentErrors(true, true, false, true);
        })

        it('C4110: Invalid CVC Code', () => {

            setUp();

            payment.fillCreditCardComponents('Mollie Tester', validCardNumber, '1228', '124');

            if (shopware.isVersionGreaterEqual(6.4)) {
                checkout.placeOrderOnConfirm();
            } else {
                payment.closePaymentsModal();
            }

            cy.wait(1000);

            assertComponentErrors(true, true, true, false);
        })

        it('C4106: Components work on edit order page', () => {

            scenarioDummyBasket.execute();

            // we have to use something else than CREDIT CARD
            // why?! because the way how Shopware behaves in the after-order payment process is, that it
            // just does NOTHING when no payment method change happens!!! it just shows "payment method updated" :)
            // but does not process a payment
            payment.switchPaymentMethod('PayPal');

            shopware.prepareDomainChange();
            checkout.placeOrderOnConfirm();

            mollieSandbox.initSandboxCookie();
            molliePayment.selectFailed();

            cy.url().should('include', '/account/order/edit');
            cy.contains('Complete payment');

            if (shopware.isVersionGreaterEqual(6.4)) {

                payment.showAllPaymentMethods();
                payment.selectPaymentMethod('Credit card');
                payment.showAllPaymentMethods();

                payment.fillCreditCardComponents('Mollie Tester', validCardNumber, '1228', '1234');

            } else {

                payment.openPaymentsModal();
                payment.selectPaymentMethod('Credit card');

                payment.fillCreditCardComponents('Mollie Tester', validCardNumber, '1228', '1234');

                payment.closePaymentsModal();
            }

            shopware.prepareDomainChange();

            checkout.placeOrderOnEdit();

            // verify that our component card is really
            // been used by comparing the last 4 digits
            cy.contains('**** ' + validCardNumber.substr(validCardNumber.length - 4));

            molliePayment.selectPaid();

            cy.url().should('include', '/checkout/finish');
            cy.contains('Thank you for updating your order');
        })

    })
})

describe('Status Tests', () => {

    before(function () {
        devices.setDevice(devices.getFirstDevice());
        // turn off credit card components
        // to speed up a few  things
        configAction.setupPlugin(false, false, false, false);

    })

    beforeEach(() => {
        devices.setDevice(devices.getFirstDevice());
        session.resetSessionData();
        session.resetBrowserSession();
    });

    it('C4266: Open Credit Card payment leads to failure', () => {

        setUp();

        // we are still in our modal, so we
        // have to close it in older versions
        if (shopware.isVersionLower(6.4)) {
            payment.closePaymentsModal();
        }

        shopware.prepareDomainChange();
        checkout.placeOrderOnConfirm();

        mollieSandbox.initSandboxCookie();

        mollieCreditCardForm.enterValidCard();
        mollieCreditCardForm.submitForm();

        molliePayment.selectOpen();

        cy.url().should('include', '/account/order/edit');
        cy.contains('Complete payment');
    })

})


describe('Administration Tests', () => {

    before(function () {
        devices.setDevice(devices.getFirstDevice());
        configAction.setupPlugin(false, false, false, false);
    })

    beforeEach(() => {
        devices.setDevice(devices.getFirstDevice());
        session.resetSessionData();
        session.resetBrowserSession();
    });

    it('C5520: Credit Card Data is shown in the Administration', () => {

        setUp();

        // we are still in our modal, so we
        // have to close it in older versions
        if (shopware.isVersionLower(6.4)) {
            payment.closePaymentsModal();
        }

        shopware.prepareDomainChange();
        checkout.placeOrderOnConfirm();

        mollieSandbox.initSandboxCookie();

        mollieCreditCardForm.enterValidCard();
        mollieCreditCardForm.submitForm();

        molliePayment.selectPaid();

        adminLogin.login();
        adminOrders.openOrders();
        adminOrders.openLastOrder();

        // our Mollie Sandbox data needs to be visible on our page
        // that's the only thing we can verify for now, but speaking of the data
        // the assertion should be very accurate and unique on our page.
        cy.contains('Credit Card Data');
        cy.contains('Mastercard');
        cy.contains('**** **** **** 0005');
        cy.contains('T. TEST');
    })

})


/**
 * Setup the whole test until we reach
 * our credit card components.
 */
function setUp() {

    scenarioDummyBasket.execute();

    // credit card components are not available
    // if already selected when being opened.
    // this is a bug, so we just switch to another payment
    // before switching back to credit card
    payment.switchPaymentMethod('PayPal');

    if (shopware.isVersionGreaterEqual(6.4)) {
        payment.switchPaymentMethod('Credit card');
    } else {
        payment.openPaymentsModal();
        // only select the card, and do not switch completely
        // we still need our modal, to add our components data
        // before closing it.
        payment.selectPaymentMethod('Credit card');
    }
}

/**
 *
 * @param cardNameValid
 * @param cardNumberValid
 * @param expiryDateValid
 * @param cvcValid
 */
function assertComponentErrors(cardNameValid, cardNumberValid, expiryDateValid, cvcValid) {
    if (cardNameValid) {
        cy.get('#cardHolder').should('not.have.class', 'error');
    } else {
        cy.get('#cardHolder').should('have.class', 'error');
    }

    if (cardNumberValid) {
        cy.get('#cardNumber').should('not.have.class', 'error');
    } else {
        cy.get('#cardNumber').should('have.class', 'error');
    }

    if (expiryDateValid) {
        cy.get('#expiryDate').should('not.have.class', 'error');
    } else {
        cy.get('#expiryDate').should('have.class', 'error');
    }

    if (cvcValid) {
        cy.get('#verificationCode').should('not.have.class', 'error');
    } else {
        cy.get('#verificationCode').should('have.class', 'error');
    }
}
