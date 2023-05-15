interface NativeEventEmitterPublish {
    detail?: object;
    cancelable?: boolean;
}
interface NativeEventEmitterSubscribeOpts {
    once?: boolean;
    scope?: Function;
}
export default class NativeEventEmitter {
    private _listeners;
    private _el;
    constructor(el: HTMLElement);
    publish(eventName: string, detail?: NativeEventEmitterPublish, cancelable?: boolean): CustomEvent;
    subscribe(eventName: string, callback: Function, opts?: NativeEventEmitterSubscribeOpts): boolean;
    unsubscribe(eventName: String): boolean;
    reset(): boolean;
    get el(): HTMLElement;
    set el(value: HTMLElement);
    get listeners(): any[];
    set listeners(value: any[]);
}
export {};
