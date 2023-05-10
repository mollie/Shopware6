import Devices from "Services/utils/Devices";
import ElementUtils from "Services/utils/Element"
import Session from "Services/utils/Session"
import Shopware from "Services/shopware/Shopware"
import VueJs from "Services/utils/VueJs/VueJs";
// ------------------------------------------------------
import ShopConfigurationAction from "Actions/admin/ShopConfigurationAction";
import AdminLoginAction from "Actions/admin/AdminLoginAction";
import AdminProductsAction from "Actions/admin/AdminProductsAction";
import ProductDetailRepository from "Repositories/admin/products/ProductDetailRepository";
import DummyUserScenario from "Scenarios/DummyUserScenario";
import TopMenuAction from "Actions/storefront/navigation/TopMenuAction";
import ListingAction from "Actions/storefront/products/ListingAction";
import PDPAction from "Actions/storefront/products/PDPAction";
import CheckoutAction from "Actions/storefront/checkout/CheckoutAction";
import PaymentAction from "Actions/storefront/checkout/PaymentAction";
import AdminOrdersAction from "Actions/admin/AdminOrdersAction";
import OrderDetailsRepository from "Repositories/admin/orders/OrderDetailsRepository";
import AdminSubscriptionsAction from "Actions/admin/AdminSubscriptionsAction";
import SubscriptionsListRepository from "Repositories/admin/subscriptions/SubscriptionsListRepository";
// ------------------------------------------------------
import MollieSandbox from "cypress-mollie/src/actions/MollieSandbox";
import PaymentScreenAction from "cypress-mollie/src/actions/screens/PaymentStatusScreen";
import CreditCardScreenAction from "cypress-mollie/src/actions/screens/CreditCardScreen";
import DummyBasketScenario from "Scenarios/DummyBasketScenario";
import SubscriptionDetailsRepository from "Repositories/admin/subscriptions/SubscriptionDetailsRepository";


const devices = new Devices();
const session = new Session();
const shopware = new Shopware();
const elementUtils = new ElementUtils();
const vueJs = new VueJs();


const repoProductDetailsAdmin = new ProductDetailRepository();
const repoOrdersDetails = new OrderDetailsRepository();
const repoAdminSubscriptions = new SubscriptionsListRepository();
const repoAdminSubscriptonDetails = new SubscriptionDetailsRepository();

const configAction = new ShopConfigurationAction();
const adminProducts = new AdminProductsAction();
const topMenu = new TopMenuAction();
const listing = new ListingAction();
const pdp = new PDPAction();
const checkout = new CheckoutAction();
const paymentAction = new PaymentAction();
const molliePayment = new PaymentScreenAction();
const mollieCreditCardForm = new CreditCardScreenAction();
const adminOrders = new AdminOrdersAction();
const adminLogin = new AdminLoginAction();
const adminSubscriptions = new AdminSubscriptionsAction();

const mollieSandbox = new MollieSandbox();

const dummyUserScenario = new DummyUserScenario();

const testDevices = [devices.getFirstDevice()];

