"use strict";

var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : {"default": mod};
};

Object.defineProperty(exports, "__esModule", {value: true});

var NativeEventEmitter_1 = __importDefault(require("./NativeEventEmitter"));

var deepmerge_1 = __importDefault(require("deepmerge"));

var PluginClass = /** @class */ (function () {

    function PluginClass(el, options, pluginName) {

        if (options === void 0) {
            options = {};
        }
        if (pluginName === void 0) {
            pluginName = false;
        }
        this.el = el;
        this.$emitter = new NativeEventEmitter_1.default(this.el);
        this._pluginName = this._getPluginName(pluginName);
        this.options = this._mergeOptions(options);
        this._initialized = false;
        this._registerInstance();
        this._init();
    }

    PluginClass.prototype._init = function () {
        if (this._initialized)
            return;
        this.init();
        this._initialized = true;
    };

    PluginClass.prototype._update = function () {
        if (!this._initialized)
            return;
        this.update();
    };

    PluginClass.prototype.update = function () {
    };

    PluginClass.prototype._registerInstance = function () {
        var elementPluginInstances = window.PluginManager.getPluginInstancesFromElement(this.el);
        elementPluginInstances.set(this._pluginName, this);
        var plugin = window.PluginManager.getPlugin(this._pluginName, false);
        plugin.get('instances').push(this);
    };

    PluginClass.prototype._getPluginName = function (pluginName) {
        if (pluginName === false) {
            // @ts-ignore
            return this.constructor.name;
        }
        return pluginName;
    };

    PluginClass.prototype._mergeOptions = function (options) {
        var dashedPluginName = this._pluginName.replace(/([A-Z])/g, '-$1').replace(/^-/, '').toLowerCase();
        var dataAttributeConfig = this.parseJsonOrFail(dashedPluginName);

        let dataAttributeOptions = '';

        if (typeof this.el.getAttribute === 'function') {
            dataAttributeOptions = this.el.getAttribute("data-".concat(dashedPluginName, "-options")) || '';
        }

        // static plugin options
        // previously merged options
        // explicit options when creating a plugin instance with 'new'
        var merge = [
            // @ts-ignore
            this.constructor.options,
            this.options,
            options,
        ];

        // options which are set via data-plugin-name-config="config name"
        if (dataAttributeConfig) {
            merge.push(window.PluginConfigManager.get(this._pluginName, dataAttributeConfig));
        }

        // options which are set via data-plugin-name-options="{json..}"
        try {
            if (dataAttributeOptions)
                merge.push(JSON.parse(dataAttributeOptions));
        } catch (e) {
            throw new Error("The data attribute \"data-".concat(dashedPluginName, "-options\" could not be parsed to json: ").concat(e.message || ''));
        }

        return deepmerge_1.default.all(merge.filter(function (config) {
            return config instanceof Object && !(config instanceof Array);
        })
            .map(function (config) {
                return config || {};
            }));
    };

    PluginClass.prototype.parseJsonOrFail = function (dashedPluginName) {

        if (typeof this.el.getAttribute !== 'function') {
            return '';
        }

        const value = this.el.getAttribute("data-".concat(dashedPluginName, "-config")) || '';

        try {
            return JSON.parse(value);
        } catch (e) {
            return value;
        }
    };

    return PluginClass;

}());

exports.default = PluginClass;
