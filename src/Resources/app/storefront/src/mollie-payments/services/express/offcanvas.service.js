export default class ExpressOffcanvasService {
    /**
     *
     * @param handler
     * @param key a unique key to identify the registration type
     */
    register(handler, key) {
        const offCanvasCartEntry = window.PluginManager.getPluginList().OffCanvasCart;

        if (!offCanvasCartEntry) {
            return;
        }

        const offCanvasInstances = offCanvasCartEntry.get('instances');

        if (!offCanvasInstances) {
            return;
        }

        for (let i = 0; i < offCanvasInstances.length; i++) {
            const offCanvas = offCanvasInstances[i];

            if (!offCanvas['_' + key + '_subscribed']) {
                offCanvas.$emitter.subscribe('registerEvents', handler);
                offCanvas['_' + key + '_subscribed'] = true;
            }
        }
    }
}
