import RegisterRepository from 'Repositories/6.4/storefront/account/RegisterRepository';

const repo = new RegisterRepository();

export default class RegisterAction {

    /**
     *
     * @param email
     * @param password
     */
    doRegister(email, password) {

        cy.visit('/account');

        repo.getSalutation().select('Mr.');

        repo.getFirstname().clear().type('Mollie');
        repo.getLastname().clear().type('Mollie');

        repo.getEmail().clear().type(email);
        repo.getPassword().clear().type(password);

        repo.getStreet().clear().type('Mollie');
        repo.getZipcode().clear().type('Mollie');
        repo.getCity().clear().type('Mollie');

        repo.getCountry().select('Germany');

        repo.getRegisterButton().click();
    }

}
