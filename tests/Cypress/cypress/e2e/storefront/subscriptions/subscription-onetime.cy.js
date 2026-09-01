import Devices from "Services/utils/Devices";
import Session from "Services/utils/Session"
import Shopware from "Services/shopware/Shopware"
// ------------------------------------------------------
import ShopConfigurationAction from "Actions/admin/ShopConfigurationAction";
import AdminLoginAction from "Actions/admin/AdminLoginAction";
import AdminOrdersAction from "Actions/admin/AdminOrdersAction";
import OrderDetailsRepository from "Repositories/admin/orders/OrderDetailsRepository";
import CheckoutAction from "Actions/storefront/checkout/CheckoutAction";
import PaymentAction from "Actions/storefront/checkout/PaymentAction";
import PDPAction from "Actions/storefront/products/PDPAction";
import MollieProductsAction from "Actions/storefront/products/MollieProductsAction";
import DummyUserScenario from "Scenarios/DummyUserScenario";
// ------------------------------------------------------
import PaymentScreenAction from "cypress-mollie/src/actions/screens/PaymentStatusScreen";
import MollieSandbox from "cypress-mollie/src/actions/MollieSandbox";
import CreditCardScreenAction from "../../../support/actions/mollie/screens/CreditCartScreen";
import ShopConfiguration from "../../../support/models/ShopConfiguration";
import PluginConfiguration from "../../../support/models/PluginConfiguration";


const devices = new Devices();
const session = new Session();
const shopware = new Shopware();

const configAction = new ShopConfigurationAction();
const adminLogin = new AdminLoginAction();
const adminOrders = new AdminOrdersAction();
const repoOrdersDetails = new OrderDetailsRepository();
const checkout = new CheckoutAction();
const paymentAction = new PaymentAction();
const pdp = new PDPAction();
const mollieProductsAction = new MollieProductsAction();
const dummyUserScenario = new DummyUserScenario();

const molliePayment = new PaymentScreenAction();
const mollieSandbox = new MollieSandbox();
const mollieCreditCardForm = new CreditCardScreenAction();

const testDevices = [devices.getFirstDevice()];

const SUBSCRIBE_BUTTON = '[data-mollie-subscribe-button]';

let beforeAllCalled = false;

function beforeEach(device) {
    cy.wrap(null).then(() => {
        if (!beforeAllCalled) {
            const shopConfig = new ShopConfiguration();
            const pluginConfig = new PluginConfiguration();
            pluginConfig.setSubscriptionIndicator(true);

            configAction.configureEnvironment(shopConfig, pluginConfig);

            beforeAllCalled = true;
        }
        devices.setDevice(device);
        session.resetBrowserSession();
    });
}


describe('Subscription one-time purchase', () => {

    testDevices.forEach(device => {

        context(devices.getDescription(device), () => {

            it('buying the same product as subscription and as one-off keeps two separate cart items and creates exactly one subscription', () => {

                beforeEach(device);

                startWithEmptyCart();

                mollieProductsAction.openSubscriptionProductDailyOnetime();

                // a product that allows one-time purchase must offer BOTH buttons
                cy.get('.btn-buy').should('exist');
                cy.get(SUBSCRIBE_BUTTON).should('be.visible');

                // add it once as a subscription (storefront JS injects the mollieSubscribe flag)
                cy.get(SUBSCRIBE_BUTTON).click();

                // add the same product once as a normal one-off
                mollieProductsAction.openSubscriptionProductDailyOnetime();
                pdp.addToCart(1);

                // the cart must keep them as two distinct line items of the same product
                cy.visit('/checkout/cart');
                cy.get('.line-item-product').should('have.length', 2);

                payWithCard();

                cy.url().should('include', '/checkout/finish');

                // the order created a subscription (only the subscription line, not the one-off)
                openLastOrderMollieTab();
                repoOrdersDetails.getSubscriptionBadge().should('exist');
            })

            it('buying the product as a normal one-off creates a plain order without any subscription', () => {

                beforeEach(device);

                startWithEmptyCart();

                mollieProductsAction.openSubscriptionProductDailyOnetime();

                pdp.addToCart(1);

                cy.visit('/checkout/cart');
                cy.get('.line-item-product').should('have.length', 1);

                payWithCard();

                cy.url().should('include', '/checkout/finish');

                // no subscription must have been created for this order
                openLastOrderMollieTab();
                repoOrdersDetails.getSubscriptionBadge().should('not.exist');
            })
        })
    })
})


function startWithEmptyCart() {
    dummyUserScenario.execute();
    // the fixed test customer can carry a restored cart from a previous run
    checkout.clearCart();
}

function payWithCard() {
    checkout.goToCheckout();
    paymentAction.switchPaymentMethod('Card');

    shopware.prepareDomainChange();
    checkout.placeOrderOnConfirm();

    mollieSandbox.initSandboxCookie();
    mollieCreditCardForm.enterValidCard();
    mollieCreditCardForm.submitForm();
    molliePayment.selectPaid();
}

function openLastOrderMollieTab() {
    adminLogin.login();
    adminOrders.openOrders();
    adminOrders.openLastOrder();
    adminOrders.openMollieTab();
}
