context("Storefront Payment Webhooks", () => {

    it('C266653: Storefront Webhook Route reachable @core', () => {

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

    it('C266655: Subscription Webhook is reachable @core', () => {

        cy.request({url: '/mollie/webhook/subscription/0d8eefdd6d12456335280e2ff42431b9?id=tr_123', failOnStatusCode: false,}).then((response) => {
            // status code needs to be 422 unprocessable entity
            expect(response.status).to.eq(422);
            // also verify the content
            expect(response.body.success).to.eq(false);
            expect(response.body.error).to.eq('Subscription with ID 0d8eefdd6d12456335280e2ff42431b9 not found in Shopware');
        })
    })

    it('C266654: Subscription Webhook requires Payment ID @core', () => {

        cy.request({url: '/mollie/webhook/subscription/abc', failOnStatusCode: false,}).then((response) => {
            // status code needs to be 422 unprocessable entity
            expect(response.status).to.eq(422);
            // also verify the content
            expect(response.body.success).to.eq(false);
            expect(response.body.error).to.eq('Please provide a Mollie Payment ID with the payment that has been done for this subscription');
        })
    })

    it('C266656: Subscription Payment Method Webhook must not exist in Storefront @core', () => {

        cy.request({url: '/mollie/webhook/subscription/abc/mandate/update', failOnStatusCode: false,}).then((response) => {
            // this one does not make sense in Storefront, so we must not build it!
            // it's immediately done within the account inside the Storefront
            expect(response.status).to.eq(404);
        })
    })


    describe('Legacy (since Plugin > v3.3.0)', function () {
        // these are legacy URLs
        // but we need to keep them for old payments (unfortunately)

        it('C266657: Legacy Subscription Webhook reachable @core', () => {

            cy.request({url: '/mollie/webhook/subscription/0d8eefdd6d12456335280e2ff42431b9/renew?id=tr_123', failOnStatusCode: false,}).then((response) => {
                // status code needs to be 422 unprocessable entity
                expect(response.status).to.eq(422);
                // also verify the content
                expect(response.body.success).to.eq(false);
                expect(response.body.error).to.eq('Subscription with ID 0d8eefdd6d12456335280e2ff42431b9 not found in Shopware');
            })
        })

        it('C266658: Legacy Subscription Webhook requires Payment ID @core', () => {

            cy.request({url: '/mollie/webhook/subscription/abc/renew', failOnStatusCode: false,}).then((response) => {
                // status code needs to be 422 unprocessable entity
                expect(response.status).to.eq(422);
                // also verify the content
                expect(response.body.success).to.eq(false);
                expect(response.body.error).to.eq('Please provide a Mollie Payment ID with the payment that has been done for this subscription');
            })
        })

    });

})

