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


describe('Payconiq', () => {

    context(devices.getDescription(device), () => {

        it('C3362896: Payconiq is existing in checkout', () => {

            session.resetBrowserSession();
            devices.setDevice(device);

            scenarioDummyBasket.execute();
            checkout.changeBillingCountry('Belgium');
            paymentAction.switchPaymentMethod('Payconiq');

            // payment would only work using currency CHF which cannot be done at the moment
        })

    })

})

