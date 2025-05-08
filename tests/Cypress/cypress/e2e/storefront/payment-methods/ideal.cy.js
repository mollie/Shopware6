import Devices from "Services/utils/Devices";
import Session from "Services/utils/Session"
import Shopware from "Services/shopware/Shopware";
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


const testDevices = [devices.getFirstDevice()];

const scenarioDummyBasket = new DummyBasketScenario(1);


describe('iDEAL Issuers', () => {

    testDevices.forEach(device => {

        context(devices.getDescription(device), () => {

            it('C4119: Issuer List on payment selection page', () => {

                // BEFORE EACH
                devices.setDevice(device);
                configAction.setupShop(true, false, false);
                session.resetBrowserSession();

                scenarioDummyBasket.execute();
                paymentAction.showPaymentMethods();


                paymentAction.selectPaymentMethod('iDEAL');
            })
        })
    })
})
