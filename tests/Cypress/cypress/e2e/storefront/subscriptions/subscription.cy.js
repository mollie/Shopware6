import Devices from "Services/utils/Devices";
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
// ------------------------------------------------------
import MollieSandbox from "cypress-mollie/src/actions/MollieSandbox";
import PaymentScreenAction from "cypress-mollie/src/actions/screens/PaymentStatusScreen";
import CreditCardScreenAction from "cypress-mollie/src/actions/screens/CreditCardScreen";
import DummyBasketScenario from "Scenarios/DummyBasketScenario";
import SubscriptionDetailsRepository from "Repositories/admin/subscriptions/SubscriptionDetailsRepository";
import SubscriptionRepository from "Repositories/storefront/account/SubscriptionRepository";
import MollieProductsAction from "Actions/storefront/products/MollieProductsAction";
import ShopConfiguration from "../../../support/models/ShopConfiguration";
import PluginConfiguration from "../../../support/models/PluginConfiguration";


const devices = new Devices();
const session = new Session();
const shopware = new Shopware();
const vueJs = new VueJs();


const repoProductDetailsAdmin = new ProductDetailRepository();
const repoOrdersDetails = new OrderDetailsRepository();
const repoAdminSubscriptionDetails = new SubscriptionDetailsRepository();
const repoSubscriptionStorefront = new SubscriptionRepository();

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
const mollieProductsAction = new MollieProductsAction();

const mollieSandbox = new MollieSandbox();

const dummyUserScenario = new DummyUserScenario();

const testDevices = [devices.getFirstDevice()];


let beforeAllCalled = false;

function beforeEach(device) {
    cy.wrap(null).then(() => {
        if (!beforeAllCalled) {

            const shopConfig = new ShopConfiguration();

            const pluginConfig = new PluginConfiguration();
            pluginConfig.setSubscriptionIndicator(true);

            configAction.configureEnvironment(shopConfig, pluginConfig);

            beforeAllCalled = true;
        }
        devices.setDevice(device);
        session.resetBrowserSession();
    });
}


