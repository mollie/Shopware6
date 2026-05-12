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

        }, {
            cacheAcrossSpecs: false,
            validate() {
                cy.intercept('GET', '/account**', (req) => {
                    req.headers['cache-control'] = 'no-cache, no-store';
                });
                cy.visit('/account');
                cy.url().should('not.include', '/login');
            }
        });

        cy.visit('/');
    }

}

