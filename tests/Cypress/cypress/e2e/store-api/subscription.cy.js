import StoreAPIClient from "Services/shopware/StoreAPIClient";
import Shopware from "Services/shopware/Shopware"


const shopware = new Shopware();

const fakeSubscriptionID = '0d8eefdd6d12456335280e2ff42431b9';

const validAddressPayload = {
    salutationId: '00000000000000000000000000000001',
    firstName: 'Cypress',
    lastName: 'Tester',
    street: 'Test Street 1',
    zipcode: '12345',
    city: 'Testville',
    countryId: '00000000000000000000000000000002',
};

const customerEmail = 'cypress@mollie.com';
const customerPassword = 'cypress123';

let client;

beforeEach(() => {
    client = new StoreAPIClient(shopware.getStoreApiToken());
});


context("Store API Subscription Routes", () => {

    describe('GET /subscription', () => {

        const url = '/mollie/subscription';

        it('C266685: /subscription with unauthorized customer (Store API) @core', async () => {
            cy.task('log', '[DEBUG] baseUrl: ' + Cypress.config('baseUrl'));
            cy.task('log', '[DEBUG] client baseURL: ' + client.client.defaults.baseURL);
            cy.task('log', '[DEBUG] storeApiToken: ' + shopware.getStoreApiToken());

            const response = await client.get(url);

            cy.task('log', '[DEBUG] unauthorized GET status: ' + JSON.stringify(response.data?.status ?? response.status));
            cy.task('log', '[DEBUG] unauthorized GET body: ' + JSON.stringify(response.data?.data ?? response.data));

            expect(response.data.status).to.be.oneOf([401, 403]);
        });

        it('C266686: /subscription with authorized customer @core', async () => {
            cy.task('log', '[DEBUG] baseUrl: ' + Cypress.config('baseUrl'));
            cy.task('log', '[DEBUG] client baseURL: ' + client.client.defaults.baseURL);
            cy.task('log', '[DEBUG] storeApiToken: ' + shopware.getStoreApiToken());

            const loginResponse = await client.login(customerEmail, customerPassword);
            cy.task('log', '[DEBUG] login status: ' + JSON.stringify(loginResponse?.data?.status ?? loginResponse?.status));
            cy.task('log', '[DEBUG] login body: ' + JSON.stringify(loginResponse?.data?.data ?? loginResponse?.data));
            cy.task('log', '[DEBUG] contextToken after login: ' + client.contextToken);
            expect(client.contextToken, 'login did not return a context token').to.not.be.null;

            const response = await client.get(url);

            expect(response.data.apiAlias).to.eq('mollie_payments_subscriptions_list');
            expect(response.data.subscriptions.length).to.be.gte(0);
        });

    });


    describe('POST /billing/update', () => {

        const url = '/mollie/subscription/' + fakeSubscriptionID + '/billing/update';

        it('C266687: /billing/update with unauthorized customer @core', async () => {
            const response = await client.post(url);

            expect(response.data.status).to.be.oneOf([401, 403]);
        });

        it('C266688: /billing/update with authorized customer @core', async () => {
            await client.login(customerEmail, customerPassword);
            expect(client.contextToken, 'login did not return a context token').to.not.be.null;

            const response = await client.post(url, validAddressPayload);

            expect(response.data.status).to.eq(500);
            expect(response.data.data.errors[0].detail).to.contain('Subscription with id ' + fakeSubscriptionID + ' was not found');
        });

    });


    describe('POST /shipping/update', () => {

        const url = '/mollie/subscription/' + fakeSubscriptionID + '/shipping/update';

        it('C266689: /shipping/update with unauthorized customer @core', async () => {
            const response = await client.post(url);

            expect(response.data.status).to.be.oneOf([401, 403]);
        });

        it('C266690: /shipping/update with authorized customer @core', async () => {
            await client.login(customerEmail, customerPassword);
            expect(client.contextToken, 'login did not return a context token').to.not.be.null;

            const response = await client.post(url, validAddressPayload);

            expect(response.data.status).to.eq(500);
            expect(response.data.data.errors[0].detail).to.contain('Subscription with id ' + fakeSubscriptionID + ' was not found');
        });

    });


    describe('POST /payment/update', () => {

        const url = '/mollie/subscription/' + fakeSubscriptionID + '/payment/update';

        it('C266691: /payment/update with unauthorized customer @core', async () => {
            const response = await client.post(url);

            expect(response.data.status).to.be.oneOf([401, 403]);
        });

        it('C266692: /payment/update with authorized customer @core', async () => {
            await client.login(customerEmail, customerPassword);
            expect(client.contextToken, 'login did not return a context token').to.not.be.null;

            const response = await client.post(url, {});

            expect(response.data.status).to.eq(500);
            expect(response.data.data.errors[0].detail).to.contain('Subscription with id ' + fakeSubscriptionID + ' was not found');
        });

    });


    describe('POST /pause', () => {

        const url = '/mollie/subscription/' + fakeSubscriptionID + '/pause';

        it('C266693: /pause with unauthorized customer @core', async () => {
            const response = await client.post(url);

            expect(response.data.status).to.be.oneOf([401, 403]);
        });

        it('C266694: /pause with authorized customer @core', async () => {
            await client.login(customerEmail, customerPassword);
            expect(client.contextToken, 'login did not return a context token').to.not.be.null;

            const response = await client.post(url, {});

            expect(response.data.status).to.eq(500);
            expect(response.data.data.errors[0].detail).to.contain('Subscription with id ' + fakeSubscriptionID + ' was not found');
        });

    });


    describe('POST /resume', () => {

        const url = '/mollie/subscription/' + fakeSubscriptionID + '/resume';

        it('C266695: /resume with unauthorized customer @core', async () => {
            const response = await client.post(url);

            expect(response.data.status).to.be.oneOf([401, 403]);
        });

        it('C266696: /resume with authorized customer @core', async () => {
            await client.login(customerEmail, customerPassword);
            expect(client.contextToken, 'login did not return a context token').to.not.be.null;

            const response = await client.post(url, {});

            expect(response.data.status).to.eq(500);
            expect(response.data.data.errors[0].detail).to.contain('Subscription with id ' + fakeSubscriptionID + ' was not found');
        });

    });


    describe('POST /skip', () => {

        const url = '/mollie/subscription/' + fakeSubscriptionID + '/skip';

        it('C266697: /skip with unauthorized customer @core', async () => {
            const response = await client.post(url);

            expect(response.data.status).to.be.oneOf([401, 403]);
        });

        it('C266698: /skip with authorized customer @core', async () => {
            await client.login(customerEmail, customerPassword);
            expect(client.contextToken, 'login did not return a context token').to.not.be.null;

            const response = await client.post(url, {});

            expect(response.data.status).to.eq(500);
            expect(response.data.data.errors[0].detail).to.contain('Subscription with id ' + fakeSubscriptionID + ' was not found');
        });

    });


    describe('POST /cancel', () => {

        const url = '/mollie/subscription/' + fakeSubscriptionID + '/cancel';

        it('C330671: /cancel with unauthorized customer @core', async () => {
            const response = await client.post(url);

            expect(response.data.status).to.be.oneOf([401, 403]);
        });

        it('C330672: /cancel with authorized customer @core', async () => {
            await client.login(customerEmail, customerPassword);
            expect(client.contextToken, 'login did not return a context token').to.not.be.null;

            const response = await client.post(url, {});

            expect(response.data.status).to.eq(500);
            expect(response.data.data.errors[0].detail).to.contain('Subscription with id ' + fakeSubscriptionID + ' was not found');
        });

    });

})
