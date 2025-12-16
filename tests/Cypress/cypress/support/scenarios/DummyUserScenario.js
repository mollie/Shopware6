import LoginAction from "Actions/storefront/account/LoginAction";
import Session from "Services/utils/Session";
import RegisterAction from "Actions/storefront/account/RegisterAction";


const login = new LoginAction();
const session = new Session();
const register = new RegisterAction();


export default class DummyUserScenario {

    /**
     *
     */
    execute() {

        const user_email = 'cypress@mollie.com';
        const user_pwd = 'cypress123';
        cy.visit('/');

        session.resetBrowserSession();

        login.doLogin(user_email, user_pwd);

        // we have to start on the home page
        // after session restoring, so that we can continue as usual
        cy.visit('/account');

    }

}
