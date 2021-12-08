import Devices from "Services/utils/Devices";
import Session from "Services/utils/Session"
import Shopware from "Services/shopware/Shopware";
import PaymentScreenAction from 'Actions/mollie/PaymentScreenAction';
// ------------------------------------------------------
import ShopConfigurationAction from "Actions/admin/ShopConfigurationAction";
// ------------------------------------------------------
import CheckoutAction from 'Actions/storefront/checkout/CheckoutAction';
import PaymentAction from "Actions/storefront/checkout/PaymentAction";
import DummyBasketScenario from "Scenarios/DummyBasketScenario";
import AdminOrdersAction from "Actions/admin/AdminOrdersAction";
import AdminLoginAction from "Actions/admin/AdminLoginAction";
import OrderDetailsRepository from "Repositories/admin/orders/OrderDetailsRepository";


const devices = new Devices();
const session = new Session();

const configAction = new ShopConfigurationAction();
const checkout = new CheckoutAction();
const paymentAction = new PaymentAction();
const molliePayment = new PaymentScreenAction();
const adminOrders = new AdminOrdersAction();
const adminLogin = new AdminLoginAction();
const repoOrdersDetails = new OrderDetailsRepository();

const scenarioDummyBasket = new DummyBasketScenario(1);


const device = devices.getFirstDevice();
const shopware = new Shopware();


context("Order Refunds", () => {

    before(function () {
        configAction.setupShop(false, false, false);
    })

    beforeEach(() => {
        session.resetBrowserSession();
        devices.setDevice(device);
    });

    context(devices.getDescription(device), () => {

        it('Refund Order in Admin', () => {

            scenarioDummyBasket.execute();
            paymentAction.switchPaymentMethod('PayPal');

            shopware.prepareDomainChange();
            checkout.placeOrderOnConfirm();

            molliePayment.initSandboxCookie();
            molliePayment.selectPaid();

            adminLogin.login();
            adminOrders.openOrders();

            // now refund our order with 1 EUR
            adminOrders.refundLatestOrder("1");

            // after refunded, open the refund manager
            // and verify that we see a PENDING refund in it as well as
            // the correct 1 EUR value.
            repoOrdersDetails.getMollieRefundManagerButton().click();
            repoOrdersDetails.getMollieRefundManagerFirstRefundStatusLabel().contains('Pending');
            repoOrdersDetails.getMollieRefundManagerFirstRefundAmountLabel().contains('€1.00');
        })

    })
})