describe('Subscription', () => {

    before(function () {
        devices.setDevice(devices.getFirstDevice());
    })

    testDevices.forEach(device => {

        context(devices.getDescription(device), () => {

            beforeEach(() => {
                devices.setDevice(device);
                session.resetBrowserSession();
            });

            describe('Storefront + Administration', function () {

                it('C4066: Purchase subscription and verify data in Administration', () => {

                    configAction.setupPlugin(true, false, false, true);
                    configAction.updateProducts('', true, 3, 'weeks');

                    dummyUserScenario.execute();
                    cy.visit('/');
                    topMenu.clickOnSecondCategory();
                    listing.clickOnFirstProduct();

                    // we have to see the subscription indicator
                    // and the add to basket button should show that we can subscribe
                    cy.contains('Subscription product');
                    cy.contains('.btn', 'Subscribe');
                    // we also want to see the translated interval
                    cy.contains('Every 3 weeks');

                    pdp.addToCart(2);

                    // ------------------------------------------------------------------------------------------------------

                    // verify our warning information in our offcanvas
                    cy.contains('Not all payments methods are available when ordering subscription products');

                    checkout.goToCheckoutInOffCanvas();

                    // ------------------------------------------------------------------------------------------------------

                    // verify our warning information on the cart page
                    cy.contains('Not all payments methods are available when ordering subscription products');
                    // we also want to see the translated interval
                    cy.contains('Every 3 weeks');

                    // now open our payment methods and verify
                    // that some of them are not available
                    // this is a check to at least see that it does something
                    // we also verify that we see all available methods (just to also check if mollie is even configured correctly).
                    if (shopware.isVersionGreaterEqual(6.4)) {
                        paymentAction.showAllPaymentMethods();
                    } else {
                        paymentAction.openPaymentsModal();
                    }

                    assertAvailablePaymentMethods();

                    if (shopware.isVersionLower(6.4)) {
                        paymentAction.closePaymentsModal();
                    }

                    paymentAction.switchPaymentMethod('Credit card');

                    shopware.prepareDomainChange();
                    checkout.placeOrderOnConfirm();

                    mollieSandbox.initSandboxCookie();
                    mollieCreditCardForm.enterValidCard();
                    mollieCreditCardForm.submitForm();
                    molliePayment.selectPaid();

                    cy.url().should('include', '/checkout/finish');
                    cy.contains('Thank you for your order');


                    // ------------------------------------------------------------------------------------------------------

                    adminLogin.login();
                    adminOrders.openOrders();
                    adminOrders.openLastOrder();

                    // our latest order must have a subscription "badge"
                    repoOrdersDetails.getSubscriptionBadge().should('exist');

                    // ------------------------------------------------------------------------------------------------------

                    // verify that we have found a new subscription entry
                    // attention, this will not be 100% accurate if we have a persisting server
                    // or multiple subscription tests, but for now it has to work
                    adminSubscriptions.openSubscriptions();
                    adminSubscriptions.openSubscription(0);

                    // ------------------------------------------------------------------------------------------------------

                    repoAdminSubscriptonDetails.getMollieCustomerIdField().should('be.visible');

                    vueJs.textField(repoAdminSubscriptonDetails.getMollieCustomerIdField()).containsValue('cst_');
                    vueJs.textField(repoAdminSubscriptonDetails.getCreatedAtField()).notEmptyValue();

                    vueJs.textField(repoAdminSubscriptonDetails.getStatusField()).equalsValue('Active');
                    vueJs.textField(repoAdminSubscriptonDetails.getCanceledAtField()).emptyValue();
                    vueJs.textField(repoAdminSubscriptonDetails.getMollieSubscriptionIdField()).containsValue('sub_');
                    vueJs.textField(repoAdminSubscriptonDetails.getMandateField()).containsValue('mdt_');
                    vueJs.textField(repoAdminSubscriptonDetails.getNextPaymentAtField()).notEmptyValue();
                    vueJs.textField(repoAdminSubscriptonDetails.getLastRemindedAtField()).emptyValue();

                    // just do a contains, because card-titles are just different
                    // across shopware versions, and in the end, we just need to make sure we see this exact string
                    cy.contains("History (2)");

                    // oldest history entry
                    cy.contains(repoAdminSubscriptonDetails.getHistoryStatusToSelector(1), 'pending', {matchCase: false});
                    cy.contains(repoAdminSubscriptonDetails.getHistoryCommentSelector(1), 'created');
                    // latest history entry
                    cy.contains(repoAdminSubscriptonDetails.getHistoryStatusFromSelector(0), 'pending', {matchCase: false});
                    cy.contains(repoAdminSubscriptonDetails.getHistoryStatusToSelector(0), 'active', {matchCase: false});
                    cy.contains(repoAdminSubscriptonDetails.getHistoryCommentSelector(0), 'confirmed');
                })

            });

            describe('Administration', () => {

                it('C4065: Subscription Configuration available in Administration @core', () => {

                    configAction.setupShop(true, false, false);

                    adminLogin.login();

                    adminProducts.openProducts();
                    adminProducts.openFirstProduct();

                    adminProducts.openMollieTab();

                    cy.contains('Mollie Subscriptions');
                    repoProductDetailsAdmin.getSubscriptionToggle().check();
                })

                it('C183210: Subscription page in Administration has links to customer and order', () => {

                    prepareSubscriptionAndOpenDetails();

                    // --------------------------------------------------------------------------------------------------

                    cy.url().should('include', '/subscription/detail');

                    cy.contains('Show Shopware customer').click();
                    cy.url().should('include', '/customer/detail');

                    cy.go('back')

                    cy.contains('Show Shopware order').click();
                    cy.url().should('include', '/order/detail');
                    cy.contains('Subscription Order', {matchCase: false}).click();

                    cy.url().should('include', '/subscription/detail');
                })

                it('C183206: Pause subscription in Administration', () => {

                    prepareSubscriptionAndOpenDetails();

                    repoAdminSubscriptonDetails.getStatusField().should('be.visible');
                    vueJs.textField(repoAdminSubscriptonDetails.getStatusField()).equalsValue('Active');

                    repoAdminSubscriptonDetails.getPauseButton().click();
                    repoAdminSubscriptonDetails.getConfirmButton().click();
                    cy.wait(2000);

                    repoAdminSubscriptonDetails.getStatusField().should('be.visible');
                    vueJs.textField(repoAdminSubscriptonDetails.getStatusField()).equalsValue('Paused');

                    cy.contains(repoAdminSubscriptonDetails.getHistoryStatusFromSelector(0), 'active', {matchCase: false});
                    cy.contains(repoAdminSubscriptonDetails.getHistoryStatusToSelector(0), 'paused', {matchCase: false});
                    cy.contains(repoAdminSubscriptonDetails.getHistoryCommentSelector(0), 'paused');
                })

                it('C183208: Resume subscription in Administration', () => {

                    prepareSubscriptionAndOpenDetails();

                    vueJs.textField(repoAdminSubscriptonDetails.getStatusField()).equalsValue('Active');

                    repoAdminSubscriptonDetails.getPauseButton().click();
                    repoAdminSubscriptonDetails.getConfirmButton().click();
                    cy.wait(2000);

                    repoAdminSubscriptonDetails.getStatusField().should('be.visible');
                    vueJs.textField(repoAdminSubscriptonDetails.getStatusField()).equalsValue('Paused');

                    repoAdminSubscriptonDetails.getResumeButton().click();
                    repoAdminSubscriptonDetails.getConfirmButton().click();
                    cy.wait(2000);

                    cy.contains(repoAdminSubscriptonDetails.getHistoryStatusFromSelector(0), 'paused', {matchCase: false});
                    cy.contains(repoAdminSubscriptonDetails.getHistoryStatusToSelector(0), 'resumed', {matchCase: false});
                    cy.contains(repoAdminSubscriptonDetails.getHistoryCommentSelector(0), 'resumed');
                })

                it('C183207: Skip subscription in Administration', () => {

                    prepareSubscriptionAndOpenDetails();

                    vueJs.textField(repoAdminSubscriptonDetails.getStatusField()).equalsValue('Active');

                    repoAdminSubscriptonDetails.getSkipButton().click();
                    repoAdminSubscriptonDetails.getConfirmButton().click();
                    cy.wait(2000);

                    repoAdminSubscriptonDetails.getStatusField().should('be.visible');
                    vueJs.textField(repoAdminSubscriptonDetails.getStatusField()).equalsValue('Skipped');

                    cy.contains(repoAdminSubscriptonDetails.getHistoryStatusFromSelector(0), 'active', {matchCase: false});
                    cy.contains(repoAdminSubscriptonDetails.getHistoryStatusToSelector(0), 'skipped', {matchCase: false});
                    cy.contains(repoAdminSubscriptonDetails.getHistoryCommentSelector(0), 'skipped');
                })

                it('C183209: Cancel subscription in Administration', () => {

                    prepareSubscriptionAndOpenDetails();

                    repoAdminSubscriptonDetails.getStatusField().should('be.visible');
                    vueJs.textField(repoAdminSubscriptonDetails.getStatusField()).equalsValue('Active');

                    repoAdminSubscriptonDetails.getCancelButton().click();
                    repoAdminSubscriptonDetails.getConfirmButton().click();
                    cy.wait(2000);

                    repoAdminSubscriptonDetails.getStatusField().should('be.visible');
                    vueJs.textField(repoAdminSubscriptonDetails.getStatusField()).equalsValue('Canceled');

                    cy.contains(repoAdminSubscriptonDetails.getHistoryStatusFromSelector(0), 'active', {matchCase: false});
                    cy.contains(repoAdminSubscriptonDetails.getHistoryStatusToSelector(0), 'canceled', {matchCase: false});
                    cy.contains(repoAdminSubscriptonDetails.getHistoryCommentSelector(0), 'cancel');
                })
            })

            describe('Storefront', () => {

                it('C4067: Subscription Indicator on PDP can be turned ON @core', () => {

                    configAction.updateProducts('', true, 3, 'weeks');
                    configAction.setupPlugin(true, false, false, true);
                    cy.wait(2000);

                    cy.visit('/');
                    topMenu.clickOnSecondCategory();
                    listing.clickOnFirstProduct();

                    // we have to see the subscription indicator
                    cy.contains('Subscription product');
                    // we also want to see the translated interval
                    cy.contains('Every 3 weeks');
                })

                it('C4068: Subscription Indicator on PDP can be turned OFF @core', () => {

                    configAction.updateProducts('', true, 3, 'weeks');
                    configAction.setupPlugin(true, false, false, false);
                    cy.wait(2000);

                    cy.visit('/');

                    topMenu.clickOnSecondCategory();
                    listing.clickOnFirstProduct();

                    cy.contains('Subscription product').should('not.exist');
                })

                it('C4077: Subscription Payment methods are limited on editOrder page', () => {

                    // hiding of payment methods does not work
                    // belo Shopware 6.4 in the way we have to do it (Storefront + API), so it's not supported
                    if (shopware.isVersionLower(6.4)) {
                        return;
                    }

                    configAction.setupShop(false, false, false);

                    configAction.updateProducts('', true, 3, 'weeks');

                    dummyUserScenario.execute();

                    cy.visit('/');

                    topMenu.clickOnSecondCategory();
                    listing.clickOnFirstProduct();

                    cy.contains('.btn', 'Subscribe');

                    pdp.addToCart(1);

                    checkout.goToCheckoutInOffCanvas();

                    // now open our payment methods and verify
                    // that some of them are not available
                    // this is a check to at least see that it does something
                    // we also verify that we see all available methods (just to also check if mollie is even configured correctly).
                    if (shopware.isVersionGreaterEqual(6.4)) {
                        paymentAction.showAllPaymentMethods();
                    } else {
                        paymentAction.openPaymentsModal();
                    }

                    paymentAction.switchPaymentMethod('Credit card');

                    shopware.prepareDomainChange();
                    checkout.placeOrderOnConfirm();

                    mollieSandbox.initSandboxCookie();
                    mollieCreditCardForm.enterValidCard();
                    mollieCreditCardForm.submitForm();
                    molliePayment.selectFailed();

                    if (shopware.isVersionGreaterEqual(6.4)) {
                        paymentAction.showAllPaymentMethods();
                    } else {
                        paymentAction.openPaymentsModal();
                    }

                    assertAvailablePaymentMethods();
                })

                it('C176306: Subscriptions are available in Account', () => {

                    prepareSubscriptionAndOpenDetails();

                    cy.visit('/');
                    topMenu.clickAccountWidgetSubscriptions();

                    // side menu needs subscription
                    cy.wait(2000);
                    cy.contains('.account-aside', 'Subscriptions');
                    // we should at least find 1 subscription
                    cy.get('.account-order-overview').find('.order-table').should('have.length.greaterThan', 0);
                })

            })
        })
    })
})


function assertAvailablePaymentMethods() {
    cy.contains('Pay later').should('not.exist');
    cy.contains('paysafecard').should('not.exist');

    cy.contains('iDEAL').should('exist');
    cy.contains('Credit card').should('exist');
    cy.contains('SOFORT').should('exist');
    cy.contains('eps').should('exist');
    cy.contains('Bancontact').should('exist');
    cy.contains('Belfius').should('exist');
    cy.contains('Giropay').should('exist');
    cy.contains('PayPal').should('exist');
}

function prepareSubscriptionAndOpenDetails() {
    configAction.setupPlugin(true, false, false, true);
    configAction.updateProducts('', true, 3, 'weeks');

    const dummyScenario = new DummyBasketScenario(1)
    dummyScenario.execute();

    paymentAction.switchPaymentMethod('Credit card');
    shopware.prepareDomainChange();
    checkout.placeOrderOnConfirm();

    mollieSandbox.initSandboxCookie();
    cy.wait(1000);
    mollieCreditCardForm.enterValidCard();
    mollieCreditCardForm.submitForm();
    molliePayment.selectPaid();

    adminLogin.login();
    adminSubscriptions.openSubscriptions();
    adminSubscriptions.openSubscription(0);

}