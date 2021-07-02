import Devices from "Services/Devices";
import Session from "Actions/utils/Session"
import Shopware from "Services/Shopware";
// ------------------------------------------------------
import ShopConfigurationAction from "Actions/admin/ShopConfigurationAction";
// ------------------------------------------------------
import PaymentAction from "Actions/storefront/checkout/PaymentAction";
import DummyBasketScenario from "Scenarios/DummyBasketScenario";


const devices = new Devices();
const session = new Session();
const shopware = new Shopware();

const configAction = new ShopConfigurationAction();
const paymentAction = new PaymentAction();

const scenarioDummyBasket = new DummyBasketScenario(1);

const device = devices.getFirstDevice();


context("Apple Pay", () => {

    before(function () {
        devices.setDevice(device);
        configAction.setupShop(true, false);
    })

    beforeEach(() => {
        session.resetBrowserSession();
        devices.setDevice(device);
    });


    it('Apple Pay hidden if not available in browser', () => {

        scenarioDummyBasket.execute();

        if (shopware.getVersion() >= 6.4) {
            paymentAction.showAllPaymentMethods();
        } else {
            paymentAction.openPaymentsModal();
        }

        // wait a bit, because the client side
        // code for the ApplePay recognition needs to
        // be executed first
        cy.wait(2000);

        cy.contains('Apple Pay').should('not.exist');
    })

})
