export default class Session {

    /**
     * This does completely reset the browser session
     * including the reset of cookies and whatever
     * needs to be done.
     */
    resetBrowserSession() {
        cy.clearAllCookies();
        cy.clearAllLocalStorage();
        cy.clearAllSessionStorage();
        cy.visit('/');
    }

    /**
     * Resets only the cookies and storage.
     * This can be used in test runs to have a lost session.
     */
    resetSessionData() {
        cy.clearAllCookies();
        cy.clearAllLocalStorage();
        cy.clearAllSessionStorage();
    }

}
