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


describe('Bancomat Pay', () => {

    context(devices.getDescription(device), () => {

        before(function () {
            devices.setDevice(device);
        })

        beforeEach(() => {
            session.resetBrowserSession();
            devices.setDevice(device);
        });

        it('C2775016: Bancomat Pay is existing in checkout', () => {

            scenarioDummyBasket.execute();

            paymentAction.switchPaymentMethod('Bancomat Pay');
        })

    })

})

