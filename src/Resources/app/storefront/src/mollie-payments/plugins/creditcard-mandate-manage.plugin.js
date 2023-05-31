import Plugin from '@shopware-storefront-sdk/plugin-system/plugin.class';
import HttpClient from '../services/HttpClient';

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
        const {
            shopUrl,
            customerId,
        } = this.options;
        if (!shopUrl) {
            throw new Error(`The "shopUrl" option for the plugin "${this._pluginName}" is not defined.`);
        }

        if (!customerId) {
            throw new Error(`The "customerId" option for the plugin "${this._pluginName}" is not defined.`);
        }

        this.mollieMandateDeleteAlertEl = document.querySelector('#mollieCreditCardMandateDeleteSuccess');
        if (!this.mollieMandateDeleteAlertEl) {
            return;
        }

        this.mollieMandateDeleteAlertErrorEl = document.querySelector('#mollieCreditCardMandateDeleteError');
        if (!this.mollieMandateDeleteAlertErrorEl) {
            return;
        }

        this.client = new HttpClient();
        this.registerEvents();
    }

    registerEvents() {

        const removeButtons = document.querySelectorAll('.mollie-credit-card-mandate-remove');
        if (!removeButtons || removeButtons.length === 0) {
            return;
        }

        const modalRemoveButtons = document.querySelectorAll('.mollie-credit-card-mandate-remove-modal-button');
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
        const {
            mollieMandateContainerClass,
            mollieMandateDataId,
        } = this.options;

        this.currentContainerEl = removeButton.closest(mollieMandateContainerClass);
        if (!this.currentContainerEl) {
            return;
        }

        this.currentMandateId = this.currentContainerEl.getAttribute(mollieMandateDataId);
    }

    onConfirmRemoveButtonClick() {
        const {
            currentContainerEl,
            currentMandateId,
        } = this;

        if (!currentContainerEl || !currentMandateId) {
            return
        }

        this.deleteMandate(currentMandateId).then(({success}) => {

            if (success) {
                this.mollieMandateDeleteAlertErrorEl.classList.add('d-none')
                this.mollieMandateDeleteAlertEl.classList.remove('d-none')
                currentContainerEl.remove();
            } else {
                this.mollieMandateDeleteAlertEl.classList.add('d-none')
                this.mollieMandateDeleteAlertErrorEl.classList.remove('d-none')
            }
        });
    }

    deleteMandate(mandateId) {
        const {shopUrl, customerId} = this.options

        return new Promise((resolve) => {
            this.client.get(
                shopUrl + '/mollie/components/revoke-mandate/' + customerId + '/' + mandateId,
                (res) => {
                    resolve({success: res && res.success})
                },
                () => {
                    resolve({success: false})
                },
                'application/json; charset=utf-8'
            );
        });
    }
}
