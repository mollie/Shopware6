<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Repository\Order;


use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;

class OrderAddressRepository
{

    /**
     * @var EntityRepositoryInterface
     */
    private $repoOrderAdresses;


    /**
     * @param EntityRepositoryInterface $repoOrderAdresses
     */
    public function __construct(EntityRepositoryInterface $repoOrderAdresses)
    {
        $this->repoOrderAdresses = $repoOrderAdresses;
    }


    /**
     * @param string $id
     * @param string $firstname
     * @param string $lastname
     * @param string $street
     * @param string $zipcode
     * @param string $city
     * @param string $countryId
     * @param Context $context
     */
    public function updateAddress(string $id, string $firstname, string $lastname, string $street, string $zipcode, string $city, string $countryId, Context $context): void
    {
        $this->repoOrderAdresses->update([
            [
                'id' => $id,
                'firstName' => $firstname,
                'lastName' => $lastname,
                'street' => $street,
                'zipcode' => $zipcode,
                'city' => $city,
                'countryId' => $countryId,
            ]
        ], $context);
    }

}
