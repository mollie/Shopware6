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

const scenarioDummyBasket = new DummyBasketScenario(1);

const device = devices.getFirstDevice();


context("Order Refunds", () => {

    before(function () {
        configAction.setupShop(false, false, false);
        configAction.updateProducts('', false, 0, '');
    })

    beforeEach(() => {
        session.resetBrowserSession();
        devices.setDevice(device);
    });

    context(devices.getDescription(device), () => {

        it('C4046: Create full refund and cancel it', () => {

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

            // verify that our refund now exists
            repoRefundManager.getFirstRefundStatusLabel().contains('Pending');

            repoRefundManager.getFirstRefundPublicDescriptionLabel().contains(REFUND_DESCRIPTION);
            repoRefundManager.getFirstRefundInternalDescriptionLabel().contains(REFUND_INTERNAL_DESCRIPTION);

            // -------------------------------------------------------------------------------

            // now cancel our pending refund
            // and make sure that its gone afterwards
            refundManager.cancelPendingRefund();
            cy.contains(REFUND_DESCRIPTION).should('not.exist')
        })


        it('C4045: Create partial refund and cancel it', () => {

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
            // and make sure that its gone afterwards
            refundManager.cancelPendingRefund();
            cy.contains(REFUND_DESCRIPTION).should('not.exist')
        })

        it('C139487: Overwrite total amount in full item refund', () => {

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
            repoRefundManager.getFirstRefundCompositionLabel().contains('1 x');

            // verify our custom amount has been used
            elementHelper.assertContainsTexts(
                repoRefundManager.getFirstRefundAmountLabel(),
                ['2.00', '2,00']
            )

            // -------------------------------------------------------------------------------

            // now cancel our pending refund
            // and make sure that its gone afterwards
            refundManager.cancelPendingRefund();
            cy.contains(REFUND_DESCRIPTION).should('not.exist')
        })

        // TODO attention this is skipped because of a bug in Mollie. they dont' clear up deleted refunds. line items are still refunded.
        it.skip('C273581: Canceled refunds should not be visible', () => {

            createOrderAndOpenAdmin();

            const REFUND_DESCRIPTION = 'full refund executed twice with Cypress';
            const CANCELED_REFUND_STATUS_LABEL = 'mollie-payments.refunds.status.canceled';
            // -------------------------------------------------------------------------------

            // open the refund manager
            // and start a partial refund of 2 EUR
            adminOrders.openRefundManager();

            // check if our button is disabled if
            // the checkbox for the verification is not enabled
            repoRefundManager.getFullRefundButton().should('be.disabled');

            // check if refund quantity input field is visible
            repoRefundManager.getFirstRefundedQuantityInputField().should('be.visible');

            // now start the full refund
            refundManager.fullRefund(REFUND_DESCRIPTION, '');

            // verify that our refund now exists
            repoRefundManager.getFirstRefundStatusLabel().contains('Pending');
            repoRefundManager.getFirstRefundPublicDescriptionLabel().contains(REFUND_DESCRIPTION);

            // -------------------------------------------------------------------------------

            // now cancel our pending refund
            // and make sure that its gone afterwards
            refundManager.cancelPendingRefund();

            // after cancel, the refund input field should be visible again
            repoRefundManager.getFirstRefundedQuantityInputField().should('be.visible');

            // now start the partial refund
            refundManager.partialAmountRefund(2, REFUND_DESCRIPTION);

            cy.contains(CANCELED_REFUND_STATUS_LABEL).should('not.exist');

            // second cancel should clear the history
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

    mollieSandbox.initSandboxCookie();
    molliePayment.selectPaid();

    adminLogin.login();
    adminOrders.openOrders();
    adminOrders.openLastOrder();
}
