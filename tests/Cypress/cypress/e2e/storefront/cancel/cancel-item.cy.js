import DummyBasketScenario from 'Scenarios/DummyBasketScenario';
import PaymentAction from "Actions/storefront/checkout/PaymentAction";
import PaymentScreenAction from "cypress-mollie/src/actions/screens/PaymentStatusScreen";
import CheckoutAction from "Actions/storefront/checkout/CheckoutAction";
import MollieSandbox from "cypress-mollie/src/actions/MollieSandbox";
import AdminOrdersAction from "Actions/admin/AdminOrdersAction";
import AdminLoginAction from "Actions/admin/AdminLoginAction";
import Shopware from "Services/shopware/Shopware";
import Devices from "Services/utils/Devices";
import ShopConfigurationAction from "Actions/admin/ShopConfigurationAction";
import OrderDetailsRepository from "Repositories/admin/orders/OrderDetailsRepository";
import CancelItemRepository from "Repositories/admin/cancel-item/CancelItemRepository";
import Session from "Services/utils/Session";


const devices = new Devices();
const shopware = new Shopware();
const configAction = new ShopConfigurationAction();

const checkout = new CheckoutAction();
const paymentAction = new PaymentAction();
const mollieSandbox = new MollieSandbox();
const molliePayment = new PaymentScreenAction();
const adminOrders = new AdminOrdersAction();
const adminLogin = new AdminLoginAction();
const scenarioDummyBasket = new DummyBasketScenario(2);
const orderDetailsRepository = new OrderDetailsRepository();
const cancelItemRepository = new CancelItemRepository();
const device = devices.getFirstDevice();
const session = new Session();


let beforeAllCalled = false;

function beforeEach(device) {
    cy.wrap(null).then(() => {
        if (!beforeAllCalled) {
            configAction.setupShop(false, false, false);
            configAction.updateProducts('', false, 0, '');
            beforeAllCalled = true;
        }
        session.resetBrowserSession();
        devices.setDevice(device);
    });
}


context("Cancel Authorized items", () => {

    context(devices.getDescription(device), () => {
        it('C3259233: Cancel items from order', () => {

            beforeEach(device);

            createOrderAndOpenAdmin('Klarna');

            orderDetailsRepository.getLineItemActionsButton(1).should('be.visible').trigger('click');

            orderDetailsRepository.getLineItemActionsButtonCancelThroughMollie().should('not.have.class', 'is--disabled');
            orderDetailsRepository.getLineItemActionsButtonCancelThroughMollie().click({force: true});
            cancelItemRepository.getQuantityInput().clear().type(2);
            cancelItemRepository.getResetStockToggle().click({force: true});
            cancelItemRepository.getItemLabel().should('not.be.empty');
            cancelItemRepository.getConfirmButton().click({force: true});
            orderDetailsRepository.getLineItemCancelled().should('contain.text', 2);
            orderDetailsRepository.getLineItemActionsButton(1).trigger('click');
            orderDetailsRepository.getLineItemActionsButtonCancelThroughMollie().should('have.class', 'is--disabled');

        });

        it('C3259299: Check cancel button on non authorized order', () => {

            beforeEach(device);

            createOrderAndOpenAdmin('PayPal');

            orderDetailsRepository.getLineItemActionsButton(1).should('be.visible').trigger('click');

            orderDetailsRepository.getLineItemActionsButtonCancelThroughMollie().should('have.class', 'is--disabled');
        });
    });
});


function createOrderAndOpenAdmin(paymentMethod) {
    scenarioDummyBasket.execute();
    paymentAction.switchPaymentMethod(paymentMethod);

    shopware.prepareDomainChange();
    checkout.placeOrderOnConfirm();

    mollieSandbox.initSandboxCookie();

    if (paymentMethod === 'PayPal') {
        molliePayment.selectPaid();
    } else {
        molliePayment.selectAuthorized();
    }

    adminLogin.login();
    adminOrders.openOrders();
    adminOrders.openLastOrder();
}
