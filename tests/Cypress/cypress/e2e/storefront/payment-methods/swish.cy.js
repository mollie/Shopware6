import Devices from "Services/utils/Devices";
import Session from "Services/utils/Session"
// ------------------------------------------------------
import PaymentAction from "Actions/storefront/checkout/PaymentAction";
import DummyBasketScenario from "Scenarios/DummyBasketScenario";
import CheckoutAction from "Actions/storefront/checkout/CheckoutAction";
// ------------------------------------------------------


const devices = new Devices();
const session = new Session();

const paymentAction = new PaymentAction();
const scenarioDummyBasket = new DummyBasketScenario(1);
const device = devices.getFirstDevice();


describe('Swish', () => {

    context(devices.getDescription(device), () => {

        it('Swish is existing in checkout', () => {

            session.resetBrowserSession();
            devices.setDevice(device);

            scenarioDummyBasket.execute();

            paymentAction.switchPaymentMethod('Swish');
        })

    })

})

