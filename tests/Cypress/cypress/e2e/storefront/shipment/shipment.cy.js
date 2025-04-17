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


let beforeAllCalled = false;

function beforeEach(device) {
    cy.wrap(null).then(() => {
        if (!beforeAllCalled) {
            configAction.setupShop(false, false, false);
            configAction.prepareShippingMethods();
            configAction.updateProducts('', false, '', '');
            beforeAllCalled = true;
        }
        session.resetBrowserSession();
        devices.setDevice(device);
    });
}


context("Order Shipping", () => {

    context(devices.getDescription(device), () => {

        it('C4039: Full Shipping in Administration @sanity', () => {

            beforeEach(device);

            createOrderAndOpenAdmin(2, 1);

            adminOrders.openShipThroughMollie();

            // make sure our modal is visible
            cy.contains('.sw-modal__header', 'Ship through Mollie', {timeout: 50000});

            // verify we have 2x 1 item
            // we use contain because linebreaks \n exist.
            // but we don't add 11 items...so that should be fine
            repoShippingFull.getFirstItemQuantity().should('contain.text', '1');
            repoShippingFull.getSecondItemQuantity().should('contain.text', '1');

            shippingAction.shipFullOrder();

            // verify delivery status and item shipped count
            assertShippingStatus('Shipped', 2);

            if (shopware.isVersionLower('6.5')) {
                repoOrderDetails.getMollieActionsButton().click({force: true});
            }
            assertShippingButtonIsDisabled();

        })

        it('C152048: Full Shipping in Administration with Tracking', () => {

            beforeEach(device);

            const TRACKING_CODE = 'code-123';

            createOrderAndOpenAdmin(2, 1);

            adminOrders.setTrackingCode(TRACKING_CODE);

            adminOrders.openShipThroughMollie();

            // verify that the tracking code is present in ship through mollie
            repoShippingFull.getAvailableTrackingCodes().contains(TRACKING_CODE);

            repoShippingFull.getTrackingCarrier().should('not.have.value', '');
            repoShippingFull.getTrackingCode().should('have.value', TRACKING_CODE);
            repoShippingFull.getTrackingUrl().should('not.have.value', '');

            shippingAction.shipFullOrder();

            assertShippingStatus('Shipped', 2);

            if (shopware.isVersionLower('6.5')) {
                repoOrderDetails.getMollieActionsButton().click({force: true});
            }

            assertShippingButtonIsDisabled();
        })

        it('C2138608: Partial Batch Shipping in Administration', () => {

            beforeEach(device);

            createOrderAndOpenAdmin(2, 1);

            adminOrders.openShipThroughMollie();

            // make sure our modal is visible
            cy.contains('.sw-modal__header', 'Ship through Mollie', {timeout: 50000});

            // verify we have 2x 1 item
            // we use contain because linebreaks \n exist.
            // but we don't add 11 items...so that should be fine
            repoShippingFull.getFirstItemQuantity().should('contain.text', '1');
            repoShippingFull.getSecondItemQuantity().should('contain.text', '1');

            shippingAction.shipBatchOrder();

            assertShippingStatus('Shipped (partially)', 1);
        })

        it('C4040: Line Item Shipping in Administration', () => {

            beforeEach(device);

            createOrderAndOpenAdmin(2, 2);

            adminOrders.openLineItemShipping(1);

            repoShippingItem.getShippedQuantity().should('contain.text', '0');
            repoShippingItem.getShippableQuantity().should('contain.text', '2');

            shippingAction.shipLineItem(1);

            // somehow this is required in Shopware 6.5, lets just stick with it, its ok
            cy.wait(2000);
            cy.reload();

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

            shippingAction.shipFullOrder();

            assertShippingStatus('Shipped', 4);

            if (shopware.isVersionLower('6.5')) {
                repoOrderDetails.getMollieActionsButton().click({force: true});
            }

            assertShippingButtonIsDisabled();
        })

        it('C4044: Line Item Shipping with Tracking', () => {

            beforeEach(device);

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

            beforeEach(device);

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

    paymentAction.switchPaymentMethod('Klarna');

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
    cy.reload();
    cy.wait(2000);

    repoOrderDetails.getDeliveryStatusTop().should('contain.text', statusLabel, {timeout: 6000});

    if (shopware.isVersionLower('6.5')) {
        /** since 6.5 you don't see the shipped items in summary **/
        repoOrderDetails.getOrderSummarySection().should('contain.text', 'Shipped amount (' + shippedItemsCount + ' items)', {timeout: 6000});
    }
}

function assertShippingButtonIsDisabled(){

    repoOrderDetails.getMollieActionButtonShipThroughMollie()
        .should('have.attr','class')
        .and('match', /--disabled/);
}