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
// ------------------------------------------------------
import MollieSandbox from "cypress-mollie/src/actions/MollieSandbox";
import PaymentScreenAction from "cypress-mollie/src/actions/screens/PaymentStatusScreen";


const devices = new Devices();
const session = new Session();

const configAction = new ShopConfigurationAction();
const checkout = new CheckoutAction();
const paymentAction = new PaymentAction();
const molliePayment = new PaymentScreenAction();
const adminOrders = new AdminOrdersAction();
const adminLogin = new AdminLoginAction();

const mollieSandbox = new MollieSandbox();

const scenarioDummyBasket = new DummyBasketScenario(1);


const device = devices.getFirstDevice();
const shopware = new Shopware();


context("Order Status Mapping Tests", () => {

    before(function () {
        configAction.setupShop(false, false, false);
        configAction.updateProducts('', false, 0, '');
    })

    beforeEach(() => {
        session.resetBrowserSession();
        devices.setDevice(device);
    });

    context(devices.getDescription(device), () => {

        it('C4028: Test Status Open stays In-Progress', () => {

            // we create a SEPA bank transfer payment
            // the payment status will be IN PROGRESS then in Shopware.
            // in reality, SEPA leads to "OPEN". So Mollie will tell us its "only" OPEN
            // but we still need to stick with IN_PROGRESS, otherwise it would be
            // confusing for merchants.

            scenarioDummyBasket.execute();
            paymentAction.switchPaymentMethod('Banktransfer');

            shopware.prepareDomainChange();
            checkout.placeOrderOnConfirm();

            mollieSandbox.initSandboxCookie();
            molliePayment.selectOpen();

            adminLogin.login();
            adminOrders.assertLatestOrderStatus('Open');
            adminOrders.assertLatestPaymentStatus('In Progress');
        })

        it('C4023: Test Status Paid', () => {

            scenarioDummyBasket.execute();
            paymentAction.switchPaymentMethod('PayPal');

            shopware.prepareDomainChange();
            checkout.placeOrderOnConfirm();

            mollieSandbox.initSandboxCookie();
            molliePayment.selectPaid();

            adminLogin.login();
            adminOrders.assertLatestOrderStatus('Done');
            adminOrders.assertLatestPaymentStatus('Paid');
        })

        it('C4024: Test Status Authorized', () => {

            scenarioDummyBasket.execute();
            paymentAction.switchPaymentMethod('Klarna');

            shopware.prepareDomainChange();
            checkout.placeOrderOnConfirm();

            mollieSandbox.initSandboxCookie();
            molliePayment.selectAuthorized();

            adminLogin.login();
            let expectedPaymentStatus = 'Authorized';
            if (shopware.isVersionLower('6.4.1')) {
                expectedPaymentStatus = 'Paid';
            }
            adminOrders.assertLatestOrderStatus('In progress');
            adminOrders.assertLatestPaymentStatus(expectedPaymentStatus);
        })

        it('C4025: Test Status Failed', () => {

            scenarioDummyBasket.execute();
            paymentAction.switchPaymentMethod('PayPal');

            shopware.prepareDomainChange();
            checkout.placeOrderOnConfirm();

            mollieSandbox.initSandboxCookie();
            molliePayment.selectFailed();

            adminLogin.login();
            adminOrders.assertLatestOrderStatus('Open');
            adminOrders.assertLatestPaymentStatus('Failed');
        })

        it('C4026: Test Status Cancelled', () => {

            scenarioDummyBasket.execute();
            paymentAction.switchPaymentMethod('PayPal');

            shopware.prepareDomainChange();
            checkout.placeOrderOnConfirm();

            mollieSandbox.initSandboxCookie();
            molliePayment.selectCancelled();

            adminLogin.login();
            adminOrders.assertLatestOrderStatus('Cancelled');
            adminOrders.assertLatestPaymentStatus('Cancelled');
        })

    })
})
