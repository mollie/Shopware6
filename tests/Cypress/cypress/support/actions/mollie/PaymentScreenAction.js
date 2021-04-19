class PaymentScreenAction {

    /**
     *
     */
    selectPaid() {

        cy.get('input[value="paid"]').click();

        this._clickSubmit();
    }

    /**
     *
     */
    selectAuthorized() {

        cy.get('input[value="authorized"]').click();

        this._clickSubmit();
    }

    /**
     *
     */
    selectFailed() {

        cy.get('input[value="failed"]').click();

        this._clickSubmit();
    }

    /**
     *
     */
    selectCancelled() {

        cy.get('input[value="canceled"]').click();

        this._clickSubmit();
    }

    /**
     *
     */
    selectExpired() {

        cy.get('input[value="expired"]').click();

        this._clickSubmit();
    }

    /**
     *
     */
    selectIDEALIssuerABA() {
        cy.get('button[value="ideal_ABNANL2A"]').click();
    }

    /**
     *
     */
    selectCBCIssuerKBC() {
        cy.get('button[value="KBC"]').click();
    }

    /**
     *
     * @private
     */
    _clickSubmit() {
        cy.get('.button').click();
    }

}

export default PaymentScreenAction;
