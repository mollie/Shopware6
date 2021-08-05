import Devices from "Services/Devices";
import Session from "Actions/utils/Session"
import PaymentScreenAction from 'Actions/mollie/PaymentScreenAction';
// ------------------------------------------------------
import ShopConfigurationAction from "Actions/admin/ShopConfigurationAction";
// ------------------------------------------------------
import CheckoutAction from 'Actions/storefront/checkout/CheckoutAction';
import PaymentAction from "Actions/storefront/checkout/PaymentAction";
import DummyBasketScenario from "Scenarios/DummyBasketScenario";
import AdminOrdersAction from "Actions/admin/AdminOrdersAction";
import AdminLoginAction from "Actions/admin/AdminLoginAction";
import Shopware from "Services/Shopware";


const devices = new Devices();
const session = new Session();

const configAction = new ShopConfigurationAction();
const checkout = new CheckoutAction();
const paymentAction = new PaymentAction();
const molliePayment = new PaymentScreenAction();
const adminOrders = new AdminOrdersAction();
const adminLogin = new AdminLoginAction();

const scenarioDummyBasket = new DummyBasketScenario(1);


const device = devices.getFirstDevice();
const shopware = new Shopware();


context("Order Status Mapping Tests", () => {

    before(function () {
        molliePayment.initSandboxCookie();
        devices.setDevice(device);
        configAction.setupShop(false, false);
    })

    beforeEach(() => {
        session.resetBrowserSession();
        devices.setDevice(device);
    });

    context(devices.getDescription(device), () => {

        it('Test Status Paid', () => {

            scenarioDummyBasket.execute();
            paymentAction.switchPaymentMethod('PayPal');
            checkout.placeOrderOnConfirm();

            molliePayment.selectPaid();

            adminLogin.login();
            adminOrders.assertLatestOrderStatus('Done');
            adminOrders.assertLatestPaymentStatus('Paid');
        })

        it('Test Status Authorized', () => {

            scenarioDummyBasket.execute();
            paymentAction.switchPaymentMethod('Pay later');
            checkout.placeOrderOnConfirm();

            molliePayment.selectAuthorized();

            adminLogin.login();
            let expectedPaymentStatus = 'Authorized';
            if (shopware.isVersionLower('6.4.1')) {
                expectedPaymentStatus = 'Paid';
            }
            adminOrders.assertLatestOrderStatus('In progress');
            adminOrders.assertLatestPaymentStatus(expectedPaymentStatus);
        })

        it('Test Status Failed', () => {

            scenarioDummyBasket.execute();
            paymentAction.switchPaymentMethod('PayPal');
            checkout.placeOrderOnConfirm();

            molliePayment.selectFailed();

            adminLogin.login();
            adminOrders.assertLatestOrderStatus('Open');
            adminOrders.assertLatestPaymentStatus('Cancelled');
        })

        it('Test Status Cancelled', () => {

            scenarioDummyBasket.execute();
            paymentAction.switchPaymentMethod('PayPal');
            checkout.placeOrderOnConfirm();

            molliePayment.selectCancelled();

            adminLogin.login();
            adminOrders.assertLatestOrderStatus('Cancelled');
            adminOrders.assertLatestPaymentStatus('Cancelled');
        })
    })
})
