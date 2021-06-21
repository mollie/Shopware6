import NavigationRepository from 'Repositories/6.4/storefront/navigation/NavigationRepository';

export default class TopMenuAction {

    /**
     *
     */
    clickOnHome() {

        const repo = new NavigationRepository();

        repo.getHomeMenuItem().click();
    }

}
