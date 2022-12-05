import StoreAPIClient from "Services/shopware/StoreAPIClient";
import Shopware from "Services/shopware/Shopware"
import StoreApiLoginAction from "Actions/store-api/StoreApiLoginAction";


const shopware = new Shopware();

const client = new StoreAPIClient(shopware.getStoreApiToken());


// that ones just made up to have a valid URL
const fakeSubscriptionID = '0d8eefdd6d12456335280e2ff42431b9';

const loginAction = new StoreApiLoginAction(client);


beforeEach(() => {
    // clear token
    client.clearContextToken();
})


context("Store API Subscription Routes", () => {

    describe('GET /subscription', () => {

        const url = '/mollie/subscription';

        it('/subscription with unauthorized customer @core', () => {
            const request = new Promise((resolve) => {
                client.get(url).then(response => {
                    resolve({'data': response.data});
                });
            })
            cy.wrap(request).its('data').then(response => {
                cy.wrap(response).its('status').should('eq', 401)
            });
        })

        it('/subscription with authorized customer @core', () => {

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

        it('/billing/update with unauthorized customer @core', () => {
            const request = new Promise((resolve) => {
                client.post(url).then(response => {
                    resolve({'data': response.data});
                });
            })
            cy.wrap(request).its('data').then(response => {
                cy.wrap(response).its('status').should('eq', 401)
            });
        })

        it('/billing/update with authorized customer @core', () => {

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

        it('/shipping/update with unauthorized customer @core', () => {
            const request = new Promise((resolve) => {
                client.post(url).then(response => {
                    resolve({'data': response.data});
                });
            })
            cy.wrap(request).its('data').then(response => {
                cy.wrap(response).its('status').should('eq', 401)
            });
        })

        it('/shipping/update with authorized customer @core', () => {

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

        it('/payment/update with unauthorized customer @core', () => {
            const request = new Promise((resolve) => {
                client.post(url).then(response => {
                    resolve({'data': response.data});
                });
            })
            cy.wrap(request).its('data').then(response => {
                cy.wrap(response).its('status').should('eq', 401)
            });
        })

        it('/payment/update with authorized customer @core', () => {

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

        it('/pause with unauthorized customer @core', () => {
            const request = new Promise((resolve) => {
                client.post(url).then(response => {
                    resolve({'data': response.data});
                });
            })
            cy.wrap(request).its('data').then(response => {
                cy.wrap(response).its('status').should('eq', 401)
            });
        })

        it('/pause with authorized customer @core', () => {

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

        it('/resume with unauthorized customer @core', () => {
            const request = new Promise((resolve) => {
                client.post(url).then(response => {
                    resolve({'data': response.data});
                });
            })
            cy.wrap(request).its('data').then(response => {
                cy.wrap(response).its('status').should('eq', 401)
            });
        })

        it('/resume with authorized customer @core', () => {

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

        it('/skip with unauthorized customer @core', () => {
            const request = new Promise((resolve) => {
                client.post(url).then(response => {
                    resolve({'data': response.data});
                });
            })
            cy.wrap(request).its('data').then(response => {
                cy.wrap(response).its('status').should('eq', 401)
            });
        })

        it('/skip with authorized customer @core', () => {

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

