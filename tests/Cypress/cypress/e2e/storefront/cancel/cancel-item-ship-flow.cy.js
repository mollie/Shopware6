import DummyBasketScenario from 'Scenarios/DummyBasketScenario';
import PaymentAction from "Actions/storefront/checkout/PaymentAction";
import PaymentScreenAction from "cypress-mollie/src/actions/screens/PaymentStatusScreen";
import CheckoutAction from "Actions/storefront/checkout/CheckoutAction";
import MollieSandbox from "cypress-mollie/src/actions/MollieSandbox";
import AdminOrdersAction from "Actions/admin/AdminOrdersAction";
import AdminLoginAction from "Actions/admin/AdminLoginAction";
import ShipThroughMollieAction from "Actions/admin/ShipThroughMollieAction";
import Shopware from "Services/shopware/Shopware";
import Devices from "Services/utils/Devices";
import ShopConfigurationAction from "Actions/admin/ShopConfigurationAction";
import OrderDetailsRepository from "Repositories/admin/orders/OrderDetailsRepository";
import CancelItemRepository from "Repositories/admin/cancel-item/CancelItemRepository";
import LineItemShippingRepository from "Repositories/admin/ship-through-mollie/LineItemShippingRepository";
import Session from "Services/utils/Session";
import ShopConfiguration from "../../../support/models/ShopConfiguration";
import PluginConfiguration from "../../../support/models/PluginConfiguration";


const devices = new Devices();
const shopware = new Shopware();
const configAction = new ShopConfigurationAction();
const session = new Session();

const checkout = new CheckoutAction();
const paymentAction = new PaymentAction();
const mollieSandbox = new MollieSandbox();
const molliePayment = new PaymentScreenAction();
const adminOrders = new AdminOrdersAction();
const adminLogin = new AdminLoginAction();
const shippingAction = new ShipThroughMollieAction();

const repoOrderDetails = new OrderDetailsRepository();
const repoCancelItem = new CancelItemRepository();
const repoShippingItem = new LineItemShippingRepository();

const device = devices.getFirstDevice();

let beforeAllCalled = false;

function runBeforeEach(device) {
    cy.wrap(null).then(() => {
        if (!beforeAllCalled) {
            const shopConfig = new ShopConfiguration();
            const pluginConfig = new PluginConfiguration();

            configAction.configureEnvironment(shopConfig, pluginConfig);

            beforeAllCalled = true;
        }
        session.resetBrowserSession();
        devices.setDevice(device);
    });
}


context('Cancel item + shipping flow', () => {

    context(devices.getDescription(device), () => {

        it('C_ORDERS_API: Ship one item, cancel another — verify Paid + Shipped (Klarna Orders API)', () => {

            runBeforeEach(device);

            createOrderAndOpenAdmin('Kl (Orders API - Test only)');

            // --- ship item 1 via line item shipping ---
            adminOrders.openLineItemShipping(1);

            repoShippingItem.getShippedQuantity().should('contain.text', '0');
            repoShippingItem.getShippableQuantity().should('contain.text', '1');

            shippingAction.shipLineItem(1);

            assertDeliveryStatus('Shipped (partially)');

            // --- cancel item 2 ---
            adminOrders.openMollieTab();

            repoOrderDetails.getLineItemActionsButton(2).should('be.visible').trigger('click');

            repoOrderDetails.getLineItemActionsButtonCancelThroughMollie().should('not.have.class', 'is--disabled');
            repoOrderDetails.getLineItemActionsButtonCancelThroughMollie().click({force: true});

            repoCancelItem.getItemLabel().should('not.be.empty');
            repoCancelItem.getQuantityInput().clear().type(1);
            repoCancelItem.getConfirmButton().click({force: true});

            repoOrderDetails.getLineItemCancelled().should('contain.text', 1);

            // --- verify final state ---
            repoOrderDetails.getOrderDetailsGeneralTab().click();
            cy.reload();

            repoOrderDetails.getDeliveryStatusTop().should('contain.text', 'Shipped (partially)', {timeout: 10000});
            repoOrderDetails.getPaymentStatusTop().should('contain.text', 'Paid', {timeout: 10000});
        });


        it('C_PAYMENTS_API: Ship one item, cancel another — verify Paid + Shipped (partially) (Klarna Payments API)', () => {

            runBeforeEach(device);

            createOrderAndOpenAdmin('Klarna');

            // --- ship item 1 via line item shipping ---
            adminOrders.openLineItemShipping(1);

            repoShippingItem.getShippedQuantity().should('contain.text', '0');
            repoShippingItem.getShippableQuantity().should('contain.text', '1');

            shippingAction.shipLineItem(1);

            assertDeliveryStatus('Shipped (partially)');

            // --- cancel item 2 ---
            adminOrders.openMollieTab();

            repoOrderDetails.getLineItemActionsButton(2).should('be.visible').trigger('click');

            repoOrderDetails.getLineItemActionsButtonCancelThroughMollie().should('not.have.class', 'is--disabled');
            repoOrderDetails.getLineItemActionsButtonCancelThroughMollie().click({force: true});

            repoCancelItem.getItemLabel().should('not.be.empty');
            repoCancelItem.getQuantityInput().clear().type(1);
            repoCancelItem.getConfirmButton().click({force: true});

            repoOrderDetails.getLineItemCancelled().should('contain.text', 1);

            // --- verify final state ---
            repoOrderDetails.getOrderDetailsGeneralTab().click();
            cy.reload();

            repoOrderDetails.getDeliveryStatusTop().should('contain.text', 'Shipped (partially)', {timeout: 10000});
            repoOrderDetails.getPaymentStatusTop().should('contain.text', 'Paid', {timeout: 10000});
        });

    });

});


function createOrderAndOpenAdmin(paymentMethod) {
    const scenario = new DummyBasketScenario(1, 2);
    scenario.execute();

    paymentAction.switchPaymentMethod(paymentMethod);

    shopware.prepareDomainChange();
    checkout.placeOrderOnConfirm();

    mollieSandbox.initSandboxCookie();
    molliePayment.selectAuthorized();

    adminLogin.login();
    adminOrders.openOrders();
    adminOrders.openLastOrder();
    adminOrders.openMollieTab();
}

function assertDeliveryStatus(statusLabel) {
    cy.contains('The order has been successfully shipped.', {timeout: 20000});
    repoOrderDetails.getOrderDetailsGeneralTab().click();
    cy.reload();
    repoOrderDetails.getDeliveryStatusTop().should('contain.text', statusLabel, {timeout: 6000});
    adminOrders.openMollieTab();
}
