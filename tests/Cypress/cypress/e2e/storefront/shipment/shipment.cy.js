import Devices from "Services/utils/Devices";
import Session from "Services/utils/Session"
import Shopware from "Services/shopware/Shopware";
// ------------------------------------------------------
import ShopConfigurationAction from "Actions/admin/ShopConfigurationAction";
// ------------------------------------------------------
import CheckoutAction from 'Actions/storefront/checkout/CheckoutAction';
import PaymentAction from "Actions/storefront/checkout/PaymentAction";
import DummyBasketScenario from "Scenarios/DummyBasketScenario";
import AdminOrdersAction from "Actions/admin/AdminOrdersAction";
import AdminLoginAction from "Actions/admin/AdminLoginAction";
import ShipThroughMollieAction from "Actions/admin/ShipThroughMollieAction";
// ------------------------------------------------------
import MollieSandbox from "cypress-mollie/src/actions/MollieSandbox";
import PaymentScreenAction from "cypress-mollie/src/actions/screens/PaymentStatusScreen";


const devices = new Devices();
const session = new Session();
const shopware = new Shopware();


const configAction = new ShopConfigurationAction();
const checkout = new CheckoutAction();
const paymentAction = new PaymentAction();
const mollieSandbox = new MollieSandbox();
const molliePayment = new PaymentScreenAction();
const adminOrders = new AdminOrdersAction();
const adminLogin = new AdminLoginAction();
const shipThroughMollie = new ShipThroughMollieAction();

const scenarioDummyBasket = new DummyBasketScenario(1, 2);

const device = devices.getFirstDevice();


context("Order Refunds", () => {

    before(function () {
        //configAction.setupShop(false, false, false);
        //configAction.updateProducts('', false, 0, '');
    })

    beforeEach(() => {
        session.resetBrowserSession();
        devices.setDevice(device);
    });

    context(devices.getDescription(device), () => {

        it('C4151: Ship through Mollie', () => {
            // create an order with 2 line items
            createOrderAndOpenAdmin();

            // open the ship through mollie action
            adminOrders.openShipThroughMollie();

            // ship the order
            shipThroughMollie.ship();

            // verify that the order has been shipped
            cy.get('.sw-order-detail__summary-data').contains('Shipped amount (2 items)').should('exist');
        })

        it('C4152: Ship through Mollie at line item', () => {
            // create an order with 2 line items
            createOrderAndOpenAdmin();

            // open the refund manager
            // and start a partial refund of 2 EUR
            adminOrders.openShipThroughMollieAtLineItem(1);

            // ship the lineItem
            shipThroughMollie.shipLineItem(1);

            // verify that the order has been shipped
            cy.get('.sw-order-detail__summary-data').contains('Shipped amount (1 items)').should('exist');
        })

        it('C4153: Ship through Mollie with tracking code', () => {
            // create an order with 2 line items
            createOrderAndOpenAdmin();
            adminOrders.setTrackingCode('asdf123456789')

            // open the ship through mollie action
            adminOrders.openShipThroughMollie();

            // verify that the tracking code is present in ship through mollie
            cy.get('[style="place-items: stretch;"] > :nth-child(1) > .sw-button > .sw-button__content').contains('asdf123456789');
            cy.get('#sw-field--tracking-code').contains('asdf123456789');

            // ship the order
            shipThroughMollie.ship();

            // verify that the order has been shipped
            cy.get('.sw-order-detail__summary-data').contains('Shipped amount (2 items)').should('exist');

            // verify that the tracking code is present
            cy.get(':nth-child(6) > .sw-button > .sw-button__content').contains('asdf123456789');
        })

        it.only('C4153: Ship through Mollie with tracking code as line item', () => {
            // create an order with 2 line items
            createOrderAndOpenAdmin();
            adminOrders.openShipThroughMollieAtLineItem(1);
            adminOrders.addTrackingCodeToLineItem(1, 'Express', 'asdf123456789');

            adminOrders.openShipThroughMollieAtLineItem(2);
            adminOrders.addTrackingCodeToLineItem(1, 'Express', 'fdsa987654321');
            //adminOrders.setTrackingCodeAtLineItem('asdf123456789')

            // open the ship through mollie action
            adminOrders.openShipThroughMollie();

            // verify that the tracking code is present in ship through mollie
            cy.get('[style="place-items: stretch;"] > :nth-child(1) > .sw-button > .sw-button__content').contains('asdf123456789');
            cy.get('#sw-field--tracking-code').contains('asdf123456789');

            // ship the order
            shipThroughMollie.ship();

            // verify that the order has been shipped
            cy.get('.sw-order-detail__summary-data').contains('Shipped amount (2 items)').should('exist');

            // verify that the tracking code is present
            cy.get(':nth-child(6) > .sw-button > .sw-button__content').contains('asdf123456789');
            cy.get(':nth-child(6) > .sw-button > .sw-button__content').contains('fdsa987654321');
        })
    })
})


function createOrderAndOpenAdmin() {
    scenarioDummyBasket.execute();
    paymentAction.switchPaymentMethod('PayPal');

    shopware.prepareDomainChange();
    checkout.placeOrderOnConfirm();

    mollieSandbox.initSandboxCookie();
    molliePayment.selectPaid();

    adminLogin.login();
    adminOrders.openOrders();
    adminOrders.openLastOrder();
}
