"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var NativeEventEmitter = /** @class */ (function () {
    function NativeEventEmitter(el) {
        this._el = el;
        // @ts-ignore
        el.$emitter = this;
        this._listeners = [];
    }
    NativeEventEmitter.prototype.publish = function (eventName, detail, cancelable) {
        if (detail === void 0) { detail = {}; }
        if (cancelable === void 0) { cancelable = false; }
        var event = new CustomEvent(eventName, {
            detail: detail,
            cancelable: cancelable,
        });
        this.el.dispatchEvent(event);
        return event;
    };
    NativeEventEmitter.prototype.subscribe = function (eventName, callback, opts) {
        if (opts === void 0) { opts = {}; }
        var emitter = this;
        var splitEventName = eventName.split('.');
        var cb = opts.scope ? callback.bind(opts.scope) : callback;
        // Support for listeners which are fired once
        if (opts.once && opts.once === true) {
            var onceCallback_1 = cb;
            cb = function onceListener(event) {
                emitter.unsubscribe(eventName);
                onceCallback_1(event);
            };
        }
        this.el.addEventListener(splitEventName[0], cb);
        this.listeners.push({
            splitEventName: splitEventName,
            opts: opts,
            cb: cb,
        });
        return true;
    };
    NativeEventEmitter.prototype.unsubscribe = function (eventName) {
        var _this = this;
        var splitEventName = eventName.split('.');
        this.listeners = this.listeners.reduce(function (accumulator, listener) {
            var foundEvent = listener.splitEventName.sort().toString() === splitEventName.sort().toString();
            if (foundEvent) {
                _this.el.removeEventListener(listener.splitEventName[0], listener.cb);
                return accumulator;
            }
            accumulator.push(listener);
            return accumulator;
        }, []);
        return true;
    };
    NativeEventEmitter.prototype.reset = function () {
        var _this = this;
        this.listeners.forEach(function (listener) {
            _this.el.removeEventListener(listener.splitEventName[0], listener.cb);
        });
        // Reset registry
        this.listeners = [];
        return true;
    };
    Object.defineProperty(NativeEventEmitter.prototype, "el", {
        get: function () {
            return this._el;
        },
        set: function (value) {
            this._el = value;
        },
        enumerable: false,
        configurable: true
    });
    Object.defineProperty(NativeEventEmitter.prototype, "listeners", {
        get: function () {
            return this._listeners;
        },
        set: function (value) {
            this._listeners = value;
        },
        enumerable: false,
        configurable: true
    });
    return NativeEventEmitter;
}());
exports.default = NativeEventEmitter;
