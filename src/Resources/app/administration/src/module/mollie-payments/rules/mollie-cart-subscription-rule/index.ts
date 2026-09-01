import template from './mollie-cart-subscription-rule.html.twig';
import createSubscriptionRuleConfig from '../subscriptionRuleConfig';

const { Component } = Shopware;

Component.extend('mollie-cart-subscription-rule', 'sw-condition-base', createSubscriptionRuleConfig(template));
