/**
 * Resolves a Shopware store/state module across versions.
 *
 * Shopware <= 6.6 keeps stores in Vuex (`Shopware.State`), whose `get()` returns `undefined`
 * for an unknown id. Shopware 6.7 moved them to Pinia (`Shopware.Store`), whose `get()` THROWS
 * for an unknown id (so optional chaining alone does not make it safe). We therefore check the
 * Vuex state first (undefined-safe) and only then fall back to the Pinia store, swallowing the
 * throw so callers always get the module or `null`.
 */
export function getStore(id: string): any {
    const stateModule = Shopware.State?.get?.(id);
    if (stateModule !== undefined) {
        return stateModule;
    }

    try {
        return Shopware.Store?.get?.(id) ?? null;
    } catch {
        return null;
    }
}
