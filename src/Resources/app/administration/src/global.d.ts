/**
 * Ambient declarations for the Shopware administration plugin build.
 *
 * The Shopware admin webpack/babel build strips types and resolves these imports
 * via loaders, so these declarations only exist to keep TypeScript and the IDE happy.
 */

// The global Shopware object injected by the administration runtime.
// It is intentionally untyped as the plugin does not ship the official Shopware types.
declare const Shopware: any;

declare module '*.twig' {
    const content: string;
    export default content;
}

declare module '*.scss' {
    const content: string;
    export default content;
}

declare module '*.html.twig' {
    const content: string;
    export default content;
}
