import Devices from "Services/utils/Devices";
import Session from "Services/utils/Session"
import Shopware from "Services/shopware/Shopware"
// ------------------------------------------------------
import ShopConfigurationAction from "Actions/admin/ShopConfigurationAction";
// ------------------------------------------------------
import AdminLoginAction from "Actions/admin/AdminLoginAction";
import AdminProductsAction from "Actions/admin/AdminProductsAction";
import ProductDetailRepository from "Repositories/admin/products/ProductDetailRepository";
import DummyUserScenario from "Scenarios/DummyUserScenario";
import TopMenuAction from "Actions/storefront/navigation/TopMenuAction";
import ListingAction from "Actions/storefront/products/ListingAction";
import PDPAction from "Actions/storefront/products/PDPAction";
import CheckoutAction from "Actions/storefront/checkout/CheckoutAction";
import PaymentAction from "Actions/storefront/checkout/PaymentAction";
import PaymentScreenAction from "Actions/mollie/PaymentScreenAction";
import AdminOrdersAction from "Actions/admin/AdminOrdersAction";
import OrderDetailsRepository from "Repositories/admin/orders/OrderDetailsRepository";
import AdminSubscriptionsAction from "Actions/admin/AdminSubscriptionsAction";
import SubscriptionsListRepository from "Repositories/admin/subscriptions/SubscriptionsListRepository";


const devices = new Devices();
const session = new Session();
const shopware = new Shopware();

const repoProductDetailsAdmin = new ProductDetailRepository();
const repoOrdersDetails = new OrderDetailsRepository();
const repoAdminSubscriptions = new SubscriptionsListRepository();

const configAction = new ShopConfigurationAction();
const adminProducts = new AdminProductsAction();
const topMenu = new TopMenuAction();
const listing = new ListingAction();
const pdp = new PDPAction();
const checkout = new CheckoutAction();
const paymentAction = new PaymentAction();
const molliePayment = new PaymentScreenAction();
const adminOrders = new AdminOrdersAction();
const adminLogin = new AdminLoginAction();
const adminSubscriptions = new AdminSubscriptionsAction();


const dummyUserScenario = new DummyUserScenario();


const testDevices = [devices.getFirstDevice()];

describe('Subscription', () => {

    before(function () {
        devices.setDevice(devices.getFirstDevice());
        configAction.setupShop(true, false, false);
    })

    testDevices.forEach(device => {

        context(devices.getDescription(device), () => {

            beforeEach(() => {
                devices.setDevice(device);
                session.resetBrowserSession();
            });

            it('Subscription Configuration available in Administration', () => {

                adminLogin.login();

                adminProducts.openProducts();
                adminProducts.openFirstProduct();

                adminProducts.openMollieTab();

                cy.contains('Mollie Subscriptions');
                repoProductDetailsAdmin.getSubscriptionToggle().check();
            })

            it('Purchasing Subscription and verifying it in the Administration', () => {

                configAction.updateProducts('', true, 3, 'weeks');

                dummyUserScenario.execute();

                cy.visit('/');

                topMenu.clickOnClothing();
                listing.clickOnFirstProduct();

                // we have to see the subscription indicator
                // and the add to basket button should show that we can subscribe
                cy.contains('Subscription product');
                cy.contains('.btn', 'Subscribe');

                pdp.addToCart(2);

                // verify our warning information in our offcanvas
                cy.contains('Not all payments methods are available when ordering subscription products');

                checkout.goToCheckoutInOffCanvas();

                // verify our warning information on the cart page
                cy.contains('Not all payments methods are available when ordering subscription products');


                // now open our payment methods and verify
                // that some of them are not available
                // this is a check to at least see that it does something
                // we also verify that we see all available methods (just to also check if mollie is even configured correctly).
                if (shopware.isVersionGreaterEqual(6.4)) {
                    paymentAction.showAllPaymentMethods();
                } else {
                    paymentAction.openPaymentsModal();
                }

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

                if (shopware.isVersionLower(6.4)) {
                    paymentAction.closePaymentsModal();
                }

                paymentAction.switchPaymentMethod('Credit card');

                shopware.prepareDomainChange();
                checkout.placeOrderOnConfirm();

                molliePayment.initSandboxCookie();
                molliePayment.selectPaid();

                cy.url().should('include', '/checkout/finish');
                cy.contains('Thank you for your order');


                // log into administration
                // we verify that our latest order has a subscription badge
                // and that our subscription also exists
                adminLogin.login();

                adminOrders.openOrders();
                adminOrders.openLastOrder();
                repoOrdersDetails.getSubscriptionBadge().should('exist');

                // verify that we have found a new subscription entry
                // attention, this will not be 100% accurate if we have a persisting server
                // or multiple subscription tests, but for now it has to work
                adminSubscriptions.openSubscriptions();
                repoAdminSubscriptions.getLatestSubscription().should('exist');
            })
        })
    })
})

