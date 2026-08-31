<?php

declare(strict_types=1);

namespace Mollie\Shopware\Component\Refund\Route;

use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Extends AbstractController on purpose: the response is built with $this->json(), which runs the
 * struct through the Symfony serializer. Answering with a plain JsonResponse instead would encode
 * it through jsonSerialize() and change the payload the administration reads.
 */
abstract class AbstractRefundOverviewRoute extends AbstractController
{
    abstract public function getDecorated(): self;

    abstract public function overview(Request $request, Context $context): JsonResponse;
}
