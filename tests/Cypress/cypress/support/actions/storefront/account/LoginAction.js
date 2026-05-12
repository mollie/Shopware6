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
                cy.request({
                    url: '/account',
                    headers: {'cache-control': 'no-cache, no-store'},
                    failOnStatusCode: false,
                }).then(response => {
                    const loginRedirect = (response.redirects || []).some(r => r.includes('/login'));
                    expect(loginRedirect, 'session should be valid').to.be.false;
                });
            }
        });

        cy.visit('/');
    }

}

