<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\Api\Payment;

use Kiener\MolliePayments\Facade\Controller\PaymentReturnFacade;
use Mollie\Api\Exceptions\ApiException;
use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class ReturnControllerBase extends AbstractController
{
    /**
     * @var PaymentReturnFacade
     */
    private $returnFacade;

    public function __construct(PaymentReturnFacade $returnFacade)
    {
        $this->returnFacade = $returnFacade;
    }

    /**
     * @throws ApiException
     */
    public function returnAction(string $swTransactionId, Context $context): ?Response
    {
        return $this->returnFacade->returnAction($swTransactionId, $context);
    }

    /**
     * @throws ApiException
     */
    public function returnActionLegacy(string $swTransactionId, Context $context): ?Response
    {
        return $this->returnFacade->returnAction($swTransactionId, $context);
    }
}
