export default class Plugin {
    static options = {};

    constructor(el, options = {}, pluginName = 'Plugin') {
        this.el = el;
        this._pluginName = pluginName;
        this._listeners = [];
        this.options = this._mergeOptions(options);
        this.init();
    }

    /**
     * Registers a DOM event listener and remembers it so destroy() can remove it.
     * Prefer this over target.addEventListener() so listeners are cleaned up when
     * the plugin is torn down or re-initialized (e.g. after an off-canvas cart
     * reload), which avoids duplicate bindings and memory leaks.
     * @param {EventTarget} target
     * @param {string} type
     * @param {function} handler
     * @param {boolean|object} options - passed to add/removeEventListener
     */
    _addListener(target, type, handler, options = false) {
        if (!target || typeof target.addEventListener !== 'function') {
            return;
        }
        target.addEventListener(type, handler, options);
        this._listeners.push({ target, type, handler, options });
    }

    /**
     * Removes every listener registered through _addListener().
     */
    destroy() {
        this._listeners.forEach(function (listener) {
            listener.target.removeEventListener(listener.type, listener.handler, listener.options);
        });
        this._listeners = [];
    }

    _mergeOptions(options) {
        const dashedName = this._pluginName
            .replace(/([A-Z])/g, '-$1')
            .replace(/^-/, '')
            .toLowerCase();
        let dataAttrOptions = {};
        if (this.el && typeof this.el.getAttribute === 'function') {
            const raw = this.el.getAttribute(`data-${dashedName}-options`);
            if (raw) {
                try {
                    dataAttrOptions = JSON.parse(raw);
                } catch (e) {
                    console.error(`[MolliePlugin] Could not parse data-${dashedName}-options`, e);
                }
            }
        }
        return Object.assign({}, this.constructor.options, options, dataAttrOptions);
    }

    init() {}
}
