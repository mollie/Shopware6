<?php

declare(strict_types=1);

namespace Mollie\Shopware\Component\Refund\Route;

use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * See AbstractRefundOverviewRoute for why this extends AbstractController.
 */
abstract class AbstractCreateRefundRoute extends AbstractController
{
    abstract public function getDecorated(): self;

    abstract public function create(Request $request, Context $context): JsonResponse;
}
