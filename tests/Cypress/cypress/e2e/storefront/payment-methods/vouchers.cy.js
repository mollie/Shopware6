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


const repoProductDetailsAdmin = new ProductDetailRepository();


const testDevices = [devices.getFirstDevice()];
const scenarioDummyBasket = new DummyBasketScenario(1);


describe('Voucher Payments', () => {

    before(function () {
        devices.setDevice(devices.getFirstDevice());
        configAction.setupShop(false, false, false);
        configAction.updateProducts('', false, 0, '');
    })

    testDevices.forEach(device => {

        context(devices.getDescription(device), () => {

            beforeEach(() => {
                devices.setDevice(device);
                session.resetBrowserSession();
            });

            it('C4144: Voucher Configuration available in Administration @core', () => {

                adminLogin.login();

                adminProducts.openProducts();
                adminProducts.openFirstProduct();

                adminProducts.openMollieTab();

                cy.contains('Voucher Payment');
                repoProductDetailsAdmin.getVoucherTypeDropdown().should('be.visible');
            })

            it('C4139: Voucher hidden if product is not configured @core', () => {

                // hiding of payment methods does not work
                // belo Shopware 6.4 in the way we have to do it (Storefront + API), so it's not supported
                if (shopware.isVersionLower(6.4)) {
                    return;
                }

                configAction.updateProducts('', false, '', '');

                scenarioDummyBasket.execute();

                if (shopware.isVersionGreaterEqual(6.4)) {
                    paymentAction.showAllPaymentMethods();
                } else {
                    paymentAction.openPaymentsModal();
                }

                cy.contains('.checkout-container', 'Voucher').should('not.exist');

                // now also check the edit order page
                payment.switchPaymentMethod('PayPal');

                shopware.prepareDomainChange();
                checkout.placeOrderOnConfirm();

                mollieSandbox.initSandboxCookie();
                molliePayment.selectFailed();

                if (shopware.isVersionGreaterEqual(6.4)) {
                    paymentAction.showAllPaymentMethods();
                } else {
                    paymentAction.openPaymentsModal();
                }

                cy.contains('.checkout-container', 'Voucher').should('not.exist');
            })

            it('C4136: Voucher available for ECO products', () => {
                configAction.updateProducts('eco', false, '', '');
                testVoucherPayment();
            })

            it('C4137: Voucher available for MEAL products', () => {
                configAction.updateProducts('meal', false, '', '');
                testVoucherPayment();
            })

            it('C4138: Voucher available for GIFT products', () => {
                configAction.updateProducts('gift', false, '', '');
                testVoucherPayment();
            })

        })
    })
})


/**
 *
 */
function testVoucherPayment() {
    scenarioDummyBasket.execute();

    paymentAction.switchPaymentMethod('Voucher');

    shopware.prepareDomainChange();
    checkout.placeOrderOnConfirm();

    // verify that we are on the mollie payment screen
    // and that our payment method is also visible somewhere in that url
    cy.url().should('include', 'https://www.mollie.com/checkout/');
    cy.url().should('include', 'voucher');
}
