import Devices from "Services/utils/Devices";
import Session from "Services/utils/Session"
import Shopware from "Services/shopware/Shopware";
// ------------------------------------------------------
import ShopConfigurationAction from "Actions/admin/ShopConfigurationAction";
// ------------------------------------------------------
import CheckoutAction from 'Actions/storefront/checkout/CheckoutAction';
import PaymentScreenAction from "Actions/mollie/PaymentScreenAction";
import PaymentAction from "Actions/storefront/checkout/PaymentAction";
import DummyBasketScenario from "Scenarios/DummyBasketScenario";


const devices = new Devices();
const session = new Session();
const shopware = new Shopware();

const molliePayment = new PaymentScreenAction();
const configAction = new ShopConfigurationAction();
const checkout = new CheckoutAction();
const payment = new PaymentAction();

const scenarioDummyBasket = new DummyBasketScenario(1);


const testDevices = [devices.getFirstDevice()];

const validCardNumber = '3782 822463 10005';


describe('Credit Card Components', () => {

    testDevices.forEach(device => {

        context(devices.getDescription(device), () => {

            beforeEach(() => {
                devices.setDevice(device);
                configAction.setupShop(true, true, false);
                session.resetSessionData();
                session.resetBrowserSession();
            });

            // skip this test until risk management for credit card max amount is set higher by mollie
            it('Successful card payment', () => {

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

                molliePayment.initSandboxCookie();

                molliePayment.selectPaid();

                cy.url().should('include', '/checkout/finish');
                cy.contains('Thank you for your order');
            })

            it('Invalid Card Holder (Empty)', () => {

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

            it('Invalid Card Holder (Invalid Value)', () => {

                setUp();

                payment.fillCreditCardComponents(' ', validCardNumber, '1228', '1234');

                if (shopware.isVersionGreaterEqual(6.4)) {
                    checkout.placeOrderOnConfirm();
                } else {
                    payment.closePaymentsModal();
                }

                cy.wait(1000);

                // if we have a space as invalid card holder name
                // then somehow this error appears.
                // its not consistent, so we just assert for this text
                cy.contains("Failed to submit card data");
            })

            it('Invalid Card Number', () => {

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

            it('Invalid Expiry Date', () => {

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

            it('Invalid CVC Code', () => {

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

            it('Components work on edit order page', () => {

                // We need to test this with Shopware's complete order page.
                // So we disable the Mollie failure mode for this test only
                configAction.setupShop(false, true, false);

                scenarioDummyBasket.execute();

                // we have to use something else than CREDIT CARD
                // why?! because the way how Shopware behaves in the after-order payment process is, that it
                // just does NOTHING when no payment method change happens!!! it just shows "payment method updated" :)
                // but does not process a payment
                payment.switchPaymentMethod('PayPal');

                shopware.prepareDomainChange();
                checkout.placeOrderOnConfirm();

                molliePayment.initSandboxCookie();
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

                molliePayment.initSandboxCookie();

                // verify that our component card is really
                // been used by comparing the last 4 digits
                cy.contains('**** ' + validCardNumber.substr(validCardNumber.length - 4));

                molliePayment.selectPaid();

                cy.url().should('include', '/checkout/finish');
                cy.contains('Thank you for updating your order');
            })

        })
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
