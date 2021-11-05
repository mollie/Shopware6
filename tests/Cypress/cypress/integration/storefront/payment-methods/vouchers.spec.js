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


const devices = new Devices();
const session = new Session();
const shopware = new Shopware();

const configAction = new ShopConfigurationAction();
const paymentAction = new PaymentAction();
const checkout = new CheckoutAction();

const adminLogin = new AdminLoginAction();
const adminProducts = new AdminProductsAction();

const repoProductDetailsAdmin = new ProductDetailRepository();


const testDevices = [devices.getFirstDevice()];
const scenarioDummyBasket = new DummyBasketScenario(1);


describe('Voucher Payments', () => {

    before(function () {
        devices.setDevice(devices.getFirstDevice());
        configAction.setupShop(true, false);
    })

    testDevices.forEach(device => {

        context(devices.getDescription(device), () => {

            beforeEach(() => {
                devices.setDevice(device);
                session.resetBrowserSession();
            });

            it('Voucher Configuration available in Administration', () => {

                adminLogin.login();

                adminProducts.openProducts();
                adminProducts.openFirstProduct();

                adminProducts.openMollieTab();

                cy.contains('Voucher Payment');
                repoProductDetailsAdmin.getVoucherTypeDropdown().should('be.visible');
            })

            it('Voucher hidden if product is not configured', () => {

                configAction.updateProducts('');

                scenarioDummyBasket.execute();

                if (shopware.isVersionGreaterEqual(6.4)) {
                    paymentAction.showAllPaymentMethods();
                } else {
                    paymentAction.openPaymentsModal();
                }

                cy.contains('checkout-container', 'Voucher').should('not.exist');
            })

            it('Voucher available for ECO products', () => {
                configAction.updateProducts('eco');
                testVoucherPayment();
            })

            it('Voucher available for MEAL products', () => {
                configAction.updateProducts('meal');
                testVoucherPayment();
            })

            it('Voucher available for GIFT products', () => {
                configAction.updateProducts('gift');
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
