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
        this.contextToken = null;
    }

    /**
     * @param email
     * @param password
     * @returns {Promise}
     */
    login(email, password) {
        // Obtain a fresh anonymous context token first. Without this, Shopware's
        // CartRestorer.restore() may find and reuse an existing customer context
        // (searched via OR customer_id), which can be stale or expired. The
        // stale context loses its customer association and subsequent requests
        // return 403 even though login returned 200.
        const contextFetch = this.contextToken
            ? Promise.resolve()
            : this.get('/context').then((contextResp) => {
                const freshToken = contextResp && contextResp.headers && contextResp.headers['sw-context-token'];
                if (freshToken) {
                    this.setContextToken(freshToken);
                }
            });

        return contextFetch.then(() => {
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
                const newToken = response && response.headers && response.headers['sw-context-token'];
                if (newToken && newToken !== this.contextToken) {
                    this.setContextToken(newToken);
                }
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
        const headers = {
            Accept: 'application/vnd.api+json',
            'sw-access-key': this.salesChannelApiKey,
            'Content-Type': 'application/json'
        };

        if (this.contextToken) {
            headers['sw-context-token'] = this.contextToken;
        }

        return headers;
    }

}
