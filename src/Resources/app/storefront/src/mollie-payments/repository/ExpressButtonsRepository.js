export default class ExpressButtonsRepository {
    findAll(additionalSelector = null) {
        let selector = '.mollie-express-button';
        if(additionalSelector !== null){
            selector += additionalSelector;
        }
        return document.querySelectorAll(selector);
    }
}