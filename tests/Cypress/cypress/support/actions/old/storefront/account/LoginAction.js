import LoginRepository from 'Repositories/old/storefront/account/LoginRepository';

const repo = new LoginRepository();

export default class LoginAction {

    /**
     *
     * @param email
     * @param password
     */
    doLogin(email, password) {

        cy.visit('/account');

        repo.getEmail().clear().type(email);
        repo.getPassword().clear().type(password);

        repo.getSubmitButton().click();
    }

}

