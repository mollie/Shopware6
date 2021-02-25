<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Storefront\Controller;

use Kiener\MolliePayments\Subscriber\OrderRepeaterSubscriber;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\CheckoutController;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Annotation\Route;

class BrowserBackHookController extends StorefrontController
{
    /**
     * @var Session
     */
    private $session;
    /**
     * @var EntityRepositoryInterface
     */
    private $orderRepository;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var CheckoutController
     */
    private $checkoutController;

    /**
     * @param Session $session
     * @param EntityRepositoryInterface $orderRepository
     * @param CheckoutController $checkoutController
     * @param LoggerInterface $logger
     */
    public function __construct(Session $session, EntityRepositoryInterface $orderRepository, CheckoutController $checkoutController, LoggerInterface $logger)
    {
        $this->session = $session;
        $this->orderRepository = $orderRepository;
        $this->logger = $logger;
        $this->checkoutController = $checkoutController;
    }


    /**
     * @RouteScope(scopes={"storefront"})
     * @Route("/checkout/cart",name="frontend.checkout.cart.page", options={"seo"="false"}, methods={"GET"})
     */
    public function cartPageRedirect(Request $request, SalesChannelContext $context)
    {

        $lastOrderId = $this->session->get(OrderRepeaterSubscriber::ORDER_ID_SESSION_KEY);

        if (!$lastOrderId) {
            return $this->callInnerController($request, $context);
        }

        $this->session->remove(OrderRepeaterSubscriber::ORDER_ID_SESSION_KEY);

        $criteria = new Criteria([$lastOrderId]);
        $searchResult = $this->orderRepository->search($criteria, Context::createDefaultContext());

        if ($searchResult->count() === 0) {
            $this->logger->warning('Failed to find orderID by session, deleting the sessionId', ['lastOrderId' => $lastOrderId]);

            return $this->callInnerController($request, $context);
        }

        return $this->redirectToRoute('frontend.account.edit-order.page', ['orderId' => $lastOrderId, 'error-code' => 'CHECKOUT__CUSTOMER_CANCELED_EXTERNAL_PAYMENT']);
    }

    private function callInnerController(Request $request, SalesChannelContext $context)
    {
        return $this->checkoutController->cartPage($request, $context);
    }
}
