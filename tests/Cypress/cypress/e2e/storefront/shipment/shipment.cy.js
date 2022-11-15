import Devices from "Services/utils/Devices";
import Session from "Services/utils/Session"
import Shopware from "Services/shopware/Shopware";
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
import LineItemShippingRepository from "Repositories/admin/ship-through-mollie/LineItemShippingRepository";
import OrderDetailsRepository from "Repositories/admin/orders/OrderDetailsRepository";
import FullShippingRepository from "Repositories/admin/ship-through-mollie/FullShippingRepository";
import ShopConfigurationAction from "Actions/admin/ShopConfigurationAction";


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
const shippingAction = new ShipThroughMollieAction();

const repoOrderDetails = new OrderDetailsRepository();
const repoShippingFull = new FullShippingRepository();
const repoShippingItem = new LineItemShippingRepository();


const device = devices.getFirstDevice();


context("Order Shipping", () => {

    before(() => {
        configAction.updateProducts('', false, '', '');
    })

    beforeEach(() => {
        session.resetBrowserSession();
        devices.setDevice(device);
    });

    context(devices.getDescription(device), () => {

        it('C4039: Full Shipping in Administration', () => {

            createOrderAndOpenAdmin(2, 1);

            adminOrders.openShipThroughMollie();

            // verify we have 2x 1 item
            // we use contain because linebreaks \n exist.
            // but we don't add 11 items...so that should be fine
            repoShippingFull.getFirstItemQuantity().should('contain.text', '1');
            repoShippingFull.getSecondItemQuantity().should('contain.text', '1');

            shippingAction.shipOrder();

            // verify delivery status and item shipped count
            assertShippingStatus('Shipped', 2);

            repoOrderDetails.getMollieActionsButton().click({force: true});
            repoOrderDetails.getMollieActionButtonShipThroughMollie().should('have.class', 'is--disabled');
        })

        it('C152048: Full Shipping in Administration with Tracking', () => {

            const TRACKING_CODE = 'code-123';

            createOrderAndOpenAdmin(2, 1);

            adminOrders.setTrackingCode(TRACKING_CODE);

            adminOrders.openShipThroughMollie();

            // verify that the tracking code is present in ship through mollie
            repoShippingFull.getAvailableTrackingCodes().contains(TRACKING_CODE);

            repoShippingFull.getTrackingCarrier().should('not.have.value', '');
            repoShippingFull.getTrackingCode().should('have.value', TRACKING_CODE);
            repoShippingFull.getTrackingUrl().should('have.value', '');

            shippingAction.shipOrder();

            assertShippingStatus('Shipped', 2);

            repoOrderDetails.getMollieActionsButton().click({force: true});
            repoOrderDetails.getMollieActionButtonShipThroughMollie().should('have.class', 'is--disabled');
        })

        it('C4040: Partial Shipping in Administration', () => {

            createOrderAndOpenAdmin(2, 2);

            adminOrders.openLineItemShipping(1);

            repoShippingItem.getShippedQuantity().should('contain.text', '0');
            repoShippingItem.getShippableQuantity().should('contain.text', '2');

            shippingAction.shipLineItem(1);

            assertShippingStatus('Shipped (partially)', 1);

            // --------------------------------------------------------------------------------------------------------------------

            // verify that 1 is now shipped, and 1 is still possible
            adminOrders.openLineItemShipping(1);

            repoShippingItem.getShippedQuantity().should('contain.text', '1');
            repoShippingItem.getShippableQuantity().should('contain.text', '1');

            // ship the second one
            shippingAction.shipLineItem(1);

            assertShippingStatus('Shipped (partially)', 2);

            // --------------------------------------------------------------------------------------------------------------------

            // now complete our partial shipping, by shipping the rest
            adminOrders.openShipThroughMollie();

            // only our "open" items are displayed.
            // so the first item is actually our second one that was not yet shipped.
            repoShippingFull.getFirstItemQuantity().should('contain.text', '2');

            shippingAction.shipOrder();

            assertShippingStatus('Shipped', 4);

            repoOrderDetails.getMollieActionsButton().click({force: true});
            repoOrderDetails.getMollieActionButtonShipThroughMollie().should('have.class', 'is--disabled');
        })

        it('C4044: Partial Shipping with Tracking', () => {

            const TRACKING_CODE1 = 'code-1';
            const TRACKING_CODE2 = 'code-2';

            createOrderAndOpenAdmin(2, 2);

            adminOrders.openLineItemShipping(1);

            repoShippingFull.getTrackingCarrier().should('not.exist');
            repoShippingFull.getTrackingCode().should('not.exist');
            repoShippingFull.getTrackingUrl().should('not.exist');

            repoShippingItem.getCancelButton().click();

            adminOrders.setTrackingCode(TRACKING_CODE1);

            adminOrders.openLineItemShipping(1);

            repoShippingFull.getTrackingCode().should('have.value', TRACKING_CODE1);

            shippingAction.shipLineItem(1);

            assertShippingStatus('Shipped (partially)', 1);

            cy.wait(1000);

            adminOrders.setTrackingCode(TRACKING_CODE2);

            adminOrders.openLineItemShipping(1);

            repoShippingFull.getAvailableTrackingCodes().contains(TRACKING_CODE1);
            repoShippingFull.getAvailableTrackingCodes().contains(TRACKING_CODE2);
        })

        it('C152049: Shipment offers selection from multiple tracking codes', () => {

            const TRACKING_CODE1 = 'code-1';
            const TRACKING_CODE2 = 'code-2';

            createOrderAndOpenAdmin(2, 1);

            adminOrders.setTrackingCode(TRACKING_CODE1);
            adminOrders.setTrackingCode(TRACKING_CODE2);

            adminOrders.openShipThroughMollie();

            repoShippingFull.getAvailableTrackingCodes().contains(TRACKING_CODE1);
            repoShippingFull.getAvailableTrackingCodes().contains(TRACKING_CODE2);

            // pre-select code 2
            repoShippingFull.getAvailableTrackingCodes().contains(TRACKING_CODE2).click({force: true});
            repoShippingFull.getTrackingCode().should('have.value', TRACKING_CODE2);

            // pre-select code 1
            repoShippingFull.getAvailableTrackingCodes().contains(TRACKING_CODE1).click({force: true});
            repoShippingFull.getTrackingCode().should('have.value', TRACKING_CODE1);
        })

    })
})


/**
 *
 */
function createOrderAndOpenAdmin(itemCount, itemQty) {

    const scenarioDummyBasket = new DummyBasketScenario(itemQty, itemCount);
    scenarioDummyBasket.execute();

    paymentAction.switchPaymentMethod('Pay later');

    shopware.prepareDomainChange();
    checkout.placeOrderOnConfirm();

    mollieSandbox.initSandboxCookie();
    molliePayment.selectAuthorized();

    adminLogin.login();
    adminOrders.openOrders();
    adminOrders.openLastOrder();
}

/**
 *
 * @param statusLabel
 * @param shippedItemsCount
 */
function assertShippingStatus(statusLabel, shippedItemsCount) {

    cy.wait(2000);

    repoOrderDetails.getDeliveryStatusTop().should('contain.text', statusLabel, {timeout: 6000});

    repoOrderDetails.getOrderSummarySection().should('contain.text', 'Shipped amount (' + shippedItemsCount + ' items)', {timeout: 6000});
}