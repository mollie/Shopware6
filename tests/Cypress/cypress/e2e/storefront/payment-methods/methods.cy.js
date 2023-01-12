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


context('Active Payment Methods', () => {

    testDevices.forEach(device => {

        context(devices.getDescription(device), () => {

            beforeEach(() => {
                devices.setDevice(device);
                configAction.setupShop(true, false, false);
                session.resetBrowserSession();
            });

            it('C3996: Mollie Payment Methods show TEST MODE @core', () => {

                scenarioDummyBasket.execute();

                if (shopware.isVersionGreaterEqual(6.4)) {
                    paymentAction.showAllPaymentMethods();
                } else {
                    paymentAction.openPaymentsModal()
                }

                // yes we require test mode, but this is
                // the only chance to see if the plugin is being used, because
                // every merchant might have different payment methods ;)
                cy.contains('(Test mode)');
            })
        })
    })
})

context('Deprecated Payment Methods', () => {

    testDevices.forEach(device => {

        context(devices.getDescription(device), () => {

            beforeEach(() => {
                devices.setDevice(device);
                configAction.setupShop(true, false, false);
                session.resetBrowserSession();
            });

            it('C38332: ING Home Pay must not exist @core', () => {

                scenarioDummyBasket.execute();

                if (shopware.isVersionGreaterEqual(6.4)) {
                    paymentAction.showAllPaymentMethods();
                } else {
                    paymentAction.openPaymentsModal()
                }

                cy.contains('ING Home').should('not.exist');
            })

            it('C38333: SEPA Direct Debit must not exist @core', () => {

                scenarioDummyBasket.execute();

                if (shopware.isVersionGreaterEqual(6.4)) {
                    paymentAction.showAllPaymentMethods();
                } else {
                    paymentAction.openPaymentsModal()
                }

                cy.contains('SEPA Direct Deb').should('not.exist');
            })

        })
    })
})
