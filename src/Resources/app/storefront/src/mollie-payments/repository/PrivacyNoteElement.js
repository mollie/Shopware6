import BuyElementRepository from './BuyElementRepository';
import ExpressButtonsRepository from "./ExpressButtonsRepository";

export const TOGGLE_PRIVACY_NOTE_EVENT = 'TogglePrivacyNote';

export class PrivacyNoteElement {
    constructor() {
        this.buyElementRepository = new BuyElementRepository();
    }

    find(button) {
        const buyElementContainer = this.buyElementRepository.find(button);
        if (buyElementContainer === null) {
            return null;
        }
        return buyElementContainer.querySelector('.mollie-privacy-note');
    }

    getCheckbox(privacyNote) {
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

    observeButtons() {

        const privacyNotes = document.querySelectorAll('.mollie-privacy-note:not(.observed)');
        const buyElementRepository = new BuyElementRepository();

        privacyNotes.forEach((privacyNote) => {

            privacyNote.classList.add('observed');

            const buyElement = buyElementRepository.find(privacyNote);
            const expressButtonsRepository = new ExpressButtonsRepository(buyElement);
            const expressButtons = expressButtonsRepository.findAll();

            expressButtons.forEach((expressButton) => {

                const observer = new MutationObserver((mutations) => {
                    let visibleExpressButtons = expressButtons.length;
                    privacyNote.classList.remove('d-none');

                    mutations.forEach((mutation) => {

                       if(mutation.target.classList.contains('d-none')){
                           visibleExpressButtons--;
                       }

                    });


                    if(visibleExpressButtons === 0){
                        privacyNote.classList.add('d-none');
                    }

                });

                observer.observe(expressButton, {attributes: true, attributeFilter: ['class']});
            })


        })


    }

}