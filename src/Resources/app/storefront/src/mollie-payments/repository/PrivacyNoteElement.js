import BuyElementRepository from './BuyElementRepository';

export default class PrivacyNoteElement {
    constructor() {
        this.buyElementRepository = new BuyElementRepository();
    }

    find(button) {
        const buyElementContainer = this.buyElementRepository.find(button);
        if(buyElementContainer === null){
            return null;
        }
        return buyElementContainer.querySelector('.mollie-privacy-note');
    }
    getCheckbox(privacyNote){
        return privacyNote.querySelector('input[name="acceptedDataProtection"]');
    }
    validate(privacyNote) {

        const dataProtection = this.getCheckbox(privacyNote);

        const dataProtectionValue = dataProtection.checked ? 1 : 0;
        dataProtection.classList.remove('is-invalid');

        if (dataProtectionValue === 0) {
            dataProtection.classList.add('is-invalid');
            return false;
        }
        return true;
    }
}