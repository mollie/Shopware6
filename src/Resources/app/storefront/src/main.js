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

const molliePlugins = new MollieRegistration();
molliePlugins.register();
