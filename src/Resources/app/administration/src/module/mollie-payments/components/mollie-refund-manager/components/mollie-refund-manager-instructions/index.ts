import template from './mollie-refund-manager-instructions.html.twig';

const { Component } = Shopware;

interface InstructionBlock {
    title: string;
    text: string;
    toggle: () => void;
}

const componentConfig: ThisType<Record<string, any>> = {
    template,

    props: {
        showInstructions: {
            type: Boolean,
            required: false,
            default: true,
        },
        blocksPrimary: {
            type: Array as () => InstructionBlock[],
            required: true,
        },
        blocksSecondary: {
            type: Array as () => InstructionBlock[],
            required: true,
        },
    },
};

Component.register('mollie-refund-manager-instructions', componentConfig);
