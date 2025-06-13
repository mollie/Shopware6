import BuyBoxRepository from '../../repository/buy-box-repository';

const DISPLAY_NONE_CLS = 'd-none';
const INVALID_CLS = 'is-invalid';

export default class PrivacyNotesService {
    constructor(document) {
        this._repoBuyBox = new BuyBoxRepository();
        this._document = document;
    }

    /**
     * Sets up privacy note checkboxes to appear and disappear based on express payment button visibility.
     * When express payment buttons (like Apple Pay) are shown, the privacy note becomes visible.
     * When all express payment buttons are hidden, the privacy note is also hidden to avoid confusion.
     */
    initCheckbox() {
        const privacyNotes = this._document.querySelectorAll('.mollie-privacy-note:not(.observed)');

        for (let i = 0; i < privacyNotes.length; i++) {
            const currentNote = privacyNotes[i];

            currentNote.classList.remove(DISPLAY_NONE_CLS);

            currentNote.classList.add('observed');

            const buyElement = this._repoBuyBox.findClosestBuyBox(currentNote);
            const expressButtons = this._repoBuyBox.findAllExpressButtons(buyElement, null);

            if (expressButtons.length === 0) {
                currentNote.classList.add(DISPLAY_NONE_CLS);
                continue;
            }

            for (let j = 0; j < expressButtons.length; j++) {
                const currentButton = expressButtons[j];
                const observer = new MutationObserver((mutations) => {
                    let visibleExpressButtons = expressButtons.length;
                    currentNote.classList.remove(DISPLAY_NONE_CLS);

                    mutations.forEach((mutation) => {
                        if (mutation.target.classList.contains(DISPLAY_NONE_CLS)) {
                            visibleExpressButtons--;
                        }
                    });

                    if (visibleExpressButtons <= 0) {
                        currentNote.classList.add(DISPLAY_NONE_CLS);
                    }
                });

                observer.observe(currentButton, { attributes: true, attributeFilter: ['class'] });
            }
        }
    }

    /**
     * Validates privacy note requirements for a given express payment button
     * @param {HTMLElement} expressButton - The express payment button element
     * @returns {boolean|undefined} Validation result or undefined if no privacy note found
     */
    validateForExpressButton(expressButton) {
        const privacyNoteElement = this._repoBuyBox.findClosestPrivacyBox(expressButton);

        if (!(privacyNoteElement instanceof HTMLDivElement)) {
            return true;
        }

        const dataProtection = this._repoBuyBox.getPrivacyBoxCheckbox(privacyNoteElement);

        const dataProtectionValue = dataProtection.checked ? 1 : 0;
        dataProtection.classList.remove(INVALID_CLS);

        if (dataProtectionValue === 0) {
            dataProtection.classList.add(INVALID_CLS);
            return false;
        }
        return true;
    }
}