describe('Subscription', () => {

    testDevices.forEach(device => {

        context(devices.getDescription(device), () => {

            describe('Storefront + Administration', function () {

                it('C2339889: Purchase subscription after failed payment and verify data in Administration', () => {

                    beforeEach(device);

                    purchaseSubscriptionAndGoToPayment();

                    molliePayment.selectFailed();

                    cy.url().should('include', '/order/edit');

                    paymentAction.switchPaymentMethod('Card');

                    checkout.placeOrderOnEdit();

                    mollieSandbox.initSandboxCookie();
                    mollieCreditCardForm.enterValidCard();
                    mollieCreditCardForm.submitForm();
                    molliePayment.selectPaid();

                    assertValidSubscriptionInAdmin();
                })

                it('C4066: Purchase subscription and verify data in Administration', () => {

                    beforeEach(device);

                    purchaseSubscriptionAndGoToPayment();

                    molliePayment.selectPaid();

                    assertValidSubscriptionInAdmin();
                })

            });

            describe('Administration', () => {

                it('C4065: Subscription Configuration available in Administration @core', () => {

                    beforeEach(device);

                    adminLogin.login();

                    adminProducts.openProducts();
                    adminProducts.openFirstProduct();

                    adminProducts.openMollieTab();

                    cy.contains('Mollie Subscriptions');
                    repoProductDetailsAdmin.getSubscriptionToggle().check();
                })

                it('C183210: Subscription page in Administration has links to customer and order', () => {

                    beforeEach(device);

                    buySubscriptionAndOpenAdminDetails();

                    // --------------------------------------------------------------------------------------------------

                    cy.url().should('include', '/subscription/detail');

                    cy.contains('Show Shopware customer').click();
                    cy.url().should('include', '/customer/detail');

                    cy.go('back');

                    cy.contains('Show Shopware order').click();
                    cy.url().should('include', '/order/detail');
                    cy.contains('Subscription Order', {matchCase: false}).click();

                    cy.url().should('include', '/subscription/detail');
                })

                it('C183206: Pause subscription in Administration', () => {

                    beforeEach(device);

                    buySubscriptionAndOpenAdminDetails();

                    repoAdminSubscriptionDetails.getStatusField().should('be.visible');
                    vueJs.textField(repoAdminSubscriptionDetails.getStatusField()).equalsValue('Active');

                    repoAdminSubscriptionDetails.getPauseButton().click();
                    repoAdminSubscriptionDetails.getConfirmButton().click();

                    repoAdminSubscriptionDetails.getStatusField().should('be.visible');
                    vueJs.textField(repoAdminSubscriptionDetails.getStatusField()).equalsValue('Paused');

                    cy.contains(repoAdminSubscriptionDetails.getHistoryStatusFromSelector(0), 'active', {matchCase: false});
                    cy.contains(repoAdminSubscriptionDetails.getHistoryStatusToSelector(0), 'paused', {matchCase: false});
                    cy.contains(repoAdminSubscriptionDetails.getHistoryCommentSelector(0), 'paused');
                })

                it('C183208: Resume subscription in Administration', () => {

                    beforeEach(device);

                    buySubscriptionAndOpenAdminDetails();

                    vueJs.textField(repoAdminSubscriptionDetails.getStatusField()).equalsValue('Active');

                    repoAdminSubscriptionDetails.getPauseButton().click();
                    repoAdminSubscriptionDetails.getConfirmButton().click();

                    repoAdminSubscriptionDetails.getStatusField().should('be.visible');
                    vueJs.textField(repoAdminSubscriptionDetails.getStatusField()).equalsValue('Paused');

                    repoAdminSubscriptionDetails.getResumeButton().click();
                    repoAdminSubscriptionDetails.getConfirmButton().click();

                    cy.contains(repoAdminSubscriptionDetails.getHistoryStatusFromSelector(0), 'paused', {timeout: 10000, matchCase: false});
                    cy.contains(repoAdminSubscriptionDetails.getHistoryStatusToSelector(0), 'resumed', {matchCase: false});
                    cy.contains(repoAdminSubscriptionDetails.getHistoryCommentSelector(0), 'resumed');
                })

                it('C183207: Skip subscription in Administration', () => {

                    beforeEach(device);

                    buySubscriptionAndOpenAdminDetails();

                    vueJs.textField(repoAdminSubscriptionDetails.getStatusField()).equalsValue('Active');

                    repoAdminSubscriptionDetails.getSkipButton().click();
                    repoAdminSubscriptionDetails.getConfirmButton().click();

                    repoAdminSubscriptionDetails.getStatusField().should('be.visible');
                    vueJs.textField(repoAdminSubscriptionDetails.getStatusField()).equalsValue('Skipped');

                    cy.contains(repoAdminSubscriptionDetails.getHistoryStatusFromSelector(0), 'active', {matchCase: false});
                    cy.contains(repoAdminSubscriptionDetails.getHistoryStatusToSelector(0), 'skipped', {matchCase: false});
                    cy.contains(repoAdminSubscriptionDetails.getHistoryCommentSelector(0), 'skipped');
                })

                it('C183209: Cancel subscription in Administration', () => {

                    beforeEach(device);

                    buySubscriptionAndOpenAdminDetails();

                    repoAdminSubscriptionDetails.getStatusField().should('be.visible');
                    vueJs.textField(repoAdminSubscriptionDetails.getStatusField()).equalsValue('Active');

                    repoAdminSubscriptionDetails.getCancelButton().click();
                    repoAdminSubscriptionDetails.getConfirmButton().click();

                    repoAdminSubscriptionDetails.getStatusField().should('be.visible');
                    vueJs.textField(repoAdminSubscriptionDetails.getStatusField()).equalsValue('Canceled');

                    cy.contains(repoAdminSubscriptionDetails.getHistoryStatusFromSelector(0), 'active', {matchCase: false});
                    cy.contains(repoAdminSubscriptionDetails.getHistoryStatusToSelector(0), 'canceled', {matchCase: false});
                    cy.contains(repoAdminSubscriptionDetails.getHistoryCommentSelector(0), 'cancel');
                })
            })

            describe('Storefront', () => {

                it('C4067: Subscription Indicator on PDP can be turned ON @core', () => {

                    beforeEach(device);

                    cy.wrap(null).then(() => {
                        const pluginConfig = new PluginConfiguration();
                        pluginConfig.setSubscriptionIndicator(true);
                        configAction.configurePlugin(pluginConfig);
                    });

                    mollieProductsAction.openSubscriptionProduct_Weekly3();

                    // we have to see the subscription indicator
                    cy.contains('Subscription product');
                    // we also want to see the translated interval
                    cy.contains('Every 3 weeks');
                })

                it('C4068: Subscription Indicator on PDP can be turned OFF @core', () => {

                    beforeEach(device);

                    cy.wrap(null).then(() => {
                        const pluginConfig = new PluginConfiguration();
                        pluginConfig.setSubscriptionIndicator(false);
                        configAction.configurePlugin(pluginConfig);
                    });

                    mollieProductsAction.openSubscriptionProductDaily1();

                    cy.contains('Subscription product').should('not.exist');
                })

                it('C4077: Subscription Payment methods are limited on editOrder page', () => {

                    // hiding of payment methods does not work
                    // belo Shopware 6.4 in the way we have to do it (Storefront + API), so it's not supported
                    if (shopware.isVersionLower(6.4)) {
                        return;
                    }

                    beforeEach(device);

                    cy.wrap(null).then(() => {
                        const shopConfig = new ShopConfiguration();
                        const pluginConfig = new PluginConfiguration();

                        configAction.configureEnvironment(shopConfig, pluginConfig);
                    });

                    dummyUserScenario.execute();

                    mollieProductsAction.openSubscriptionProduct_Weekly3();

                    cy.contains('.btn', 'Subscribe');

                    pdp.addToCart(1);

                    checkout.goToCheckoutInOffCanvas();

                    // now open our payment methods and verify
                    // that some of them are not available
                    // this is a check to at least see that it does something
                    // we also verify that we see all available methods (just to also check if mollie is even configured correctly).
                    paymentAction.showPaymentMethods();

                    paymentAction.switchPaymentMethod('Card');

                    shopware.prepareDomainChange();
                    checkout.placeOrderOnConfirm();

                    mollieSandbox.initSandboxCookie();
                    mollieCreditCardForm.enterValidCard();
                    mollieCreditCardForm.submitForm();
                    molliePayment.selectFailed();

                    paymentAction.showPaymentMethods();

                    assertAvailablePaymentMethods();
                })

                it('C176306: Subscriptions are available in Account', () => {

                    beforeEach(device);

                    buySubscription();

                    cy.visit('/');
                    topMenu.clickAccountWidgetSubscriptions();

                    // side menu needs subscription item
                    cy.contains('.account-aside', 'Subscriptions', {timeout: 10000});

                    // we should at least find 1 subscription
                    cy.get('.account-order-overview').find('.order-table').should('have.length.greaterThan', 0);

                    // the javascript was not working, there is a toggle button that switches between "View" and "Hide".
                    // let's make sure it's working
                    cy.contains('View');

                    repoSubscriptionStorefront.getSubscriptionViewButton(0).click();

                    // now the toggle button should show "Hide"
                    cy.contains('Hide');

                    cy.contains('edit billing address');

                    repoSubscriptionStorefront.getSubscriptionContextMenuButton(0).click();
                    cy.contains('Repeat subscription');
                })

                it('C4237799: Accessibility Storefront Account Subscriptions @a11y', () => {
                    beforeEach(device);

                    buySubscription();

                    cy.visit('/');
                    topMenu.clickAccountWidgetSubscriptions();

                    cy.injectAxe();

                    // test initial page
                    cy.checkA11y('.account-content-main');

                    // test billing address modal
                    repoSubscriptionStorefront.getSubscriptionViewButton(0).click();
                    repoSubscriptionStorefront.getSubscriptionEditBillingAddressButton(0).click();
                    cy.checkA11y('.account-content-main');

                    // close our modal
                    repoSubscriptionStorefront.getSubscriptionEditBillingAddressModalSaveButton(0).click();

                    // the save button leads to a reload
                    cy.injectAxe();

                    // test shipping address modal
                    repoSubscriptionStorefront.getSubscriptionViewButton(0).click();
                    repoSubscriptionStorefront.getSubscriptionEditShippingAddressModal(0).click();
                    cy.checkA11y('.account-content-main');

                    // close our modal
                    repoSubscriptionStorefront.getSubscriptionEditShippingAddressModalSaveButton(0).click();
                });

            })
        })
    })
})


