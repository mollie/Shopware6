export default class EventHandlerUtils {
    addEventListenerOnce(element, handler, eventName = 'click') {
        const key = '__handler_' + eventName;

        if (element[key]) return;

        element.addEventListener(eventName, handler);
        element[key] = true;
    }
}
