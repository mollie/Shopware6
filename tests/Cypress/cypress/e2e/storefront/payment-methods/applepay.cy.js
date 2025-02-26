import Devices from "Services/utils/Devices";
import Shopware from "Services/shopware/Shopware";
import {ApplePaySessionMockFactory} from "Services/applepay/ApplePay.Mock";
// ------------------------------------------------------
import ShopConfigurationAction from "Actions/admin/ShopConfigurationAction";
// ------------------------------------------------------
import PaymentAction from "Actions/storefront/checkout/PaymentAction";
import DummyBasketScenario from "Scenarios/DummyBasketScenario";
import Session from "Services/utils/Session";


const devices = new Devices();
const shopware = new Shopware();
const session = new Session();

const configAction = new ShopConfigurationAction();
const paymentAction = new PaymentAction();

const applePayFactory = new ApplePaySessionMockFactory;

const scenarioDummyBasket = new DummyBasketScenario(1);

const device = devices.getFirstDevice();


let beforeAllCalled = false;

function beforeEach(device) {
    cy.wrap(null).then(() => {
        if (!beforeAllCalled) {
            configAction.setupShop(true, false, false);
            beforeAllCalled = true;
        }
        session.resetBrowserSession();
        devices.setDevice(device);
    });
}


context("Apple Pay", () => {

    describe('Checkout', () => {

        it('C4082: Apple Pay visible if possible in browser (Checkout) @core', () => {

            beforeEach(device);

            applePayFactory.registerApplePay(true);

            scenarioDummyBasket.execute();

            if (shopware.isVersionGreaterEqual(6.4)) {
                paymentAction.showAllPaymentMethods();
            } else {
                paymentAction.openPaymentsModal();
            }

            cy.wait(2000);
            cy.contains('Apple Pay').should('exist');
        })

        it('C4080: Apple Pay hidden if not possible in browser (Checkout) @core', () => {

            beforeEach(device);

            applePayFactory.registerApplePay(false);

            scenarioDummyBasket.execute();

            if (shopware.isVersionGreaterEqual(6.4)) {
                paymentAction.showAllPaymentMethods();
            } else {
                paymentAction.openPaymentsModal();
            }

            cy.wait(2000);
            cy.contains('Apple Pay').should('not.exist');
        })
    })

})
