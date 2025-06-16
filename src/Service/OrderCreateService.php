<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Order\Exception\PaymentMethodNotAvailableException;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * This class is use for Apple Pay direct to create an order.
 * There is a class from Shopware to create the order, but this class creates and endless loop in lower shopware versions if you inject the order service via DI. only lazy fetch from Container works.
 * however in newer version this class is not public anymore so fetching from container throws an exceptions.
 * because of this, we copy the functionality and use this class instead
 */
class OrderCreateService
{
    public const IS_DOWNLOAD = 'is-download';
    private DataValidator $dataValidator;
    private DataValidationFactoryInterface $orderValidationFactory;
    private EventDispatcherInterface $eventDispatcher;
    private CartService $cartService;
    /**
     * @var EntityRepository<PaymentMethodCollection<PaymentMethodEntity>>
     */
    private $paymentMethodRepository;

    /**
     * @param EntityRepository<PaymentMethodCollection<PaymentMethodEntity>> $paymentMethodRepository
     */
    public function __construct(
        DataValidator $dataValidator,
        DataValidationFactoryInterface $orderValidationFactory,
        EventDispatcherInterface $eventDispatcher,
        CartService $cartService,
                                       $paymentMethodRepository
    ) {
        $this->dataValidator = $dataValidator;
        $this->orderValidationFactory = $orderValidationFactory;
        $this->eventDispatcher = $eventDispatcher;
        $this->cartService = $cartService;
        $this->paymentMethodRepository = $paymentMethodRepository;
    }

    /**
     * @throws ConstraintViolationException
     */
    public function createOrder(DataBag $data, SalesChannelContext $context): string
    {
        $cart = $this->cartService->getCart($context->getToken(), $context);
        $hasVirtualGoods = $this->hasLineItemWithState($cart->getLineItems(), self::IS_DOWNLOAD);
        $this->validateOrderData($data, $context, $hasVirtualGoods);

        $this->validateCart($cart, $context->getContext());

        return $this->cartService->order($cart, $context, $data->toRequestDataBag());
    }

    private function hasLineItemWithState(LineItemCollection $lineItems, string $state): bool
    {
        $flatLineItems = $lineItems->getFlat();
        /** @var LineItem $lineItem */
        foreach ($flatLineItems as $lineItem) {
            /** @phpstan-ignore-next-line  */
            if (! method_exists($lineItem, 'getStates')) {
                return false;
            }
            if (\in_array($state, $lineItem->getStates(), true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws ConstraintViolationException
     */
    private function validateOrderData(
        ParameterBag $data,
        SalesChannelContext $context,
        bool $hasVirtualGoods
    ): void {
        $definition = $this->getOrderCreateValidationDefinition(new DataBag($data->all()), $context, $hasVirtualGoods);
        $violations = $this->dataValidator->getViolations($data->all(), $definition);

        if ($violations->count() > 0) {
            throw new ConstraintViolationException($violations, $data->all());
        }
    }

    private function validateCart(Cart $cart, Context $context): void
    {
        $idsOfPaymentMethods = [];

        foreach ($cart->getTransactions() as $paymentMethod) {
            $idsOfPaymentMethods[] = $paymentMethod->getPaymentMethodId();
        }

        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('active', true)
        );

        $paymentMethods = $this->paymentMethodRepository->searchIds($criteria, $context);

        if ($paymentMethods->getTotal() !== \count(array_unique($idsOfPaymentMethods))) {
            foreach ($cart->getTransactions() as $paymentMethod) {
                if (! \in_array($paymentMethod->getPaymentMethodId(), $paymentMethods->getIds(), true)) {
                    throw new PaymentMethodNotAvailableException($paymentMethod->getPaymentMethodId());
                }
            }
        }
    }

    private function getOrderCreateValidationDefinition(
        DataBag $data,
        SalesChannelContext $context,
        bool $hasVirtualGoods
    ): DataValidationDefinition {
        $validation = $this->orderValidationFactory->create($context);

        if ($hasVirtualGoods) {
            $validation->add('revocation', new NotBlank());
        }

        $validationEvent = new BuildValidationEvent($validation, $data, $context->getContext());
        $this->eventDispatcher->dispatch($validationEvent, $validationEvent->getName());

        return $validation;
    }
}
