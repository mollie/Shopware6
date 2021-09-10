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

        const user_email = "dev@localhost.de";
        const user_pwd = "MollieMollie111";

        cy.visit('/');

        register.doRegister(user_email, user_pwd);

        session.resetBrowserSession();

        login.doLogin(user_email, user_pwd);
    }

}
