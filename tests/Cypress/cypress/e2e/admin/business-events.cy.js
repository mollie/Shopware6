import Devices from "Services/utils/Devices";
import Session from "Services/utils/Session"
// ------------------------------------------------------
import AdminLoginAction from "Actions/admin/AdminLoginAction";
import Shopware from "Services/shopware/Shopware";
import AdminSettingsAction from "Actions/admin/AdminSettingsAction";


const devices = new Devices();
const session = new Session();

const adminLogin = new AdminLoginAction();
const settingsAction = new AdminSettingsAction();

const shopware = new Shopware();

const device = devices.getFirstDevice();

export const getMochaContext = () => cy.state('runnable').ctx;


context("Events Config", () => {

    beforeEach(() => {
        session.resetBrowserSession();
        devices.setDevice(device);
    });

    context(devices.getDescription(device), () => {

        it('C1277772: Flow Builder does not break Business Events in Admin (Shopware < 6.5)', () => {

            if (shopware.isVersionGreaterEqual('6.5.0.0')) {
                getMochaContext().skip('Business Events are only available below Shopware 6.5');
                return;
            }

            adminLogin.login();
            settingsAction.openBusinessEventsPage();
            settingsAction.openFirstBusinessEvent();

            cy.get('.sw-card__content').contains('Event');
        })

    })
})
