export default class RegisterRepository {


    /**
     *
     * @returns {*}
     */
    getAccountType() {
        return cy.get('#accountType,[name="accountType"]');
    }

    /**
     *
     * @returns {*}
     */
    getSalutation() {
        return cy.get('#personalSalutation');
    }

    /**
     *
     * @returns {*}
     */
    getFirstname() {
        return cy.get('#personalFirstName,[name="billingAddress[firstName]"]');
    }

    /**
     *
     * @returns {*}
     */
    getLastname() {
        return cy.get('#personalLastName,[name="billingAddress[lastName]"]');
    }

    /**
     *
     * @returns {*}
     */
    getCompany() {
        return cy.get('#billingAddresscompany,[name="billingAddress[company]"]');
    }

    /**
     *
     * @returns {*}
     */
    getEmail() {
        return cy.get('#personalMail');
    }

    /**
     *
     * @returns {*}
     */
    getPassword() {
        return cy.get('#personalPassword');
    }

    /**
     *
     * @returns {*}
     */
    getStreet() {
        return cy.get('#billingAddressAddressStreet,[name="billingAddress[street]"]');
    }

    /**
     *
     * @returns {*}
     */
    getZipcode() {
        return cy.get('#billingAddressAddressZipcode,[name="billingAddress[zipcode]"]');
    }

    /**
     *
     * @returns {*}
     */
    getCity() {
        return cy.get('#billingAddressAddressCity,[name="billingAddress[city]"]');
    }

    /**
     *
     * @returns {*}
     */
    getCountry() {
        return cy.get('#billingAddressAddressCountry');
    }

    /**
     *
     * @returns {*}
     */
    getRegisterButton() {
        return cy.get('.register-submit > .btn');
    }

}
