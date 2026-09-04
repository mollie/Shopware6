import template from './mollie-pluginconfig-section-capture.html.twig';
import './mollie-pluginconfig-section-capture.scss';

const { Component } = Shopware;

// The switches of this card are plain bool fields in config.xml, so that Shopware renders and
// inherits them per sales channel. Only the explanation above them lives here, because a card can
// carry text no other way before Shopware 6.7.13.
Component.register('mollie-pluginconfig-section-capture', {
    template,
});
