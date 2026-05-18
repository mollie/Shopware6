export default class Plugin {
    static options = {};

    constructor(el, options = {}, pluginName = 'Plugin') {
        this.el = el;
        this.options = Object.assign({}, this.constructor.options, options);
        this._pluginName = pluginName;
    }

    init() {}
}
