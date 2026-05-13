import LoginRepository from 'Repositories/storefront/account/LoginRepository';

const repo = new LoginRepository();
export default class LoginAction {

    /**
     *
     * @param email
     * @param password
     */
    doLogin(email, password) {

        cy.session('login', () => {

            cy.visit('/account');

            repo.getEmail().clear().type(email, {'force': true});
            repo.getPassword().clear().type(password, {'force': true});

            repo.getSubmitButton().click();
            repo.getSubmitButton().click({force: true});
            cy.wait(2000);
        }, {
            cacheAcrossSpecs: false,
            validate() {
                cy.visit('/account');
                cy.url().should('not.include', '/login');
            }
        });

        cy.visit('/');
    }

}

