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


context("Cancel Authorized items", () => {
    before(function () {
        configAction.setupShop(false, false, false);
        configAction.updateProducts('', false, 0, '');
    })

    beforeEach(() => {
        session.resetBrowserSession();
        devices.setDevice(device);
    });

    context(devices.getDescription(device), () => {
        it ('Cancel items from order', () => {
            createOrderAndOpenAdmin('Pay now');


            orderDetailsRepository.getLineItemActionsButton(1).should('be.visible').click({force: true});

            orderDetailsRepository.getLineItemActionsButtonCancelThroughMollie().should('not.have.class', 'is--disabled');
            orderDetailsRepository.getLineItemActionsButtonCancelThroughMollie().click({force:true});
            cancelItemRepository.getQuantityInput().type(2);
            cancelItemRepository.getItemLabel().should('not.be.empty');
            cancelItemRepository.getConfirmButton().click({force:true});
            orderDetailsRepository.getLineItemCancelled().should('contain.text',2);
            orderDetailsRepository.getLineItemActionsButton(1).click({force: true});
            orderDetailsRepository.getLineItemActionsButtonCancelThroughMollie().should('have.class', 'is--disabled');

        }) ;

        it('Check cancel button on non authorized order',() =>{
            createOrderAndOpenAdmin('PayPal');


            orderDetailsRepository.getLineItemActionsButton(1).should('be.visible').click({force: true});

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
    molliePayment.selectAuthorized();

    adminLogin.login();
    adminOrders.openOrders();
    adminOrders.openLastOrder();
}
