import Devices from "Services/utils/Devices";
import Session from "Services/utils/Session"
import Element from "Services/utils/Element";
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
import MollieRefundManagerRepository from "Repositories/admin/orders/MollieRefundManagerRepository";


const devices = new Devices();
const session = new Session();
const element = new Element();
const shopware = new Shopware();

const repoOrdersDetails = new OrderDetailsRepository();
const repoRefundManager = new MollieRefundManagerRepository();

const configAction = new ShopConfigurationAction();
const checkout = new CheckoutAction();
const paymentAction = new PaymentAction();
const molliePayment = new PaymentScreenAction();
const adminOrders = new AdminOrdersAction();
const adminLogin = new AdminLoginAction();

const scenarioDummyBasket = new DummyBasketScenario(1);

const device = devices.getFirstDevice();


context("Order Refunds", () => {

    before(function () {
        configAction.setupShop(false, false, false);
    })

    beforeEach(() => {
        session.resetBrowserSession();
        devices.setDevice(device);
    });

    context(devices.getDescription(device), () => {

        it('Refund Manager not available if not paid', () => {

            scenarioDummyBasket.execute();
            paymentAction.switchPaymentMethod('Pay later');

            shopware.prepareDomainChange();
            checkout.placeOrderOnConfirm();

            molliePayment.initSandboxCookie();
            molliePayment.selectAuthorized();

            adminLogin.login();
            adminOrders.openOrders();
            adminOrders.openLastOrder();

            repoOrdersDetails.getMollieRefundManagerButton().should('be.disabled');
        })

        it('Refund Order in Admin', () => {

            scenarioDummyBasket.execute();
            paymentAction.switchPaymentMethod('PayPal');

            shopware.prepareDomainChange();
            checkout.placeOrderOnConfirm();

            molliePayment.initSandboxCookie();
            molliePayment.selectPaid();

            adminLogin.login();
            adminOrders.openOrders();
            adminOrders.openLastOrder();

            // now refund our order with 1 EUR
            adminOrders.refundOrder("1");

            // after refunded, open the refund manager
            // and verify that we see a PENDING refund in it as well as
            // the correct 1 EUR value.
            cy.wait(500);
            repoOrdersDetails.getMollieRefundManagerButton().click();
            repoRefundManager.getFirstRefundStatusLabel().contains('Pending');

            // because of (weird) number formats which might not be the same all the time (even if they should)
            // we just search within multiple formats
            element.containsText(
                repoRefundManager.getFirstRefundAmountLabel(),
                ['1.00', '1,00']
            )
        })

        it('Cancel pending refund in Admin', () => {

            scenarioDummyBasket.execute();
            paymentAction.switchPaymentMethod('PayPal');

            shopware.prepareDomainChange();
            checkout.placeOrderOnConfirm();

            molliePayment.initSandboxCookie();
            molliePayment.selectPaid();

            adminLogin.login();
            adminOrders.openOrders();
            adminOrders.openLastOrder();

            // now refund our order with 1 EUR
            adminOrders.refundOrder("1");

            // afterwards, try to cancel our refund again
            adminOrders.cancelOrderRefund();

            cy.reload();

            // let's open the refund manager, and verify
            // that the pending refund is NOT existing anymore
            cy.wait(500);
            repoOrdersDetails.getMollieRefundManagerButton().click();
            cy.contains('Pending').should('not.exist');
        })

    })
})
