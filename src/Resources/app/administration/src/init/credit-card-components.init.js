import creditCardComponents from '../assets'

creditCardComponents.forEach((creditCardComponent) => {
    // eslint-disable-next-line no-undef
    Shopware.Component.register(creditCardComponent.name, creditCardComponent);
});