function purchaseSubscriptionAndGoToPayment() {

    dummyUserScenario.execute();

    mollieProductsAction.openSubscriptionProduct_Weekly3();

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
    paymentAction.showPaymentMethods();

    assertAvailablePaymentMethods();

    paymentAction.switchPaymentMethod('Card');


    shopware.prepareDomainChange();
    checkout.placeOrderOnConfirm();

    mollieSandbox.initSandboxCookie();
    mollieCreditCardForm.enterValidCard();
    mollieCreditCardForm.submitForm();
}

function buySubscription() {

    const dummyUserScenario = new DummyUserScenario();
    dummyUserScenario.execute();

    mollieProductsAction.openSubscriptionProduct_Weekly3();
    pdp.addToCart(1);

    checkout.goToCheckout();
    paymentAction.switchPaymentMethod('Card');
    shopware.prepareDomainChange();
    checkout.placeOrderOnConfirm();

    mollieSandbox.initSandboxCookie();
    cy.wait(1000);
    mollieCreditCardForm.enterValidCard();
    mollieCreditCardForm.submitForm();
    molliePayment.selectPaid();
}

function buySubscriptionAndOpenAdminDetails() {

    buySubscription();

    adminLogin.login();
    adminSubscriptions.openSubscriptions();
    adminSubscriptions.openSubscription(0);
}

