import Devices from "Services/utils/Devices";
import Session from "Services/utils/Session"
import Shopware from "Services/shopware/Shopware";
// ------------------------------------------------------
import ShopConfigurationAction from "Actions/admin/ShopConfigurationAction";
import CheckoutAction from 'Actions/storefront/checkout/CheckoutAction';
import PaymentAction from "Actions/storefront/checkout/PaymentAction";
import DummyBasketScenario from "Scenarios/DummyBasketScenario";
import AdminOrdersAction from "Actions/admin/AdminOrdersAction";
import AdminLoginAction from "Actions/admin/AdminLoginAction";
import ShipThroughMollieAction from "Actions/admin/ShipThroughMollieAction";
// ------------------------------------------------------
import MollieSandbox from "cypress-mollie/src/actions/MollieSandbox";
import PaymentScreenAction from "cypress-mollie/src/actions/screens/PaymentStatusScreen";
import ShopConfiguration from "../../../support/models/ShopConfiguration";
import PluginConfiguration from "../../../support/models/PluginConfiguration";


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

const device = devices.getFirstDevice();


let beforeAllCalled = false;

function beforeEach(device) {
    cy.wrap(null).then(() => {
        if (!beforeAllCalled) {
            const shopConfig = new ShopConfiguration();
            const pluginConfig = new PluginConfiguration();

            pluginConfig.setCreditCardComponents(false);
            pluginConfig.setCreditCardManualCapture(true);

            configAction.configureEnvironment(shopConfig, pluginConfig);

            beforeAllCalled = true;
        }
        session.resetBrowserSession();
        devices.setDevice(device);
    });
}


describe('Credit Card Manual Capture', () => {

    context(devices.getDescription(device), () => {

        it('C_MANUAL_CAPTURE: Credit card payment is authorized and captured after shipping', () => {

            beforeEach(device);

            const scenarioDummyBasket = new DummyBasketScenario(1);
            scenarioDummyBasket.execute();

            paymentAction.switchPaymentMethod('Card');

            shopware.prepareDomainChange();
            checkout.placeOrderOnConfirm();

            mollieSandbox.initSandboxCookie();
            molliePayment.selectAuthorized();

            adminLogin.login();

            // verify payment status is authorized (not yet paid)
            adminOrders.assertLatestPaymentStatus('Authorized');

            adminOrders.openOrders();
            adminOrders.openLastOrder();

            adminOrders.openShipThroughMollie();
            shippingAction.shipFullOrder();

            // after shipping, payment status should be paid
            adminOrders.assertLatestPaymentStatus('Paid');
        })

    })

})