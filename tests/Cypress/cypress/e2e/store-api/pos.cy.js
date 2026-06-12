import StoreAPIClient from "Services/shopware/StoreAPIClient";
import Shopware from "Services/shopware/Shopware"


const shopware = new Shopware();

const client = new StoreAPIClient(shopware.getStoreApiToken());

const storeApiPrefix = '/store-api';


context(storeApiPrefix + "/mollie/ideal/issuers", () => {

    it('C1341122: POS fetch terminals (Store API)', async () => {
        const response = await client.get('/mollie/pos/terminals');

        expect(response.data.apiAlias).to.eq('mollie_payments_pos_terminals');
        expect(response.data.terminals.length).to.be.gte(1);
    });

})

context(storeApiPrefix + "/mollie/ideal/store-issuer", () => {

    it('C1341123: POS store terminal with invalid customer id (Store API) @core', async () => {
        const response = await client.post('/mollie/pos/store-terminal/cust-123/ideal_ABNANL2A');

        expect(response.data.message).to.contain('Using deprecated route, please provide "terminalId" in request body for payment');
    });

})
