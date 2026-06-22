import template from './sw-product-detail.html.twig';

const { Component } = Shopware;

Component.override('sw-product-detail', {
    template,
});
