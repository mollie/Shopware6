/**
 * Resolves a Shopware store/state module across versions.
 *
 * Shopware <= 6.6 keeps stores in Vuex (`Shopware.State`), whose `get()` returns `undefined`
 * for an unknown id. Shopware 6.7 moved them to Pinia (`Shopware.Store`), whose `get()` THROWS
 * for an unknown id, so it must not be called blindly - the registry is checked first. Pinia
 * wins when the module is registered there, because on 6.7 the Vuex shim can still answer with
 * a stale module.
 */
export function getStore(id: string): any {
    if (Shopware.Store?.list?.().includes(id)) {
        return Shopware.Store.get(id);
    }

    return Shopware.State?.get?.(id) ?? null;
}
