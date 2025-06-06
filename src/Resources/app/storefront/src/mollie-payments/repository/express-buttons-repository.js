export default class ExpressButtonsRepository {

    constructor(target = null) {
        this.target = target;
        if (this.target === null) {
            this.target = document;
        }
    }

    findAll(additionalSelector = null) {
        let selector = '.mollie-express-button';
        if (additionalSelector !== null) {
            selector += additionalSelector;
        }
        return this.target.querySelectorAll(selector);
    }

    findApplePayButtons() {
        return this.target.querySelectorAll('.mollie-express-button.js-apple-pay');
    }

    findApplePayContainers() {
        return this.target.querySelectorAll('.js-apple-pay-container');
    }

}
