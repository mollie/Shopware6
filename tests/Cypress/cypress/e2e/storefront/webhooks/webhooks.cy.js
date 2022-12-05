context("Storefront Payment Webhooks", () => {

    it('Webhook with invalid UUID @core', () => {

        cy.request({url: '/mollie/webhook/abc', failOnStatusCode: false,}).then((response) => {
            // status code needs to be 422 unprocessable entity
            expect(response.status).to.eq(422);
            // also verify the content
            expect(response.body.success).to.eq(false);
            expect(response.body.error).to.eq('Value is not a valid UUID: abc');
        })
    })

    it('Webhook with invalid Transaction ID @core', () => {

        cy.request({url: '/mollie/webhook/0d8eefdd6d12456335280e2ff42431b9', failOnStatusCode: false,}).then((response) => {
            // status code needs to be 422 unprocessable entity
            expect(response.status).to.eq(422);
            // also verify the content
            expect(response.body.success).to.eq(false);
            expect(response.body.error).to.eq('Transaction 0d8eefdd6d12456335280e2ff42431b9 not found in Shopware');
        })
    })

})


context("Storefront Subscription Webhooks", () => {

    it('Subscription Webhook with missing Payment ID @core', () => {

        cy.request({url: '/mollie/webhook/subscription/abc', failOnStatusCode: false,}).then((response) => {
            // status code needs to be 422 unprocessable entity
            expect(response.status).to.eq(422);
            // also verify the content
            expect(response.body.success).to.eq(false);
            expect(response.body.error).to.eq('Please provide a Mollie Payment ID with the payment that has been done for this subscription');
        })
    })

    it('Subscription Webhook with invalid Subscription ID @core', () => {

        cy.request({url: '/mollie/webhook/subscription/0d8eefdd6d12456335280e2ff42431b9?id=tr_123', failOnStatusCode: false,}).then((response) => {
            // status code needs to be 422 unprocessable entity
            expect(response.status).to.eq(422);
            // also verify the content
            expect(response.body.success).to.eq(false);
            expect(response.body.error).to.eq('Subscription with ID 0d8eefdd6d12456335280e2ff42431b9 not found in Shopware');
        })
    })

    it('Subscription Payment Method Webhook not existing in Storefront @core', () => {

        cy.request({url: '/mollie/webhook/subscription/abc/mandate/update', failOnStatusCode: false,}).then((response) => {
            // this one does not make sense in Storefront, so we must not build it!
            // it's immediately done within the account inside the Storefront
            expect(response.status).to.eq(404);
        })
    })


    describe('Legacy (since Plugin > v3.3.0)', function () {
        // these are legacy URLs
        // but we need to keep them for old payments (unfortunately)

        it('Legacy Subscription Webhook with missing Payment ID @core', () => {

            cy.request({url: '/mollie/webhook/subscription/abc/renew', failOnStatusCode: false,}).then((response) => {
                // status code needs to be 422 unprocessable entity
                expect(response.status).to.eq(422);
                // also verify the content
                expect(response.body.success).to.eq(false);
                expect(response.body.error).to.eq('Please provide a Mollie Payment ID with the payment that has been done for this subscription');
            })
        })

        it('Legacy Subscription Webhook with invalid Subscription ID @core', () => {

            cy.request({url: '/mollie/webhook/subscription/0d8eefdd6d12456335280e2ff42431b9/renew?id=tr_123', failOnStatusCode: false,}).then((response) => {
                // status code needs to be 422 unprocessable entity
                expect(response.status).to.eq(422);
                // also verify the content
                expect(response.body.success).to.eq(false);
                expect(response.body.error).to.eq('Subscription with ID 0d8eefdd6d12456335280e2ff42431b9 not found in Shopware');
            })
        })

    });

})

