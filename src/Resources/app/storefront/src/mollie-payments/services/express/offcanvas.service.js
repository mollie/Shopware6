export default class ExpressOffcanvasService {

    register(handler, topic) {
        const offCanvasInstances = window.PluginManager.getPluginList().OffCanvasCart.get('instances');

        if (offCanvasInstances) {
            for (let i = 0; i < offCanvasInstances.length; i++) {
                const offCanvas = offCanvasInstances[i];

                if (!offCanvas['_' + topic + '_subscribed']) {
                    offCanvas.$emitter.subscribe('offCanvasOpened', handler);
                    offCanvas['_' + topic + '_subscribed'] = true;
                }
            }
        }
    }

}