context("API Payment Webhooks", () => {

    it('Webhook with invalid UUID @core', () => {

        cy.request({url: '/api/mollie/webhook/abc', failOnStatusCode: false,}).then((response) => {
            // status code needs to be 422 unprocessable entity
            expect(response.status).to.eq(422);
            // also verify the content
            expect(response.body.success).to.eq(false);
            expect(response.body.error).to.eq('Value is not a valid UUID: abc');
        })
    })

    it('Webhook with invalid Transaction ID @core', () => {

        cy.request({url: '/api/mollie/webhook/0d8eefdd6d12456335280e2ff42431b9', failOnStatusCode: false,}).then((response) => {
            // status code needs to be 422 unprocessable entity
            expect(response.status).to.eq(422);
            // also verify the content
            expect(response.body.success).to.eq(false);
            expect(response.body.error).to.eq('Transaction 0d8eefdd6d12456335280e2ff42431b9 not found in Shopware');
        })
    })

    it('Legacy Webhook is available @core', () => {

        cy.request({url: '/api/v2/mollie/webhook/abc', failOnStatusCode: false,}).then((response) => {
            // status code needs to be 422 unprocessable entity
            expect(response.status).to.eq(422);
            // also verify the content
            expect(response.body.success).to.eq(false);
            expect(response.body.error).to.eq('Value is not a valid UUID: abc');
        })
    })

})

context("API Subscription Webhooks", () => {

    it('Renew Webhook with missing payment ID @core', () => {

        cy.request({url: '/api/mollie/webhook/subscription/abc/renew', failOnStatusCode: false,}).then((response) => {
            // status code needs to be 422 unprocessable entity
            expect(response.status).to.eq(422);
            // also verify the content
            expect(response.body.success).to.eq(false);
            expect(response.body.error).to.eq('Please provide a Mollie Payment ID with the payment that has been done for this subscription');
        })
    })

    it('Renew Webhook with invalid subscription ID @core', () => {

        cy.request({url: '/api/mollie/webhook/subscription/0d8eefdd6d12456335280e2ff42431b9/renew?id=tr_123', failOnStatusCode: false,}).then((response) => {
            // status code needs to be 422 unprocessable entity
            expect(response.status).to.eq(422);
            // also verify the content
            expect(response.body.success).to.eq(false);
            expect(response.body.error).to.eq('Subscription with ID 0d8eefdd6d12456335280e2ff42431b9 not found in Shopware');
        })
    })

    it('Renew Legacy Webhook is available @core', () => {

        cy.request({url: '/api/v2/mollie/webhook/subscription/abc/renew', failOnStatusCode: false,}).then((response) => {
            // status code needs to be 422 unprocessable entity
            expect(response.status).to.eq(422);
            // also verify the content
            expect(response.body.success).to.eq(false);
            expect(response.body.error).to.eq('Please provide a Mollie Payment ID with the payment that has been done for this subscription');
        })
    })

})


