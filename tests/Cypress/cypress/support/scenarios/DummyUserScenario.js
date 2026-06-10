import LoginAction from "Actions/storefront/account/LoginAction";

const login = new LoginAction();

export default class DummyUserScenario {

    execute() {
        cy.clearAllCookies();
        cy.clearAllLocalStorage();
        cy.clearAllSessionStorage();
        login.doLogin('cypress@mollie.com', 'cypress123');
        cy.visit('/account');
        cy.url().should('not.include', '/account/login').then(function (url) {
            cy.log('Login verified - current URL: ' + url);
        });
    }

}
