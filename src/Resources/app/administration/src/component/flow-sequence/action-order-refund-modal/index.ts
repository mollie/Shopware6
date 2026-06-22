import template from './action-order-refund-modal.twig';
import './action-order-refund-modal.scss';
import createFlowActionModalConfig from '../flowActionModalConfig';

const { Component } = Shopware;

Component.register('mollie-payments-flowsequence-action-order-refund-modal', createFlowActionModalConfig(template));
