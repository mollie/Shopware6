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


describe('MyBank', () => {

    context(devices.getDescription(device), () => {

        before(function () {
            devices.setDevice(device);
        })

        beforeEach(() => {
            session.resetBrowserSession();
            devices.setDevice(device);
        });

        it('C3192051: MyBank is existing in checkout', () => {

            scenarioDummyBasket.execute();

            paymentAction.switchPaymentMethod('MyBank');

            // payment would only work using currency CHF which cannot be done at the moment
        })

    })

})

