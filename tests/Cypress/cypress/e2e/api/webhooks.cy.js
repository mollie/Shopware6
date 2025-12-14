context("API Payment Webhooks", () => {

    it('C266659: API Webhook is reachable @core', () => {

        cy.request({url: '/api/mollie/webhook/0d8eefdd6d12456335280e2ff42431b9', failOnStatusCode: false,}).then((response) => {
            // status code needs to be 422 unprocessable entity
            expect(response.status).to.eq(422);
            // also verify the content
            expect(response.body.errors[0].detail).to.eq('Transaction 0d8eefdd6d12456335280e2ff42431b9 not found in Shopware');
        })
    })

    it('C266660: API Legacy Webhook is reachable @core', () => {

        cy.request({url: '/api/v2/mollie/webhook/abc', failOnStatusCode: false,}).then((response) => {

            expect(response.status).to.eq(404);

        })
    })

})

context("API Subscription Webhooks", () => {

    describe('/subscription/mandate/update', function () {

        it('C266661: API Subscription Webhook is reachable @core', () => {

            cy.request({url: '/api/mollie/webhook/subscription/0d8eefdd6d12456335280e2ff42431b9?id=tr_123', failOnStatusCode: false,}).then((response) => {
                // status code needs to be 422 unprocessable entity
                expect(response.status).to.eq(422);
                // also verify the content
                expect(response.body.success).to.eq(false);
                expect(response.body.error).to.eq('Subscription with ID 0d8eefdd6d12456335280e2ff42431b9 not found in Shopware');
            })
        })

        it('C266662: API Subscription Shopware-Legacy Webhook is reachable @core', () => {

            cy.request({url: '/api/v2/mollie/webhook/subscription/abc', failOnStatusCode: false,}).then((response) => {
                // status code needs to be 422 unprocessable entity
                expect(response.status).to.eq(422);
                // also verify the content
                expect(response.body.success).to.eq(false);
                expect(response.body.error).to.eq('Please provide a Mollie Payment ID with the payment that has been done for this subscription');
            })
        })

        it('C266663: API Subscription Webhook with missing payment ID @core', () => {

            cy.request({url: '/api/mollie/webhook/subscription/abc', failOnStatusCode: false,}).then((response) => {
                // status code needs to be 422 unprocessable entity
                expect(response.status).to.eq(422);
                // also verify the content
                expect(response.body.success).to.eq(false);
                expect(response.body.error).to.eq('Please provide a Mollie Payment ID with the payment that has been done for this subscription');
            })
        })

    })

    describe('/subscription/xxx/mandate/update', function () {

        it('C266664: API Subscription mandate update Webhook is reachable @core', () => {

            cy.request({url: '/api/mollie/webhook/subscription/0d8eefdd6d12456335280e2ff42431b9/mandate/update', failOnStatusCode: false,}).then((response) => {
                // status code needs to be 422 unprocessable entity
                expect(response.status).to.eq(422);
                // also verify the content
                expect(response.body.success).to.eq(false);
                expect(response.body.error).to.eq('Subscription 0d8eefdd6d12456335280e2ff42431b9 not found in Shopware');
            })
        })

        it('C266665: API Subscription mandate update Shopware-Legacy Webhook is reachable @core', () => {

            cy.request({url: '/api/v2/mollie/webhook/subscription/0d8eefdd6d12456335280e2ff42431b9/mandate/update', failOnStatusCode: false,}).then((response) => {
                // status code needs to be 422 unprocessable entity
                expect(response.status).to.eq(422);
                // also verify the content
                expect(response.body.success).to.eq(false);
                expect(response.body.error).to.eq('Subscription 0d8eefdd6d12456335280e2ff42431b9 not found in Shopware');
            })
        })
    })

    describe('Legacy (since Plugin > v3.3.0)', function () {
        // these are legacy URLs
        // but we need to keep them for old payments (unfortunately)

        it('C266666: API Legacy Subscription Webhook with invalid subscription ID @core', () => {

            cy.request({url: '/api/mollie/webhook/subscription/0d8eefdd6d12456335280e2ff42431b9/renew?id=tr_123', failOnStatusCode: false,}).then((response) => {
                // status code needs to be 422 unprocessable entity
                expect(response.status).to.eq(422);
                // also verify the content
                expect(response.body.success).to.eq(false);
                expect(response.body.error).to.eq('Subscription with ID 0d8eefdd6d12456335280e2ff42431b9 not found in Shopware');
            })
        })

        it('C266667: API Legacy Subscription Webhook with missing payment ID @core', () => {

            cy.request({url: '/api/mollie/webhook/subscription/abc/renew', failOnStatusCode: false,}).then((response) => {
                // status code needs to be 422 unprocessable entity
                expect(response.status).to.eq(422);
                // also verify the content
                expect(response.body.success).to.eq(false);
                expect(response.body.error).to.eq('Please provide a Mollie Payment ID with the payment that has been done for this subscription');
            })
        })

        it('C266668: API Legacy Subscription Shopware-Legacy Webhook is available @core', () => {

            cy.request({url: '/api/v2/mollie/webhook/subscription/abc/renew', failOnStatusCode: false,}).then((response) => {
                // status code needs to be 422 unprocessable entity
                expect(response.status).to.eq(422);
                // also verify the content
                expect(response.body.success).to.eq(false);
                expect(response.body.error).to.eq('Please provide a Mollie Payment ID with the payment that has been done for this subscription');
            })
        })

    })

})


