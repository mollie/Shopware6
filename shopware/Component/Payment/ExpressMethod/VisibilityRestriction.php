<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressMethod;

enum VisibilityRestriction: string
{
    case PRODUCT_DETAIL_PAGE = 'pdp';
    case PRODUCT_LISTING_PAGE = 'plp';

    case OFF_CANVAS = 'offcanvas';
    case CART = 'cart';
}
