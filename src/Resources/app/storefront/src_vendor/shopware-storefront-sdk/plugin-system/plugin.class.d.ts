export default abstract class PluginClass {
    private readonly el;
    private $emitter;
    private readonly _pluginName;
    private readonly options;
    private _initialized;
    constructor(el: HTMLElement, options?: any, pluginName?: boolean | string);
    private _init;
    _update(): void;
    abstract init(): void;
    update(): void;
    _registerInstance(): void;
    private _getPluginName;
    private _mergeOptions;
    private parseJsonOrFail;
}
