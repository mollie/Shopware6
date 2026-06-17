import Devices from "Services/utils/Devices";
import Session from "Services/utils/Session"
import Element from "Services/utils/Element";
import Shopware from "Services/shopware/Shopware";
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
// ------------------------------------------------------
import MollieSandbox from "cypress-mollie/src/actions/MollieSandbox";
import PaymentScreenAction from "cypress-mollie/src/actions/screens/PaymentStatusScreen";
import ShopConfiguration from "../../../support/models/ShopConfiguration";
import PluginConfiguration from "../../../support/models/PluginConfiguration";


const devices = new Devices();
const session = new Session();
const elementHelper = new Element();
const shopware = new Shopware();


const configAction = new ShopConfigurationAction();
const checkout = new CheckoutAction();
const paymentAction = new PaymentAction();
const mollieSandbox = new MollieSandbox();
const molliePayment = new PaymentScreenAction();
const adminOrders = new AdminOrdersAction();
const adminLogin = new AdminLoginAction();
const refundManager = new RefundManagerAction();

const repoRefundManager = new RefundManagerRepository();

const scenarioDummyBasket = new DummyBasketScenario(10);

const device = devices.getFirstDevice();


let beforeAllCalled = false;

function beforeEach(device) {
    cy.wrap(null).then(() => {
        if (!beforeAllCalled) {
            const shopConfig = new ShopConfiguration();
            const pluginConfig = new PluginConfiguration();

            configAction.configureEnvironment(shopConfig, pluginConfig);
            beforeAllCalled = true;
        }
        session.resetBrowserSession();
        devices.setDevice(device);
    });
}


context("Order Refunds", () => {

    context(devices.getDescription(device), () => {

        it('C4046: Create full refund and cancel it @sanity', () => {

            beforeEach(device);

            createOrderAndOpenAdmin();

            const REFUND_DESCRIPTION = 'full refund with Cypress';
            const REFUND_INTERNAL_DESCRIPTION = 'refund done';

            // -------------------------------------------------------------------------------

            // open the refund manager
            // and start a partial refund of 2 EUR
            adminOrders.openRefundManager();

            // check if our button is disabled if
            // the checkbox for the verification is not enabled
            repoRefundManager.getFullRefundButton().should('be.disabled');

            // now start the partial refund
            refundManager.fullRefund(REFUND_DESCRIPTION, REFUND_INTERNAL_DESCRIPTION);

            // // verify that our refund now exists
            repoRefundManager.getFirstRefundStatusLabel().contains('Pending');

            repoRefundManager.getFirstRefundPublicDescriptionLabel().contains(REFUND_DESCRIPTION);
            repoRefundManager.getFirstRefundInternalDescriptionLabel().contains(REFUND_INTERNAL_DESCRIPTION);

            // -------------------------------------------------------------------------------

            // now cancel our pending refund
            // status should switch to Canceled, the entry remains visible
            refundManager.cancelPendingRefund();
            repoRefundManager.getFirstRefundStatusLabel().contains('Canceled');
        })


        it('C4045: Create partial refund and cancel it', () => {

            beforeEach(device);

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
            repoRefundManager.getFirstRefundPublicDescriptionLabel().contains(REFUND_DESCRIPTION);
            // because of (weird) number formats which might not be the same
            // all the time (even if they should) we just search within multiple formats
            elementHelper.assertContainsTexts(
                repoRefundManager.getFirstRefundAmountLabel(),
                ['2.00', '2,00']
            )

            // -------------------------------------------------------------------------------

            // now cancel our pending refund
            // status should switch to Canceled, the entry remains visible
            refundManager.cancelPendingRefund();
            repoRefundManager.getFirstRefundStatusLabel().contains('Canceled');
        })

        it('C139487: Overwrite total amount in full item refund', () => {

            beforeEach(device);

            createOrderAndOpenAdmin();

            const REFUND_DESCRIPTION = 'item refund with custom amount with Cypress';

            // -------------------------------------------------------------------------------

            // open the refund manager
            // and start a partial refund of 2 EUR
            adminOrders.openRefundManager();

            // click on SELECT ALL
            refundManager.selectAllItems();

            // now start the partial refund with a custom amount
            refundManager.partialAmountRefund(2, REFUND_DESCRIPTION);

            // -------------------------------------------------------------------------------

            repoRefundManager.getFirstRefundStatusLabel().contains('Pending');
            repoRefundManager.getFirstRefundPublicDescriptionLabel().contains(REFUND_DESCRIPTION);

            // verify that we have a valid composition (meaning item information)
            repoRefundManager.getFirstRefundCompositionLabel().contains('10 x');

            // verify our custom amount has been used
            elementHelper.assertContainsTexts(
                repoRefundManager.getFirstRefundAmountLabel(),
                ['2.00', '2,00']
            )

            // -------------------------------------------------------------------------------

            // now cancel our pending refund
            // status should switch to Canceled, the entry remains visible
            refundManager.cancelPendingRefund();
            repoRefundManager.getFirstRefundStatusLabel().contains('Canceled');
        })

        it('C273581: Canceled refunds remain visible with Canceled status', () => {

            beforeEach(device);

            createOrderAndOpenAdmin();

            const REFUND_DESCRIPTION = 'full refund executed twice with Cypress';

            // -------------------------------------------------------------------------------

            adminOrders.openRefundManager();

            repoRefundManager.getFullRefundButton().should('be.disabled');
            repoRefundManager.getFirstLineItemQuantityInput().should('be.visible');

            // first full refund
            refundManager.fullRefund(REFUND_DESCRIPTION, '');

            repoRefundManager.getFirstRefundStatusLabel().contains('Pending');
            repoRefundManager.getFirstRefundPublicDescriptionLabel().contains(REFUND_DESCRIPTION);

            // -------------------------------------------------------------------------------

            // cancel first refund → entry stays visible with Canceled status
            refundManager.cancelPendingRefund();
            repoRefundManager.getFirstRefundStatusLabel().contains('Canceled');

            // the refund input fields should still be visible after cancel
            repoRefundManager.getFirstLineItemQuantityInput().scrollIntoView().should('be.visible');

            // -------------------------------------------------------------------------------

            // second full refund → newest entry should show Pending
            refundManager.fullRefund(REFUND_DESCRIPTION, '');
            repoRefundManager.getFirstRefundStatusLabel().contains('Pending');

            // cancel second refund → also becomes Canceled
            refundManager.cancelPendingRefund();
            repoRefundManager.getFirstRefundStatusLabel().contains('Canceled');
        })

    })
})


function createOrderAndOpenAdmin() {
    scenarioDummyBasket.execute();
    paymentAction.switchPaymentMethod('PayPal');

    shopware.prepareDomainChange();
    checkout.placeOrderOnConfirm();

    mollieSandbox.initSandboxCookie();
    molliePayment.selectPaid();

    adminLogin.login();
    adminOrders.openOrders();
    adminOrders.openLastOrder();
}