function assertAvailablePaymentMethods() {
    cy.get('.payment-methods input.klarnapaylater').should('not.exist');
    cy.get('.payment-methods input.paysafecard').should('not.exist');

    cy.contains('iDEAL').should('exist');
    cy.contains('Card').should('exist');
    cy.contains('EPS').should('exist');
    cy.contains('Bancontact').should('exist');
    cy.contains('Belfius').should('exist');
    cy.contains('PayPal').should('exist');
}

function assertValidSubscriptionInAdmin() {
    cy.url().should('include', '/checkout/finish');
    cy.contains('Thank you');
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

    repoAdminSubscriptionDetails.getMollieCustomerIdField().should('be.visible');

    vueJs.textField(repoAdminSubscriptionDetails.getMollieCustomerIdField()).containsValue('cst_');
    vueJs.textField(repoAdminSubscriptionDetails.getCreatedAtField()).notEmptyValue();

    vueJs.textField(repoAdminSubscriptionDetails.getStatusField()).equalsValue('Active');
    vueJs.textField(repoAdminSubscriptionDetails.getCanceledAtField()).emptyValue();
    vueJs.textField(repoAdminSubscriptionDetails.getMollieSubscriptionIdField()).containsValue('sub_');
    vueJs.textField(repoAdminSubscriptionDetails.getMandateField()).containsValue('mdt_');
    vueJs.textField(repoAdminSubscriptionDetails.getNextPaymentAtField()).notEmptyValue();
    vueJs.textField(repoAdminSubscriptionDetails.getLastRemindedAtField()).emptyValue();

    // just do a contains, because card-titles are just different
    // across shopware versions, and in the end, we just need to make sure we see this exact string
    cy.contains("History (2)");

    // oldest history entry
    cy.contains(repoAdminSubscriptionDetails.getHistoryStatusToSelector(1), 'pending', {matchCase: false});
    cy.contains(repoAdminSubscriptionDetails.getHistoryCommentSelector(1), 'created');
    // latest history entry
    cy.contains(repoAdminSubscriptionDetails.getHistoryStatusFromSelector(0), 'pending', {matchCase: false});
    cy.contains(repoAdminSubscriptionDetails.getHistoryStatusToSelector(0), 'active', {matchCase: false});
    cy.contains(repoAdminSubscriptionDetails.getHistoryCommentSelector(0), 'confirmed');
}
