import Devices from "Services/utils/Devices";
import Session from "Services/utils/Session"
// ------------------------------------------------------
import AdminLoginAction from "Actions/admin/AdminLoginAction";
import Shopware from "Services/shopware/Shopware";
import AdminPluginAction from "Actions/admin/AdminPluginAction";
import VueJs from "Services/utils/VueJs/VueJs";


const devices = new Devices();
const session = new Session();

const adminLogin = new AdminLoginAction();
const pluginAction = new AdminPluginAction();

const shopware = new Shopware();

const device = devices.getFirstDevice();


context("Plugin Config", () => {

    beforeEach(() => {
        session.resetBrowserSession();
        devices.setDevice(device);
    });

    context(devices.getDescription(device), () => {

        it('C147522: Onboarding Section is visible @core', () => {

            adminLogin.login();
            pluginAction.openPluginConfiguration();

            cy.contains('Onboarding is easy with Mollie!');
        })

        it('C147523: Update Payment Method triggers action @core', () => {

            adminLogin.login();
            pluginAction.openPluginConfiguration();

            cy.get('.sw-system-config--field-mollie-payments-config-mollie-plugin-config-section-payments > .sw-container > .sw-button').click();

            cy.contains('The payment methods are successfully updated.');
        })

        it('C148986: Rounding Settings Information is visible @core', () => {

            adminLogin.login();
            pluginAction.openPluginConfiguration();

            cy.contains('Shopware can use currency settings to calculate');
        })

        it('C4001: Smart Contact Form is responding properly @core', () => {

            adminLogin.login();
            pluginAction.openPluginConfiguration();

            cy.get('.col-right > button.sw-button', {timeout: 10000}).click();

            // we have to see our modal popup
            cy.contains('Request support from Mollie');

            // the modal should show the Shopware version number
            cy.contains("v" + shopware.getDisplayedVersion());

            // the send button is disabled until data is filled in
            cy.get('.sw-button-process').should('be.disabled');

            // now fill in our data
            cy.get('#sw-field--name').type('John');
            cy.get('#sw-field--email').type('test@localhost.com');
            cy.get('#sw-field--subject').type('Cypress Test Request');
            cy.get('.sw-text-editor').type('This is an automated request by Cypress and should not be sent.');

            // now click somewhere else
            cy.get('#sw-field--name').click();

            // the send button should be enabled now
            cy.get('.sw-button-process').should('not.be.disabled');
        })

        it('C234008: Custom format for order number shows interactive preview @core', () => {

            adminLogin.login();
            pluginAction.openPluginConfiguration();

            const inputPrefix = '#MolliePayments\\.config\\.formatOrderNumber';
            const divPreview = '.sw-system-config--field-mollie-payments-config-mollie-plugin-config-section-payments-format';

            cy.get(inputPrefix).click().clear();
            cy.get(divPreview).should('be.visible');

            cy.get(inputPrefix).click().clear();
            cy.get(inputPrefix).click().type('cypress_{ordernumber}', {parseSpecialCharSequences: false});
            cy.get(divPreview).should('be.visible');
            cy.contains(divPreview, '"cypress_1000"');

            cy.get(inputPrefix).click().clear();
            cy.get(inputPrefix).click().type('cypress_{ordernumber}-stage', {parseSpecialCharSequences: false});
            cy.get(divPreview).should('be.visible');
            cy.contains(divPreview, '"cypress_1000-stage"');
        })

        it('C1097313: Display order lifetime days warning', () => {

            adminLogin.login();
            pluginAction.openPluginConfiguration();


            const inputField = '#MolliePayments\\.config\\.orderLifetimeDays';
            const errorDiv = '.bankTransferDueDateLimitReached';
            const klarnaWarningDiv = '.bankTransferDueDateKlarnaLimitReached';


            cy.get(inputField).clear().type('101');
            cy.get(klarnaWarningDiv).should('not.exist');
            cy.get(errorDiv).should('exist');

            pluginAction.savePlugConfiguration();
            cy.get(klarnaWarningDiv).should('not.exist');
            cy.get(errorDiv).should('exist');

            cy.get(inputField).clear().type('30');
            cy.get(klarnaWarningDiv).should('exist');
            cy.get(errorDiv).should('not.exist');

            pluginAction.savePlugConfiguration();
            cy.get(klarnaWarningDiv).should('exist');
            cy.get(errorDiv).should('not.exist');


            cy.get(inputField).clear().type('0');
            cy.get(klarnaWarningDiv).should('not.exist');
            cy.get(errorDiv).should('not.exist');

            pluginAction.savePlugConfiguration();
            cy.get(klarnaWarningDiv).should('not.exist');
            cy.get(errorDiv).should('not.exist');


        })
    })
})
