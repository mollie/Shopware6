import Plugin from 'src/plugin-system/plugin.class';
import DomAccess from 'src/helper/dom-access.helper';
import PageLoadingIndicatorUtil from 'src/utility/loading-indicator/page-loading-indicator.util';
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
        mollieMandateDeleteAlertId: '#mollieCreditCardMandateDeleteSuccess',
    };

    init() {
        const {
            shopUrl,
            customerId,
            mollieMandateDeleteAlertId,
        } = this.options;
        if (!shopUrl) {
            throw new Error(`The "shopUrl" option for the plugin "${ this._pluginName }" is not defined.`);
        }

        if (!customerId) {
            throw new Error(`The "customerId" option for the plugin "${ this._pluginName }" is not defined.`);
        }

        this.mollieMandateDeleteAlertEl = DomAccess.querySelector(document, mollieMandateDeleteAlertId, false);
        if (!this.mollieMandateDeleteAlertEl) {
            return;
        }

        this.client = new HttpClient();
        this.registerEvents();
    }

    registerEvents() {
        const {
            mollieMandateRemoveButtonClass,
            mollieMandateRemoveModalButtonClass,
        } = this.options;
        const removeButtons = DomAccess.querySelectorAll(document, mollieMandateRemoveButtonClass, false);
        if (!removeButtons || removeButtons.length === 0) {
            return;
        }

        const modalRemoveButtons = DomAccess.querySelectorAll(document, mollieMandateRemoveModalButtonClass, false);
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

        PageLoadingIndicatorUtil.create();
        this.deleteMandate(currentMandateId).then(({ success }) => {
            PageLoadingIndicatorUtil.remove();
            if (!success) {
                return;
            }

            this.mollieMandateDeleteAlertEl.classList.remove('d-none')
            currentContainerEl.remove();
        });
    }

    deleteMandate(mandateId) {
        const { shopUrl, customerId } = this.options

        return new Promise((resolve) => {
            this.client.get(
                shopUrl + '/mollie/components/revoke-mandate/' + customerId + '/' + mandateId,
                (res) => {
                    resolve({ success: res && res.success })
                },
                () => {
                    resolve({ success: false })
                },
                'application/json; charset=utf-8'
            );
        });
    }
}
