import StoreAPIClient from "Services/shopware/StoreAPIClient";
import Shopware from "Services/shopware/Shopware"
import StoreApiLoginAction from "Actions/store-api/StoreApiLoginAction";


const shopware = new Shopware();

const client = new StoreAPIClient(shopware.getStoreApiToken());


// that ones just made up to have a valid URL
const fakeSubscriptionID = '0d8eefdd6d12456335280e2ff42431b9';

const loginAction = new StoreApiLoginAction(client);


function beforeEach() {
    // clear token
    client.clearContextToken();
}


context("Store API Subscription Routes", () => {

    describe('GET /subscription', () => {

        const url = '/mollie/subscription';

        it('C266685: /subscription with unauthorized customer (Store API) @core', () => {

            beforeEach();

            const request = new Promise((resolve) => {
                client.get(url).then(response => {
                    resolve({'data': response.data});
                });
            })
            cy.wrap(request).its('data').then(response => {
                cy.wrap(response).its('status').should('eq', 401)
            });
        })

        it('C266686: /subscription with authorized customer @core', () => {

            beforeEach();

            loginAction.registerAndLogin('loginDone');

            cy.wait('@loginDone').then(() => {

                const request = new Promise((resolve) => {
                    client.get(url).then(response => {
                        resolve({'data': response.data});
                    });
                })

                cy.wrap(request).its('data').then(response => {
                    cy.wrap(response).its('apiAlias').should('eq', 'mollie_payments_subscriptions_list')
                    cy.wrap(response).its('subscriptions').its('length').should('be.gte', 0)
                });
            })
        })

    })


    describe('POST /billing/update', function () {

        const url = '/mollie/subscription/' + fakeSubscriptionID + '/billing/update';

        it('C266687: /billing/update with unauthorized customer @core', () => {

            beforeEach();

            const request = new Promise((resolve) => {
                client.post(url).then(response => {
                    resolve({'data': response.data});
                });
            })
            cy.wrap(request).its('data').then(response => {
                cy.wrap(response).its('status').should('eq', 401)
            });
        })

        it('C266688: /billing/update with authorized customer @core', () => {

            beforeEach();

            loginAction.registerAndLogin('loginDone');

            cy.wait('@loginDone').then(() => {

                const request = new Promise((resolve) => {
                    client.post(url, {}).then(response => {
                        resolve({'data': response.data});
                    });
                })

                cy.wrap(request).its('data').then(response => {
                    cy.wrap(response).its('status').should('eq', 500)
                    expect(response.data.errors[0].detail).to.contain('Subscription ' + fakeSubscriptionID + ' not found in Shopware');
                });
            });
        })

    });

    describe('POST /shipping/update', function () {

        const url = '/mollie/subscription/' + fakeSubscriptionID + '/shipping/update';

        it('C266689: /shipping/update with unauthorized customer @core', () => {

            beforeEach();

            const request = new Promise((resolve) => {
                client.post(url).then(response => {
                    resolve({'data': response.data});
                });
            })
            cy.wrap(request).its('data').then(response => {
                cy.wrap(response).its('status').should('eq', 401)
            });
        })

        it('C266690: /shipping/update with authorized customer @core', () => {

            beforeEach();

            loginAction.registerAndLogin('loginDone');

            cy.wait('@loginDone').then(() => {

                const request = new Promise((resolve) => {
                    client.post(url, {}).then(response => {
                        resolve({'data': response.data});
                    });
                })

                cy.wrap(request).its('data').then(response => {
                    cy.wrap(response).its('status').should('eq', 500)
                    expect(response.data.errors[0].detail).to.contain('Subscription ' + fakeSubscriptionID + ' not found in Shopware');
                });
            });
        })

    });

    describe('POST /payment/update', function () {

        const url = '/mollie/subscription/' + fakeSubscriptionID + '/payment/update';

        it('C266691: /payment/update with unauthorized customer @core', () => {

            beforeEach();

            const request = new Promise((resolve) => {
                client.post(url).then(response => {
                    resolve({'data': response.data});
                });
            })
            cy.wrap(request).its('data').then(response => {
                cy.wrap(response).its('status').should('eq', 401)
            });
        })

        it('C266692: /payment/update with authorized customer @core', () => {

            beforeEach();

            loginAction.registerAndLogin('loginDone');

            cy.wait('@loginDone').then(() => {

                const request = new Promise((resolve) => {
                    client.post(url, {}).then(response => {
                        resolve({'data': response.data});
                    });
                })

                cy.wrap(request).its('data').then(response => {
                    cy.wrap(response).its('status').should('eq', 500)
                    expect(response.data.errors[0].detail).to.contain('Subscription ' + fakeSubscriptionID + ' not found in Shopware');
                });
            });
        })
    });

    describe('POST /pause', function () {

        const url = '/mollie/subscription/' + fakeSubscriptionID + '/pause';

        it('C266693: /pause with unauthorized customer @core', () => {

            beforeEach();

            const request = new Promise((resolve) => {
                client.post(url).then(response => {
                    resolve({'data': response.data});
                });
            })
            cy.wrap(request).its('data').then(response => {
                cy.wrap(response).its('status').should('eq', 401)
            });
        })

        it('C266694: /pause with authorized customer @core', () => {

            beforeEach();

            loginAction.registerAndLogin('loginDone');

            cy.wait('@loginDone').then(() => {

                const request = new Promise((resolve) => {
                    client.post(url, {}).then(response => {
                        resolve({'data': response.data});
                    });
                })

                cy.wrap(request).its('data').then(response => {
                    cy.wrap(response).its('status').should('eq', 500)
                    expect(response.data.errors[0].detail).to.contain('Subscription ' + fakeSubscriptionID + ' not found in Shopware');
                });
            });
        })
    });

    describe('POST /resume', function () {

        const url = '/mollie/subscription/' + fakeSubscriptionID + '/resume';

        it('C266695: /resume with unauthorized customer @core', () => {

            beforeEach();

            const request = new Promise((resolve) => {
                client.post(url).then(response => {
                    resolve({'data': response.data});
                });
            })
            cy.wrap(request).its('data').then(response => {
                cy.wrap(response).its('status').should('eq', 401)
            });
        })

        it('C266696: /resume with authorized customer @core', () => {

            beforeEach();

            loginAction.registerAndLogin('loginDone');

            cy.wait('@loginDone').then(() => {

                const request = new Promise((resolve) => {
                    client.post(url, {}).then(response => {
                        resolve({'data': response.data});
                    });
                })

                cy.wrap(request).its('data').then(response => {
                    cy.wrap(response).its('status').should('eq', 500)
                    expect(response.data.errors[0].detail).to.contain('Subscription ' + fakeSubscriptionID + ' not found in Shopware');
                });
            });
        })
    });

    describe('POST /skip', function () {

        const url = '/mollie/subscription/' + fakeSubscriptionID + '/skip';

        it('C266697: /skip with unauthorized customer @core', () => {

            beforeEach();

            const request = new Promise((resolve) => {
                client.post(url).then(response => {
                    resolve({'data': response.data});
                });
            })
            cy.wrap(request).its('data').then(response => {
                cy.wrap(response).its('status').should('eq', 401)
            });
        })

        it('C266698: /skip with authorized customer @core', () => {

            beforeEach();

            loginAction.registerAndLogin('loginDone');

            cy.wait('@loginDone').then(() => {

                const request = new Promise((resolve) => {
                    client.post(url, {}).then(response => {
                        resolve({'data': response.data});
                    });
                })

                cy.wrap(request).its('data').then(response => {
                    cy.wrap(response).its('status').should('eq', 500)
                    expect(response.data.errors[0].detail).to.contain('Subscription ' + fakeSubscriptionID + ' not found in Shopware');
                });
            });
        })

    });

    describe('POST /cancel', function () {

        const url = '/mollie/subscription/' + fakeSubscriptionID + '/cancel';

        it('C330671: /cancel with unauthorized customer @core', () => {

            beforeEach();

            const request = new Promise((resolve) => {
                client.post(url).then(response => {
                    resolve({'data': response.data});
                });
            })
            cy.wrap(request).its('data').then(response => {
                cy.wrap(response).its('status').should('eq', 401)
            });
        })

        it('C330672: /cancel with authorized customer @core', () => {

            beforeEach();
            
            loginAction.registerAndLogin('loginDone');

            cy.wait('@loginDone').then(() => {

                const request = new Promise((resolve) => {
                    client.post(url, {}).then(response => {
                        resolve({'data': response.data});
                    });
                })

                cy.wrap(request).its('data').then(response => {
                    cy.wrap(response).its('status').should('eq', 500)
                    expect(response.data.errors[0].detail).to.contain('Subscription ' + fakeSubscriptionID + ' not found in Shopware');
                });
            });
        })

    });

})

