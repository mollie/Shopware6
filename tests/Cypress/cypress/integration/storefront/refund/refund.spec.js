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
import RefundManagerAction from "Actions/admin/RefundManagerAction";
import RefundManagerRepository from "Repositories/admin/refund-manager/RefundManagerRepository";


const devices = new Devices();
const session = new Session();
const elementHelper = new Element();
const shopware = new Shopware();


const configAction = new ShopConfigurationAction();
const checkout = new CheckoutAction();
const paymentAction = new PaymentAction();
const molliePayment = new PaymentScreenAction();
const adminOrders = new AdminOrdersAction();
const adminLogin = new AdminLoginAction();
const refundManager = new RefundManagerAction();

const repoRefundManager = new RefundManagerRepository();

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

        it.only('Create full refund and cancel it', () => {

            createOrderAndOpenAdmin();


            const REFUND_DESCRIPTION = 'full refund with Cypress';

            // -------------------------------------------------------------------------------

            // open the refund manager
            // and start a partial refund of 2 EUR
            adminOrders.openRefundManager();

            // check if our button is disabled if
            // the checkbox for the verification is not enabled
            repoRefundManager.getFullRefundButton().should('be.disabled');

            // now start the partial refund
            refundManager.fullRefund(REFUND_DESCRIPTION);

            // verify that our refund now exists
            repoRefundManager.getFirstRefundStatusLabel().contains('Pending');
            repoRefundManager.getFirstRefundDescriptionLabel().contains(REFUND_DESCRIPTION);

            // -------------------------------------------------------------------------------

            // now cancel our pending refund
            // and make sure that its gone afterwards
            refundManager.cancelPendingRefund();
            cy.contains(REFUND_DESCRIPTION).should('not.exist')
        })


        it('Create partial refund and cancel it', () => {

            createOrderAndOpenAdmin();


            const REFUND_DESCRIPTION = 'partial refund with Cypress';

            // -------------------------------------------------------------------------------

            // open the refund manager
            // and start a partial refund of 2 EUR
            adminOrders.openRefundManager();

            // check if our button is disabled if
            // the checkbox for the verification is not enabled
            repoRefundManager.getRefundButton().should('be.disabled');

            // now start the partial refund
            refundManager.partialAmountRefund(2, REFUND_DESCRIPTION);

            // verify that our refund now exists
            repoRefundManager.getFirstRefundStatusLabel().contains('Pending');
            repoRefundManager.getFirstRefundDescriptionLabel().contains(REFUND_DESCRIPTION);
            // because of (weird) number formats which might not be the same
            // all the time (even if they should) we just search within multiple formats
            elementHelper.assertContainsText(
                repoRefundManager.getFirstRefundAmountLabel(),
                ['2.00', '2,00']
            )

            // -------------------------------------------------------------------------------

            // now cancel our pending refund
            // and make sure that its gone afterwards
            refundManager.cancelPendingRefund();
            cy.contains(REFUND_DESCRIPTION).should('not.exist')
        })

    })
})


function createOrderAndOpenAdmin() {
    scenarioDummyBasket.execute();
    paymentAction.switchPaymentMethod('PayPal');

    shopware.prepareDomainChange();
    checkout.placeOrderOnConfirm();

    molliePayment.initSandboxCookie();
    molliePayment.selectPaid();

    adminLogin.login();
    adminOrders.openOrders();
    adminOrders.openLastOrder();
}
