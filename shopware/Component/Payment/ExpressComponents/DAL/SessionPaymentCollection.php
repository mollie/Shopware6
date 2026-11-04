<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents\DAL;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<SessionPaymentEntity>
 *
 * @method void add(SessionPaymentEntity $entity)
 * @method void set(string $key, SessionPaymentEntity $entity)
 * @method SessionPaymentEntity[] getIterator()
 * @method SessionPaymentEntity[] getElements()
 * @method null|SessionPaymentEntity get(string $key)
 * @method null|SessionPaymentEntity first()
 * @method null|SessionPaymentEntity last()
 */
final class SessionPaymentCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return SessionPaymentEntity::class;
    }
}
