import axios from 'axios';

export default class AdminAPIClient {


    /**
     *
     */
    constructor() {
        this.authInformation = {};
        this.basePath = '';

        this.client = axios.create({
            baseURL: `${Cypress.config('baseUrl')}/api`
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
        return this.loginByUserName().then(() => {
            const requestConfig = {
                headers: this.getHeaders(),
                url,
                method,
                params,
                data
            };

            return this.client.request(requestConfig).then((response) => {
                if (Array.isArray(response.data.data) && response.data.data.length === 1) {
                    return response.data.data[0];
                }
                return response.data.data;
            });
        }).catch(({response}) => {
            if (response.data && response.data.errors) {
                console.log(response.data.errors);
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
            Authorization: `Bearer ${this.authInformation.access_token}`,
            'Content-Type': 'application/json'
        };
    }

    /**
     * Renders an header to stdout including information about the available flags.
     *
     * @param {String} username
     * @param {String} password
     * @returns {Object}
     */
    loginByUserName(username = 'admin', password = 'shopware') {
        return this.client.post('/oauth/token', {
            grant_type: 'password',
            client_id: 'administration',
            scopes: 'write',
            username: username,
            password: password
        }).catch((err) => {
            console.log(Promise.reject(err.data));
        }).then((response) => {
            this.authInformation = response.data;
            return this.authInformation;
        });
    }

}
