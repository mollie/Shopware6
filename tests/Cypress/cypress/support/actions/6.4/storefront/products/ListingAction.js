import ListingRepository from 'Repositories/6.4/storefront/products/ListingRepository';

export default class ListingAction {

    /**
     *
     */
    clickOnFirstProduct() {

        const repo = new ListingRepository();

        repo.getFirstProduct().click();
    }

}
