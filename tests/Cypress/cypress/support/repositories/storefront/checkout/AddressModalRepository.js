export default class AddressModalRepository
{

    getCurrentAddressEditButton()
    {
        return cy.get('.modal-dialog-address #shipping-address-tab-pane .address-manager-select-address button');

    }

    getEditFormCountryDropdownContainer()
    {
        return cy.get('.dropdown-menu .address-manager-modal-address-form[data-address-type="shipping"]');
    }

    getEditFormCountryDropdown()
    {
        return cy.get('select.country-select:eq(0)');
    }

    getEditFormSaveAddressButton() {
        return cy.get('.address-form-create-submit');
    }

}