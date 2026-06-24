export default class AdminLoginAction {

    /**
     * Logs into the Shopware Admin through the real UI login flow.
     *
     * We intentionally go through the actual login page (instead of seeding a
     * token via the API) so that any regression in our plugin that breaks the
     * admin login - broken bundle, failing decorator, bad migration - actually
     * turns the tests red instead of staying hidden.
     *
     * The flakiness on CI is a timing issue, not a flow issue: on a slow Docker
     * runner the admin SPA needs longer to hydrate before it accepts input, and
     * the post-login boot takes longer than the previous 10s allowed. So we wait
     * for readiness explicitly and give the slow steps generous timeouts.
     */
    login() {

        // increase our viewport for admin
        // otherwise we don't see a lot (page height)
        cy.viewport(1920, 1500);

        // watch the real auth call so a broken login fails loudly and clearly
        // at this step, instead of timing out later on some unrelated element
        cy.intercept('POST', '/api/oauth/token').as('adminAuth');

        cy.visit('/admin');

        // wait until the login form is actually hydrated and interactive.
        // typing too early on a slow runner silently drops characters.
        cy.get('#sw-field--username', {timeout: 30000})
            .should('be.visible')
            .clear()
            .type('admin');

        cy.get('#sw-field--password')
            .should('be.visible')
            .clear()
            .type('shopware');

        cy.get('.sw-button,.sw-login__submit button')
            .should('be.enabled')
            .click();

        // confirm the login itself succeeded before waiting on the SPA boot
        cy.wait('@adminAuth').its('response.statusCode').should('eq', 200);

        // the admin SPA boot after login is slow on CI - the element does appear
        // eventually, 10s was simply too tight. give it room.
        cy.get('.sw-version__info', {timeout: 60000}).should('be.visible');
    }

}
