import ListingRepository from 'Repositories/storefront/products/ListingRepository';

export default class ListingAction {



    /**
     *
     * @param n
     */
    clickOnNthProduct(n) {

        const repo = new ListingRepository();

        repo.getNthProduct(n).click();
    }

}
