import template from './mollie-lineitem-subscription-rule.html.twig';
import createSubscriptionRuleConfig from '../subscriptionRuleConfig';

const { Component } = Shopware;

Component.extend('mollie-lineitem-subscription-rule', 'sw-condition-base', createSubscriptionRuleConfig(template));
