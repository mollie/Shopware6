import Shopware from "Services/shopware/Shopware"

const shopware = new Shopware


export default class StoreApiLoginAction {


    constructor(client) {
        this.client = client;
    }


    login(completionAlias)
    {
        const email = 'cypress@mollie.com';
        const pwd = 'cypress123';

        cy.intercept('**/account/login').as(completionAlias);

        const loginPromise = this.client.post('/account/login',
            {
                "email": email,
                "password": pwd,
            }
        );

        cy.wrap(loginPromise).then((response) => {
            let loginToken = response.data.contextToken;
            if(loginToken === undefined){
                loginToken = response.headers["sw-context-token"];
            }
            this.client.setContextToken(loginToken);
            cy.log('Context-Token: ' + loginToken);
        });
    }

    /**
     *
     * @param completionAlias
     */
    registerAndLogin(completionAlias) {

        cy.intercept('**/account/login').as(completionAlias);


        const email = 'cypress-api@mollie.com';
        const pwd = 'myCypressPwd';

        var salutationId = '';
        var countryId = '';

        const requestSalutations = this.client.post('/salutation', {});

        cy.wrap(requestSalutations).then((response) => {

            if (response.data.elements) {
                salutationId = response.data.elements[0].id;
            } else {
                salutationId = response.data[0].id;
            }

            cy.log('API > Salutation loaded: ' + salutationId);

            const requestCountries = this.client.post('/country', {});

            cy.wrap(requestCountries).then((response) => {

                response.data.elements.forEach((country) => {
                    if (country.iso === 'DE') {
                        countryId = country.id;
                    }
                });

                if (countryId === '') {
                    countryId = response.data.elements[0].id;
                }


                cy.log('API > Germany (Country) loaded: ' + countryId);

                const requestRegister = this.client.post('/account/register',
                    {
                        "email": email,
                        "password": pwd,
                        "salutationId": salutationId,
                        "firstName": "Cypress",
                        "lastName": "StoreApi",
                        "acceptedDataProtection": true,
                        "storefrontUrl": shopware.getStorefrontDomain(),
                        "billingAddress": {
                            "firstName": "string",
                            "lastName": "string",
                            "zipcode": "string",
                            "city": "string",
                            "street": "string",
                            "countryId": countryId
                        }
                    }
                );

                cy.wrap(requestRegister).then((response) => {

                    const status = response.data.status;
                    if (status !== 200) {
                        const firstError = response.data.data.errors[0].code;
                        // this error is OK, it just means we are already registered
                        // other errors are NOT OK!
                        cy.wrap(firstError).should('equal', 'VIOLATION::CUSTOMER_EMAIL_NOT_UNIQUE');
                    }

                    cy.log('API > Registration executed');

                    const loginPromise = this.client.post('/account/login',
                        {
                            "email": email,
                            "password": pwd,
                        }
                    );

                    cy.wrap(loginPromise).then((response) => {
                        let loginToken = response.data.contextToken;
                        if(loginToken === undefined){
                            loginToken = response.headers["sw-context-token"];
                        }
                        this.client.setContextToken(loginToken);
                        cy.log('Context-Token: ' + loginToken);
                    });
                });

            });

        })

    }

}
