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


const device = devices.getFirstDevice();


context("Events Config", () => {

    beforeEach(() => {
        session.resetBrowserSession();
        devices.setDevice(device);
    });

    context(devices.getDescription(device), () => {

        it('Should open event details without errors', () => {

            adminLogin.login();
            settingsAction.openBusinessEventsPage();
            settingsAction.editFirstBusinessEvent();

            cy.get('.sw-card__content').contains('Event');
        })


    })
})
