import NavigationRepository from 'Repositories/old/storefront/navigation/NavigationRepository';

export default class TopMenuAction {

    /**
     *
     */
    clickOnHome() {

        const repo = new NavigationRepository();

        repo.getHomeMenuItem().click();
    }

}
