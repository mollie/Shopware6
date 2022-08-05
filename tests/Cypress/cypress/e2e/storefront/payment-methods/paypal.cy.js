import Devices from "Services/utils/Devices";
import Session from "Services/utils/Session"
import Shopware from "Services/shopware/Shopware";
// ------------------------------------------------------
import ShopConfigurationAction from "Actions/admin/ShopConfigurationAction";
// ------------------------------------------------------
import PaymentAction from "Actions/storefront/checkout/PaymentAction";
import DummyBasketScenario from "Scenarios/DummyBasketScenario";
import CheckoutAction from "Actions/storefront/checkout/CheckoutAction";
import AdminOrdersAction from "Actions/admin/AdminOrdersAction";
import AdminLoginAction from "Actions/admin/AdminLoginAction";
import OrderDetailsRepository from "Repositories/admin/orders/OrderDetailsRepository";
// ------------------------------------------------------
import MollieSandbox from "cypress-mollie/src/actions/MollieSandbox";
import PaymentScreenAction from "cypress-mollie/src/actions/screens/PaymentStatusScreen";


const devices = new Devices();
const session = new Session();
const shopware = new Shopware();

const repoOrdersDetails = new OrderDetailsRepository();

const configAction = new ShopConfigurationAction();
const paymentAction = new PaymentAction();
const checkout = new CheckoutAction();
const adminOrders = new AdminOrdersAction();
const adminLogin = new AdminLoginAction();

const mollieSandbox = new MollieSandbox();
const molliePayment = new PaymentScreenAction();

const testDevices = [devices.getFirstDevice()];
const scenarioDummyBasket = new DummyBasketScenario(1);


describe('PayPal', () => {

    testDevices.forEach(device => {

        context(devices.getDescription(device), () => {

            beforeEach(() => {
                devices.setDevice(device);
                session.resetBrowserSession();
                configAction.setupShop(false, false, false);
                configAction.updateProducts('', false, 0, '');
            });

            it('C4112: Payment status "pending" leads to successful order', () => {

                scenarioDummyBasket.execute();

                paymentAction.switchPaymentMethod('PayPal');

                shopware.prepareDomainChange();
                checkout.placeOrderOnConfirm();

                mollieSandbox.initSandboxCookie();
                molliePayment.selectPending();

                cy.url().should('include', '/checkout/finish');
                cy.contains('Thank you for your order');
            })

            it('C4113: Paypal Reference Number is visible in Administration', () => {

                scenarioDummyBasket.execute();

                paymentAction.switchPaymentMethod('PayPal');

                shopware.prepareDomainChange();
                checkout.placeOrderOnConfirm();

                mollieSandbox.initSandboxCookie();
                molliePayment.selectPaid();

                adminLogin.login();

                adminOrders.openOrders();
                adminOrders.openLastOrder();

                repoOrdersDetails.getPaymentReferenceTitle().should('exist');
                repoOrdersDetails.getPaymentReferenceValue().should('exist');
            })

        })
    })
})

