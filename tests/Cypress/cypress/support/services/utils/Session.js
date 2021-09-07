export default class Session {

    /**
     * This does completely reset the browser session
     * including the reset of cookies and whatever
     * needs to be done.
     */
    resetBrowserSession() {
        // we have to clear cookies 2x to really make it work
        cy.clearCookies();
        cy.clearCookies();

        cy.visit('/', {
            onBeforeLoad: (win) => {
                win.sessionStorage.clear()
            }
        });
    }

    /**
     * Resets only the cookies and storage.
     * This can be used in test runs to have a lost session.
     */
    resetSessionData() {
        cy.clearLocalStorage();
        // we have to clear cookies 2x to really make it work
        cy.clearCookies();
        cy.clearCookies();
    }

}
