import Devices from "Services/utils/Devices";
import Session from "Services/utils/Session";
import Shopware from "Services/shopware/Shopware";
// ------------------------------------------------------
import ShopConfigurationAction from "Actions/admin/ShopConfigurationAction";
import AdminLoginAction from "Actions/admin/AdminLoginAction";
import AdminCreateOrderAction from "Actions/admin/AdminCreateOrderAction";
import AdminOrdersAction from "Actions/admin/AdminOrdersAction";
// ------------------------------------------------------
import ShopConfiguration from "../../../support/models/ShopConfiguration";
import PluginConfiguration from "../../../support/models/PluginConfiguration";
import CreditCardScreen from "../../../support/actions/mollie/screens/CreditCartScreen";
// ------------------------------------------------------
import MollieSandbox from "cypress-mollie/src/actions/MollieSandbox";
import PaymentStatusScreen from "cypress-mollie/src/actions/screens/PaymentStatusScreen";
import PaymentListScreen from "cypress-mollie/src/actions/screens/PaymentListScreen";


const devices = new Devices();
const session = new Session();
const shopware = new Shopware();

const configAction = new ShopConfigurationAction();
const adminLogin = new AdminLoginAction();
const createOrder = new AdminCreateOrderAction();
const adminOrders = new AdminOrdersAction();

const mollieSandbox = new MollieSandbox();
const molliePayment = new PaymentStatusScreen();
const molliePaymentMethods = new PaymentListScreen();
const mollieCreditCard = new CreditCardScreen();

const device = devices.getFirstDevice();


function beforeEach(device) {
    cy.wrap(null).then(() => {
        session.resetBrowserSession();
        devices.setDevice(device);
    });
}

/**
 * Configures the plugin for a payment link test. The method-selection flag decides whether the
 * customer may pick a different payment method on the Mollie page or is bound to the order's method.
 * configureEnvironment also assigns every payment method to every sales channel, so credit card is
 * offered on the Mollie payment link page.
 */
function configurePaymentLink(allowMethodSelection) {
    const shopConfig = new ShopConfiguration();
    const pluginConfig = new PluginConfiguration();

    pluginConfig.setMollieFailureMode(false);
    pluginConfig.setPaymentLinkMethodSelection(allowMethodSelection);

    // When the customer may choose the method, the link sends allowedMethods with every method
    // available for the order. Mollie rejects any that is not active in the profile, so enable the
    // availability rules to strip those before they end up in allowedMethods.
    pluginConfig.setUseMolliePaymentMethodLimits(allowMethodSelection);

    configAction.configureEnvironment(shopConfig, pluginConfig);
}

/**
 * Opens the pay URL a merchant would put into the confirmation mail. The controller answers with a
 * 302 to the payment link page on the Mollie domain; cy.visit() cannot target a cross-origin
 * redirect directly, so we keep the shop session alive and trigger it as an in-app navigation.
 */
function openPaymentLink(orderId) {
    shopware.prepareDomainChange();

    cy.window().then((win) => {
        win.location.href = '/mollie/pay/' + orderId;
    });

    cy.url({timeout: 30000}).should('include', 'mollie.com');
}


context("Payment Link", () => {

    context(devices.getDescription(device), () => {

        it('pay an existing order via a payment link @core', () => {

            beforeEach(device);

            // method selection off: the customer pays with the order's payment method
            configurePaymentLink(false);

            adminLogin.login();

            // create an order for the customer the same way an admin would
            createOrder.createOrder('cypress@mollie.com', 'MOL_REGULAR', 'PayPal', 'Mollie Test Shipment').then((orderId) => {

                openPaymentLink(orderId);

                // pay with PayPal on the payment link page
                molliePaymentMethods.selectPaypal();

                mollieSandbox.initSandboxCookie();
                molliePayment.selectPaid();

                // back in the shop on the success page
                cy.url({timeout: 30000}).should('include', '/checkout/finish');
                cy.contains('Thank you for your order');

                // finalize ran through: the order is paid. We are still logged into the admin from
                // creating the order, so we can go straight to the order list.
                adminOrders.assertLatestPaymentStatus('Paid');
            });
        });

        it.only('pay a payment link with a different method (credit card) chosen on Mollie @core', () => {

            beforeEach(device);

            // method selection on: the customer may switch the payment method on the Mollie page
            configurePaymentLink(true);

            adminLogin.login();

            // the order starts on PayPal, but the customer will switch to credit card on Mollie
            createOrder.createOrder('cypress@mollie.com', 'MOL_REGULAR', 'PayPal', 'Mollie Test Shipment').then((orderId) => {

                openPaymentLink(orderId);

                // pick credit card instead of the order's PayPal and pay with a valid test card
                molliePaymentMethods.selectCreditCard();

                mollieSandbox.initSandboxCookie();
                mollieCreditCard.enterValidCard();
                mollieCreditCard.submitForm();
                molliePayment.selectPaid();

                // back in the shop on the success page
                cy.url({timeout: 30000}).should('include', '/checkout/finish');
                cy.contains('Thank you for your order');

                // finalize ran through for the method chosen on Mollie: the order is paid. We are
                // still logged into the admin from creating the order. The card details rendering in
                // the Mollie tab is already covered by the credit card spec.
                adminOrders.assertLatestPaymentStatus('Paid');
            });
        });

    });

});
