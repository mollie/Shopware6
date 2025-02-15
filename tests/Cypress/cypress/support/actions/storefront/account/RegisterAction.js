import RegisterRepository from 'Repositories/storefront/account/RegisterRepository';

const repo = new RegisterRepository();

export default class RegisterAction {

    /**
     *
     * @param email
     * @param password
     */
    doRegister(email, password) {

        cy.visit('/account');

        repo.getAccountType().select('Commercial');
        repo.getSalutation().select('Mr.');

        repo.getFirstname().clear().type('Mollie');
        repo.getLastname().clear().type('Mollie');

        repo.getCompany().clear().type('Mollie');

        repo.getEmail().clear().type(email);
        repo.getPassword().clear().type(password);

        repo.getStreet().clear().type('Mollie 13a');
        repo.getZipcode().clear().type('1234568');
        repo.getCity().clear().type('Mollie');

        repo.getCountry().select('Germany');

        repo.getRegisterButton().click();
    }

}
