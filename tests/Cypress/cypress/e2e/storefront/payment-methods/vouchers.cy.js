import Devices from "Services/utils/Devices";
import Session from "Services/utils/Session"
import Shopware from "Services/shopware/Shopware";
// ------------------------------------------------------
import ShopConfigurationAction from "Actions/admin/ShopConfigurationAction";
// ------------------------------------------------------
import PaymentAction from "Actions/storefront/checkout/PaymentAction";
import DummyBasketScenario from "Scenarios/DummyBasketScenario";
import CheckoutAction from "Actions/storefront/checkout/CheckoutAction";
import AdminLoginAction from "Actions/admin/AdminLoginAction";
import AdminProductsAction from "Actions/admin/AdminProductsAction";
import ProductDetailRepository from "Repositories/admin/products/ProductDetailRepository";
// ------------------------------------------------------
import MollieSandbox from "cypress-mollie/src/actions/MollieSandbox";
import PaymentScreenAction from "cypress-mollie/src/actions/screens/PaymentStatusScreen";
import DummyUserScenario from "Scenarios/DummyUserScenario";
import ListingAction from "Actions/storefront/products/ListingAction";
import PDPAction from "Actions/storefront/products/PDPAction";
import MollieProductsAction from "Actions/storefront/products/MollieProductsAction";
import ShopConfiguration from "../../../support/models/ShopConfiguration";
import PluginConfiguration from "../../../support/models/PluginConfiguration";


const devices = new Devices();
const session = new Session();
const shopware = new Shopware();

const configAction = new ShopConfigurationAction();
const paymentAction = new PaymentAction();
const checkout = new CheckoutAction();
const mollieSandbox = new MollieSandbox();
const molliePayment = new PaymentScreenAction();

const adminLogin = new AdminLoginAction();
const adminProducts = new AdminProductsAction();

const payment = new PaymentAction();

const listingAction = new ListingAction();
const pdpAction = new PDPAction();
const mollieProductsAction = new MollieProductsAction();

const repoProductDetailsAdmin = new ProductDetailRepository();


const testDevices = [devices.getFirstDevice()];
const scenarioDummyBasket = new DummyBasketScenario(1);
const scenarioDummyUser = new DummyUserScenario();

let beforeAllCalled = false;

function beforeEach(device) {
    cy.wrap(null).then(() => {
        if (!beforeAllCalled) {
            devices.setDevice(devices.getFirstDevice());
            const shopConfig = new ShopConfiguration();
            const pluginConfig = new PluginConfiguration();

            configAction.configureEnvironment(shopConfig, pluginConfig);
            beforeAllCalled = true;
        }
        devices.setDevice(device);
        session.resetBrowserSession();
    });
}


describe('Voucher Payments', () => {

    testDevices.forEach(device => {

        context(devices.getDescription(device), () => {

            it('C4144: Voucher Configuration available in Administration @core', () => {

                beforeEach(device);

                adminLogin.login();

                adminProducts.openProducts();
                adminProducts.openFirstProduct();

                adminProducts.openMollieTab();

                cy.contains('Voucher Payment');
                repoProductDetailsAdmin.getVoucherTypeDropdown().should('be.visible');
            })

            it('C4139: Voucher hidden if product is not configured', () => {

                beforeEach(device);

                // hiding of payment methods does not work
                // belo Shopware 6.4 in the way we have to do it (Storefront + API), so it's not supported
                if (shopware.isVersionLower(6.4)) {
                    return;
                }

                scenarioDummyUser.execute();
                mollieProductsAction.openRegularProduct();
                pdpAction.addToCart(1);
                checkout.goToCheckout();

                paymentAction.showPaymentMethods();

                cy.contains('.confirm-payment-shipping', 'Voucher').should('not.exist');

                // now also check the edit order page
                payment.switchPaymentMethod('PayPal');

                shopware.prepareDomainChange();
                checkout.placeOrderOnConfirm();

                mollieSandbox.initSandboxCookie();
                molliePayment.selectFailed();

                paymentAction.showPaymentMethods();

                cy.contains('.confirm-payment-shipping', 'Voucher').should('not.exist');
            })

            it('C4136: Voucher available for ECO products', () => {

                beforeEach(device);

                scenarioDummyUser.execute();
                mollieProductsAction.openEcoProduct();
                pdpAction.addToCart(1);

                testVoucherPayment();
            })

            it('C4137: Voucher available for MEAL products', () => {

                beforeEach(device);

                scenarioDummyUser.execute();
                mollieProductsAction.openMealProduct();
                pdpAction.addToCart(1);

                testVoucherPayment();
            })

            it('C4138: Voucher available for GIFT products', () => {

                beforeEach(device);

                scenarioDummyUser.execute();
                mollieProductsAction.openGiftProduct();
                pdpAction.addToCart(1);

                testVoucherPayment();
            })

        })
    })
})


function testVoucherPayment() {

    checkout.goToCheckout();

    paymentAction.switchPaymentMethod('Voucher');

    shopware.prepareDomainChange();
    checkout.placeOrderOnConfirm();

    // verify that we are on the mollie payment screen
    // and that our payment method is also visible somewhere in that url
    cy.url().should('include', 'https://www.mollie.com/checkout/');
    cy.url().should('include', 'voucher');
}
