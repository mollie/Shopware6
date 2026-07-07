import MollieRegistration from './register';

// When the browser restores the edit-order page from the bfcache (e.g. after a
// browser back from Mollie), the restored DOM still shows the previously chosen
// payment method. Force a reload there so a repeated payment switch works. Scoped
// to the edit-order page so the rest of the shop keeps its bfcache back navigation.
window.addEventListener('pageshow', function (event) {
    if (event.persisted && window.location.pathname.includes('/account/order/edit/')) {
        window.location.reload();
    }
});

// For cross-version compatibility the plugin ships its compiled storefront JS in
// both the flat (Shopware <=6.5) and the sub-folder (Shopware 6.6+) layout. Shopware
// 6.5 collects both during theme:compile, so this bundle can end up in all.js twice
// and execute a second time. The second run would hit PluginManager.register() with
// an already-registered plugin and throw, aborting the whole storefront JS. Guard on
// a shared window flag so the registration runs exactly once per page.
if (!window.__mollieStorefrontRegistered) {
    window.__mollieStorefrontRegistered = true;

    const molliePlugins = new MollieRegistration();
    molliePlugins.register();
}
