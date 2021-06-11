import ListingRepository from 'Repositories/old/storefront/products/ListingRepository';

export default class ListingAction {

    /**
     *
     */
    clickOnFirstProduct() {

        const repo = new ListingRepository();

        repo.getFirstProduct().click();
    }

}
