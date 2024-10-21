import BuyElementRepository from './BuyElementRepository';

export default class BuyButtonRepository {
    constructor() {
        this.buyElementRepository = new BuyElementRepository();
    }

    find(button) {
        const buyElementContainer = this.buyElementRepository.find(button);
        if (buyElementContainer === null) {
            return null;
        }
        return buyElementContainer.querySelector('.btn-buy');
    }


}