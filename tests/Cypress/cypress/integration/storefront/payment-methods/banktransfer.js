import Devices from "Services/utils/Devices";
import Session from "Services/utils/Session"
import Shopware from "Services/shopware/Shopware";
// ------------------------------------------------------
import ShopConfigurationAction from "Actions/admin/ShopConfigurationAction";
// ------------------------------------------------------
import PaymentAction from "Actions/storefront/checkout/PaymentAction";
import DummyBasketScenario from "Scenarios/DummyBasketScenario";
import CheckoutAction from "Actions/storefront/checkout/CheckoutAction";
import PaymentScreenAction from "Actions/mollie/PaymentScreenAction";


const devices = new Devices();
const session = new Session();
const shopware = new Shopware();

const configAction = new ShopConfigurationAction();
const paymentAction = new PaymentAction();
const checkout = new CheckoutAction();

const molliePayment = new PaymentScreenAction();

const testDevices = [devices.getFirstDevice()];
const scenarioDummyBasket = new DummyBasketScenario(1);


describe('SEPA Bank Transfer', () => {

    testDevices.forEach(device => {

        context(devices.getDescription(device), () => {

            beforeEach(() => {
                devices.setDevice(device);
                session.resetBrowserSession();
                configAction.setupShop(false, false, false);
            });

            it('Payment status "open" leads to succesful order', () => {

                scenarioDummyBasket.execute();

                paymentAction.switchPaymentMethod('Banktransfer');

                shopware.prepareDomainChange();
                checkout.placeOrderOnConfirm();

                molliePayment.initSandboxCookie();
                molliePayment.selectOpen();

                cy.url().should('include', '/checkout/finish');
                cy.contains('Thank you for your order');
            })

        })
    })
})

