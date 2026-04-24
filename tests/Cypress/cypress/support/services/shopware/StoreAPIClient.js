import axios from 'axios';

export default class StoreAPIClient {


    /**
     *
     * @param salesChannelApiKey
     */
    constructor(salesChannelApiKey) {
        this.salesChannelApiKey = salesChannelApiKey;
        this.authInformation = {};
        this.basePath = '';

        this.clearContextToken();

        this.client = axios.create({
            baseURL: `${Cypress.config('baseUrl')}/store-api`,
            timeout: 10000,
        });
    }

    /**
     *
     * @param token
     */
    setContextToken(token) {
        this.contextToken = token;
    }

    /**
     *
     */
    clearContextToken() {
        this.contextToken = '-'; // empty is not allowed
    }

    /**
     * Logs in the given customer against the Store API and stores the
     * returned context token on this client, so that subsequent requests
     * are authenticated as this customer.
     *
     * @param email
     * @param password
     * @returns {Promise}
     */
    login(email, password) {
        return this.post('/account/login', {
            email: email,
            password: password,
        }).then((response) => {
            let token;

            if (response && response.data && response.data.contextToken) {
                token = response.data.contextToken;
            } else if (response && response.headers && response.headers['sw-context-token']) {
                token = response.headers['sw-context-token'];
            }

            if (token) {
                this.setContextToken(token);
            }

            return response;
        });
    }

    /**
     *
     * @param url
     * @param data
     * @param params
     * @returns {*}
     */
    get(url, params = {}) {
        return this.request({
            method: 'get',
            url,
            params
        });
    }

    /**
     *
     * @param url
     * @param data
     * @param params
     * @returns {*}
     */
    post(url, data, params = {}) {
        return this.request({
            method: 'post',
            url,
            data,
            params
        });
    }

    /**
     *
     * @param url
     * @param data
     * @param params
     * @returns {*}
     */
    delete(url, params = {}) {
        return this.request({
            method: 'delete',
            url,
            params
        });
    }

    /**
     *
     * @param url
     * @param data
     * @param params
     * @returns {*}
     */
    head(url, params = {}) {
        return this.request({
            method: 'head',
            url,
            params
        });
    }

    /**
     *
     * @param url
     * @param data
     * @param params
     * @returns {*}
     */
    options(url, params = {}) {
        return this.request({
            method: 'options',
            url,
            params
        });
    }

    /**
     *
     * @param url
     * @param data
     * @param params
     * @returns {*}
     */
    put(url, data, params = {}) {
        return this.request({
            method: 'put',
            data,
            url,
            params
        });
    }

    /**
     *
     * @param url
     * @param data
     * @param params
     * @returns {*}
     */
    patch(url, data, params = {}) {
        return this.request({
            method: 'patch',
            data,
            url,
            params
        });
    }

    /**
     *
     * @param url
     * @param method
     * @param params
     * @param data
     * @returns {*}
     */
    request({url, method, params, data}) {

        const requestConfig = {
            headers: this.getHeaders(),
            url,
            method,
            params,
            data
        };

        return this.client.request(requestConfig)
            .then((response) => {
                return response;
            })
            .catch(function (error) {
                return {
                    'data': error.response,
                }
            });
    }

    /**
     * Returns the necessary headers for the administration API requests
     *
     * @returns {Object}
     */
    getHeaders() {
        return {
            Accept: 'application/vnd.api+json',
            'sw-access-key': this.salesChannelApiKey,
            'sw-context-token': this.contextToken,
            'Content-Type': 'application/json'
        };
    }

}
