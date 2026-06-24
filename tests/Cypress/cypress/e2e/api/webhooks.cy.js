let adminToken = null;

before(() => {
    cy.request({
        method: 'POST',
        url: '/api/oauth/token',
        body: {
            grant_type: 'password',
            client_id: 'administration',
            scopes: 'write',
            username: 'admin',
            password: 'shopware',
        },
        failOnStatusCode: false,
    }).then((response) => {
        adminToken = response.body.access_token;
    });
});

context("API Payment Webhooks", () => {

    it('C266659: API Webhook is reachable @core', () => {

        cy.request({url: '/api/mollie/webhook/0d8eefdd6d12456335280e2ff42431b9', failOnStatusCode: false, headers: {Authorization: 'Bearer ' + adminToken}}).then((response) => {
            // status code needs to be 422 unprocessable entity
            expect(response.status).to.eq(422);
            // also verify the content
            expect(response.body.errors[0].detail).to.eq('Transaction 0d8eefdd6d12456335280e2ff42431b9 not found in Shopware');
        })
    })

    it('C266660: API Legacy Webhook is reachable @core', () => {

        cy.request({url: '/api/v2/mollie/webhook/abc', failOnStatusCode: false, headers: {Authorization: 'Bearer ' + adminToken}}).then((response) => {

            expect(response.status).to.eq(404);

        })
    })

})

context("API Subscription Webhooks", () => {

    describe('/subscription/mandate/update', function () {

        it('C266661: API Subscription Webhook is reachable @core', () => {

            cy.request({url: '/api/mollie/webhook/subscription/0d8eefdd6d12456335280e2ff42431b9?id=tr_123', failOnStatusCode: false, headers: {Authorization: 'Bearer ' + adminToken}}).then((response) => {

                expect(response.status).to.eq(500);

                expect(response.body.errors[0].detail).to.eq('Subscription with id 0d8eefdd6d12456335280e2ff42431b9 was not found');
            })
        })



        it('C266663: API Subscription Webhook with missing payment ID @core', () => {

            cy.request({url: '/api/mollie/webhook/subscription/abc', failOnStatusCode: false, headers: {Authorization: 'Bearer ' + adminToken}}).then((response) => {
                // status code needs to be 422 unprocessable entity
                expect(response.status).to.eq(422);

                expect(response.body.errors[0].detail).to.eq('Subscription webhook without mollie payment id: abc');
            })
        })

    })

    describe('/subscription/xxx/mandate/update', function () {

        it('C266664: API Subscription mandate update Webhook is reachable @core', () => {

            cy.request({url: '/api/mollie/webhook/subscription/0d8eefdd6d12456335280e2ff42431b9/mandate/update', failOnStatusCode: false, headers: {Authorization: 'Bearer ' + adminToken}}).then((response) => {
                // status code needs to be 422 unprocessable entity
                expect(response.status).to.eq(422);
                // also verify the content
                expect(response.body.success).to.eq(false);
                expect(response.body.error).to.eq('Subscription with id 0d8eefdd6d12456335280e2ff42431b9 was not found');
            })
        })

        it('C266665: API Subscription mandate update Shopware-Legacy Webhook is not available @core', () => {

            cy.request({url: '/api/v2/mollie/webhook/subscription/0d8eefdd6d12456335280e2ff42431b9/mandate/update', failOnStatusCode: false, headers: {Authorization: 'Bearer ' + adminToken}}).then((response) => {

                expect(response.status).to.eq(404);

            })
        })
    })

    describe('Legacy (since Plugin > v3.3.0)', function () {
        // these are legacy URLs
        // but we need to keep them for old payments (unfortunately)

        it('C266666: API Legacy Subscription Webhook with invalid subscription ID @core', () => {

            cy.request({url: '/api/mollie/webhook/subscription/0d8eefdd6d12456335280e2ff42431b9/renew?id=tr_123', failOnStatusCode: false, headers: {Authorization: 'Bearer ' + adminToken}}).then((response) => {
                // status code needs to be 422 unprocessable entity

                expect(response.status).to.eq(500);
                // also verify the content

                expect(response.body.errors[0].detail).to.eq('Subscription with id 0d8eefdd6d12456335280e2ff42431b9 was not found');
            })
        })

        it('C266667: API Legacy Subscription Webhook with missing payment ID @core', () => {

            cy.request({url: '/api/mollie/webhook/subscription/abc/renew', failOnStatusCode: false, headers: {Authorization: 'Bearer ' + adminToken}}).then((response) => {
                // status code needs to be 422 unprocessable entity
                expect(response.status).to.eq(422);

                expect(response.body.errors[0].detail).to.eq('Subscription webhook without mollie payment id: abc');
            })
        })

        it('C266668: API Legacy Subscription Shopware-Legacy Webhook is available @core', () => {

            cy.request({url: '/api/v2/mollie/webhook/subscription/abc/renew', failOnStatusCode: false, headers: {Authorization: 'Bearer ' + adminToken}}).then((response) => {
                // status code needs to be 422 unprocessable entity
                expect(response.status).to.eq(404);
            })
        })

    })

})