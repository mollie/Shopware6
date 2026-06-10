import LoginAction from "Actions/storefront/account/LoginAction";

const login = new LoginAction();

export default class DummyUserScenario {

    execute() {
        cy.clearAllCookies();
        cy.clearAllLocalStorage();
        cy.clearAllSessionStorage();
        login.doLogin('cypress@mollie.com', 'cypress123');
        cy.visit('/account');
    }

}
