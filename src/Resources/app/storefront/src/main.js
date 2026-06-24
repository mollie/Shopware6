import MollieRegistration from './register';

window.addEventListener('pageshow', function (event) {
    if (event.persisted) {
        window.location.reload();
    }
});

const molliePlugins = new MollieRegistration();
molliePlugins.register();
