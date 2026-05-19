export default class Plugin {
    static options = {};

    constructor(el, options = {}, pluginName = 'Plugin') {
        this.el = el;
        this._pluginName = pluginName;
        this.options = this._mergeOptions(options);
        this.init();
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
