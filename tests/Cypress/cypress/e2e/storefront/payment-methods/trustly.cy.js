import Devices from "Services/utils/Devices";
import Session from "Services/utils/Session"
// ------------------------------------------------------
import PaymentAction from "Actions/storefront/checkout/PaymentAction";
import DummyBasketScenario from "Scenarios/DummyBasketScenario";
// ------------------------------------------------------


const devices = new Devices();
const session = new Session();

const paymentAction = new PaymentAction();
const scenarioDummyBasket = new DummyBasketScenario(1);

const device = devices.getFirstDevice();


describe('Trustly', () => {

    context(devices.getDescription(device), () => {

        it('C3362895: Trustly is existing in checkout', () => {

            session.resetBrowserSession();
            devices.setDevice(device);

            scenarioDummyBasket.execute();

            paymentAction.switchPaymentMethod('Trustly');

            // payment would only work using currency CHF which cannot be done at the moment
        })

    })

})

