export default (() => {
    const context = require.context('./creditcard_logos', true, /svg$/);

    return context.keys().reduce((accumulator, item) => {
        const prefix = 'mollie-credit-card-logo-';
        const componentName = item.split('/')[1].split('.')[0];

        const component = {
            name: `${prefix}${componentName}`,
            functional: true,
            render(createElement, elementContext) {

                const data = elementContext.data;

                return createElement('span', {
                    class: [data.staticClass, data.class],
                    style: data.style,
                    attrs: data.attrs,
                    on: data.on,
                    domProps: {
                        innerHTML: context(item).default,
                    },
                });
            },
        };

        accumulator.push(component);
        return accumulator;
    }, []);
})();
