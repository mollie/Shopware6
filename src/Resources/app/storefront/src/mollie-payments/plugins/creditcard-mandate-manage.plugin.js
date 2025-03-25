import HttpClient from '../services/http-client';
import Plugin from '../plugin';

const DISPLAY_NONE_CLS = 'd-none';

/**
 * This plugin manage the credit card mandate of customer
 */
export default class MollieCreditCardMandateManage extends Plugin {
    static options = {
        shopUrl: null,
        customerId: null,
        mollieMandateContainerClass: '.mollie-credit-card-mandate',
        mollieMandateDataId: 'data-mollie-credit-card-mandate-id',
        mollieMandateRemoveButtonClass: '.mollie-credit-card-mandate-remove',
        mollieMandateRemoveModalButtonClass: '.mollie-credit-card-mandate-remove-modal-button',
        mollieMandateDeleteAlertSuccessId: '#mollieCreditCardMandateDeleteSuccess',
        mollieMandateDeleteAlertErrorId: '#mollieCreditCardMandateDeleteError',
    };

    init() {
        if (!this.options.shopUrl) {
            throw new Error(`The "shopUrl" option for the plugin "${this._pluginName}" is not defined.`);
        }

        if (!this.options.customerId) {
            throw new Error(`The "customerId" option for the plugin "${this._pluginName}" is not defined.`);
        }

        this.mollieMandateDeleteAlertEl = document.querySelector(this.options.mollieMandateDeleteAlertSuccessId);
        if (!this.mollieMandateDeleteAlertEl) {
            return;
        }

        this.mollieMandateDeleteAlertErrorEl = document.querySelector(this.options.mollieMandateDeleteAlertErrorId);
        if (!this.mollieMandateDeleteAlertErrorEl) {
            return;
        }

        this.client = new HttpClient();
        this.registerEvents();
    }

    registerEvents() {
        const removeButtons = document.querySelectorAll(this.options.mollieMandateRemoveButtonClass);
        if (!removeButtons || removeButtons.length === 0) {
            return;
        }

        const modalRemoveButtons = document.querySelectorAll(this.options.mollieMandateRemoveModalButtonClass);
        if (!modalRemoveButtons || modalRemoveButtons.length === 0) {
            return;
        }

        removeButtons.forEach((removeButton) => {
            removeButton.addEventListener('click', (e) => {
                e.preventDefault();

                this.onRemoveButtonClick(removeButton);
            });
        });

        modalRemoveButtons.forEach((modalRemoveButton) => {
            modalRemoveButton.addEventListener('click', (e) => {
                e.preventDefault();

                this.onConfirmRemoveButtonClick();
            });
        });
    }

    onRemoveButtonClick(removeButton) {
        this.currentContainerEl = removeButton.closest(this.options.mollieMandateContainerClass);
        if (!this.currentContainerEl) {
            return;
        }

        this.currentMandateId = this.currentContainerEl.getAttribute(this.options.mollieMandateDataId);
    }

    onConfirmRemoveButtonClick() {
        const { currentContainerEl, currentMandateId } = this;

        if (!currentContainerEl || !currentMandateId) {
            return;
        }

        this.deleteMandate(currentMandateId).then(({ success }) => {
            if (success) {
                this.mollieMandateDeleteAlertErrorEl.classList.add(DISPLAY_NONE_CLS);
                this.mollieMandateDeleteAlertEl.classList.remove(DISPLAY_NONE_CLS);
                currentContainerEl.remove();
            } else {
                this.mollieMandateDeleteAlertEl.classList.add(DISPLAY_NONE_CLS);
                this.mollieMandateDeleteAlertErrorEl.classList.remove(DISPLAY_NONE_CLS);
            }
        });
    }

    deleteMandate(mandateId) {
        return new Promise((resolve) => {
            this.client.get(
                this.options.shopUrl + '/mollie/components/revoke-mandate/' + this.options.customerId + '/' + mandateId,
                (res) => {
                    resolve({ success: res && res.success });
                },
                () => {
                    resolve({ success: false });
                },
                'application/json; charset=utf-8',
            );
        });
    }
}
