import { Application } from 'src/core/shopware';

/**
 * Refresh token helper which manages a cache of requests to retry them after the token got refreshed.
 * @class
 */
export default class RefreshTokenHelper {
    constructor() {
        this._subscribers = [];
        this._isRefreshing = false;
    }

    /**
     * Subscribe a new callback to the cache queue
     *
     * @param {Function} [callback = () => {}]
     */
    subscribe(callback = () => {}) {
        this._subscribers.push(callback);
    }

    /**
     * Event handler which will be fired when the token got refresh. It iterates over the registered
     * subscribers and fires the callbacks with the new token.
     *
     * @param {String} token - Renewed access token
     */
    onRefreshToken(token) {
        this._subscribers = this._subscribers.reduce((accumulator, callback) => {
            callback.call(null, token);
            return accumulator;
        }, []);
    }

    /**
     * Fires the refresh token request and renews the bearer authentication in the login service.
     *
     * @returns {Promise<String>}
     */
    fireRefreshTokenRequest() {
        const providerContainer = Application.getContainer('service');
        const loginService = providerContainer.loginService;
        const refreshToken = loginService.getRefreshToken();

        this.isRefreshing = true;
        return loginService.refreshTokenUsingRefreshToken(refreshToken).then((response) => {
            const refresh = loginService.getBearerAuthentication('refresh');
            this.isRefreshing = false;

            loginService.setBearerAuthentication({
                access: response.data.access_token,
                expiry: response.data.expires_in,
                refresh
            });
            return response.data.access_token;
        });
    }

    get isRefreshing() {
        return this._isRefreshing;
    }

    set isRefreshing(value) {
        this._isRefreshing = value;
    }
}