import BuyBoxRepository from '../../repository/buy-box-repository';

const DISPLAY_NONE_CLS = 'd-none';
const INVALID_CLS = 'is-invalid';
const OBSERVED_HOOK = 'js-mollie-observed';

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
        const privacyNotes = this._document.querySelectorAll(`.mollie-privacy-note:not(.${OBSERVED_HOOK})`);

        for (let i = 0; i < privacyNotes.length; i++) {
            const currentNote = privacyNotes[i];

            currentNote.classList.remove(DISPLAY_NONE_CLS);

            currentNote.classList.add(OBSERVED_HOOK);

            const buyElement = this._repoBuyBox.findClosestBuyBox(currentNote);

            // if the privacy note has no surrounding buy box, there is no express
            // button it could belong to. Hide it and continue instead of calling
            // querySelectorAll() on null, which would throw and abort init() for
            // every following express button (no click handler => no validation).
            if (buyElement === null) {
                currentNote.classList.add(DISPLAY_NONE_CLS);
                continue;
            }

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
     * Resolves the accepted-data-protection value that has to be sent to the backend
     * for a given express payment button.
     *
     * The checkbox is never a child of the express button container itself, it is rendered
     * in the sibling `.mollie-privacy-note` of the surrounding buy box. It therefore has to
     * be looked up through the buy box, otherwise the value silently stays unaccepted and
     * Shopware rejects the guest registration.
     *
     * A missing privacy note means the shop does not require the checkbox, or the customer
     * is already logged in and gave the consent while registering. Neither case creates a
     * guest account, so returning 0 never blocks a checkout and no consent is faked.
     *
     * @param {HTMLElement} expressButton - The express payment button element
     * @returns {number} 1 if an accepted checkbox exists, 0 otherwise
     */
    getAcceptedDataProtection(expressButton) {
        const privacyNoteElement = this._repoBuyBox.findClosestPrivacyBox(expressButton);

        if (privacyNoteElement === null) {
            return 0;
        }

        const dataProtection = this._repoBuyBox.getPrivacyBoxCheckbox(privacyNoteElement);

        if (dataProtection === null) {
            return 0;
        }

        return dataProtection.checked ? 1 : 0;
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
