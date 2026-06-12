import StoreAPIClient from "Services/shopware/StoreAPIClient";
import Shopware from "Services/shopware/Shopware"

const shopware = new Shopware();

const client = new StoreAPIClient(shopware.getStoreApiToken());


it('C2040032: Mollie Config can be retrieved using Store-API', async () => {
    const response = await client.get('/mollie/config');

    expect(response.data.apiAlias).to.eq('mollie_payments_config');
    expect(response.data.profileId).to.exist;
    expect(response.data.profileId).to.not.eq('');
    expect(response.data.testMode).to.exist;
    expect(response.data.testMode).to.not.eq('');
    expect(response.data.locale).to.exist;
    expect(response.data.locale).to.not.eq('');
    expect(response.data.oneClickPayments).to.exist;
    expect(response.data.oneClickPayments).to.not.eq('');
});
