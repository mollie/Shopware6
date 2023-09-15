<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Repository\Order;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;

class OrderAddressRepository implements OrderAddressRepositoryInterface
{
    /**
     * @var EntityRepository
     */
    private $repoOrderAdresses;


    /**
     * @param EntityRepository $repoOrderAdresses
     */
    public function __construct($repoOrderAdresses)
    {
        $this->repoOrderAdresses = $repoOrderAdresses;
    }


    /**
     * @param string $id
     * @param string $firstname
     * @param string $lastname
     * @param string $company
     * @param string $department
     * @param string $vatId
     * @param string $street
     * @param string $zipcode
     * @param string $city
     * @param string $countryId
     * @param Context $context
     * @return void
     */
    public function updateAddress(string $id, string $firstname, string $lastname, string $company, string $department, string $vatId, string $street, string $zipcode, string $city, string $countryId, Context $context): void
    {
        $this->repoOrderAdresses->update([
            [
                'id' => $id,
                'firstName' => $firstname,
                'lastName' => $lastname,
                'company' => $company,
                'department' => $department,
                'vatId' => $vatId,
                'street' => $street,
                'zipcode' => $zipcode,
                'city' => $city,
                'countryId' => $countryId,
            ]
        ], $context);
    }

    /**
     * @param array<mixed> $data
     * @param Context $context
     * @return EntityWrittenContainerEvent
     */
    public function update(array $data, Context $context): EntityWrittenContainerEvent
    {
        return $this->repoOrderAdresses->update($data, $context);
    }
}
