import Devices from "Services/utils/Devices";
import Session from "Services/utils/Session"
// ------------------------------------------------------
import AdminLoginAction from "Actions/admin/AdminLoginAction";
import Shopware from "Services/shopware/Shopware";


const devices = new Devices();
const session = new Session();

const adminLogin = new AdminLoginAction();


const shopware = new Shopware();

const device = devices.getFirstDevice();


context("Plugin Config", () => {

    beforeEach(() => {
        session.resetBrowserSession();
        devices.setDevice(device);
    });

    context(devices.getDescription(device), () => {

        it('Smart Contact Form is responding properly', () => {

            adminLogin.login();

            cy.visit('/admin#/sw/extension/config/MolliePayments');

            cy.get('.col-right > button.sw-button').click();


            // we have to see our modal popup
            cy.contains('Request support from Mollie');

            // the modal should show the Shopware version number
            cy.contains("v" + shopware.getVersion());

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

    })
})
