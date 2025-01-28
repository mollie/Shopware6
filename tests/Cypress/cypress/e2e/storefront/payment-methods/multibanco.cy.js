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
const checkout = new CheckoutAction();
const device = devices.getFirstDevice();


describe('Multibanco', () => {

    context(devices.getDescription(device), () => {

        before(function () {
            devices.setDevice(device);
        })

        beforeEach(() => {
            session.resetBrowserSession();
            devices.setDevice(device);
        });

        it('MB Way is existing in checkout', () => {

            scenarioDummyBasket.execute();
            checkout.changeBillingCountry('Portugal');
            paymentAction.switchPaymentMethod('Multibanco');

            // payment would only work using currency CHF which cannot be done at the moment
        })

    })

})

