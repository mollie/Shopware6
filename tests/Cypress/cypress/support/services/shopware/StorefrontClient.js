import axios from 'axios';

export default class StorefrontClient {


    /**
     *
     */
    constructor() {
        this.basePath = '';

        this.client = axios.create({
            baseURL: `${Cypress.config('baseUrl')}`,
            timeout: 10000,
        });
    }

    /**
     *
     * @param url
     * @param params
     * @returns {*}
     */
    get(url, params = {}) {
        return this.request({
            method: 'get',
            url: url,
            params: params
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
            url:url,
            data:data,
            params: params
        });
    }

    /**
     *
     * @param url
     * @param params
     * @returns {*}
     */
    delete(url, params = {}) {
        return this.request({
            method: 'delete',
            url:url,
            params:params
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
            url:url,
            params:params
        });
    }

    /**
     *
     * @param url
     * @param params
     * @returns {*}
     */
    options(url, params = {}) {
        return this.request({
            method: 'options',
            url:url,
            params:params
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
            data:data,
            url:url,
            params:params
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
            data:data,
            url:url,
            params: params
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
            url: url,
            method: method,
            params: params,
            data: data
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
            'Content-Type': 'application/json'
        };
    }

}
