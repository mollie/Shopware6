import NavigationRepository from 'Repositories/storefront/navigation/NavigationRepository';

export default class TopMenuAction {

    /**
     *
     */
    clickOnHome() {

        const repo = new NavigationRepository();

        repo.getHomeMenuItem().click();
    }

}
