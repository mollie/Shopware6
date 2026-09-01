<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

use Mollie\Shopware\Component\Mollie\Exception\MissingCountryException;
use Mollie\Shopware\Component\Mollie\Exception\MissingSalutationException;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;

final class Address implements \JsonSerializable
{
    public const CUSTOM_FIELDS_KEY = 'mollie_payments_express_address_id';

    /**
     * Maximum length Mollie accepts for the address "title" field. Longer values are
     * rejected with an "Unprocessable Entity" error and abort the whole payment creation.
     */
    private const MAX_TITLE_LENGTH = 20;

    /**
     * Characters Mollie accepts in an address: the letters and digits of the Unicode blocks
     * Basic Latin, Latin-1 Supplement, Latin Extended-A and Phonetic Extensions, without the
     * two mathematical signs that sit between the Latin-1 letters.
     */
    private const ACCEPTED_CHARACTERS = 'A-Za-z0-9\x{00AA}\x{00BA}\x{00C0}-\x{00D6}\x{00D8}-\x{00F6}\x{00F8}-\x{017F}\x{1D00}-\x{1D7F}';

    /**
     * A name may carry only the accepted characters, a space and the three punctuation marks
     * that appear in real names. An underscore or an emoji from a wallet is rejected by Mollie
     * with "Unprocessable Entity" and aborts the payment.
     */
    private const UNSUPPORTED_NAME_CHARACTERS = '~[^' . self::ACCEPTED_CHARACTERS . ' .\'-]~u';

    /**
     * Street, city and company name accept a far wider set of punctuation than a name does,
     * so they are cleaned with their own pattern instead of losing a legitimate "&", "/" or
     * the "°" and "·" that belong to a Spanish or Catalan address.
     */
    private const UNSUPPORTED_ADDRESS_CHARACTERS = '~[^' . self::ACCEPTED_CHARACTERS . ' .,:;#&/()+@_"\x{00B0}\x{00B7}\'-]~u';

    private const COMBINING_MARKS = '~\p{Mn}~u';

    private const LETTER = '~\p{L}~u';

    private const WHITESPACES = '~\s+~u';

    /**
     * Characters that have an accepted counterpart and are therefore mapped rather than folded:
     * the typographic apostrophes and dashes an Apple Pay or Google Pay wallet delivers.
     */
    private const CHARACTER_REPLACEMENTS = [
        '’' => "'",
        '‘' => "'",
        '´' => "'",
        '`' => "'",
        '–' => '-',
        '—' => '-',
        '‑' => '-',
        '−' => '-',
    ];

    private string $title;
    private string $givenName;
    private string $familyName;
    private string $organizationName = '';
    private string $streetAndNumber;
    private string $streetAdditional = '';
    private string $postalCode;
    private string $email;
    private string $phone = '';
    private string $city;
    private string $country;

    public function __construct(string $email, string $title, string $givenName, string $familyName, string $streetAndNumber, string $postalCode, string $city, string $country)
    {
        $this->email = $email;
        $this->title = $title;
        $this->givenName = $this->sanitize($givenName, self::UNSUPPORTED_NAME_CHARACTERS);
        $this->familyName = $this->sanitize($familyName, self::UNSUPPORTED_NAME_CHARACTERS);
        $this->streetAndNumber = $this->sanitize($streetAndNumber, self::UNSUPPORTED_ADDRESS_CHARACTERS);
        $this->postalCode = $postalCode;
        $this->city = $this->sanitize($city, self::UNSUPPORTED_ADDRESS_CHARACTERS);
        $this->country = $country;
    }

    public static function fromAddress(CustomerEntity $customer, OrderAddressEntity $orderAddress): self
    {
        $salutation = $customer->getSalutation();
        if ($salutation === null) {
            throw new MissingSalutationException();
        }
        $country = $orderAddress->getCountry();
        if ($country === null) {
            throw new MissingCountryException();
        }
        $address = new self($customer->getEmail(),
            (string) $salutation->getDisplayName(),
            $orderAddress->getFirstName(),
            $orderAddress->getLastName(),
            $orderAddress->getStreet(),
            (string) $orderAddress->getZipcode(),
            $orderAddress->getCity(),
            (string) $country->getIso()
        );

        if ($orderAddress->getPhoneNumber() !== null) {
            $address->setPhone($orderAddress->getPhoneNumber());
        }
        $streetAdditional = $address->joinAdditionalLines($orderAddress->getAdditionalAddressLine1(), $orderAddress->getAdditionalAddressLine2());
        if ($streetAdditional !== '') {
            $address->setStreetAdditional($streetAdditional);
        }

        if ($orderAddress->getCompany() !== null) {
            $address->setOrganizationName($orderAddress->getCompany());
        }

        return $address;
    }

    /**
     * The addresses attached to a SalesChannelContext customer are loaded without their
     * customer association, so the email has to be passed in by the caller in that case.
     */
    public static function fromCustomerAddress(CustomerAddressEntity $customerAddress, string $fallbackEmail = ''): self
    {
        $customer = $customerAddress->getCustomer();
        $country = $customerAddress->getCountry();

        $address = new self(
            $customer !== null ? $customer->getEmail() : $fallbackEmail,
            '',
            (string) $customerAddress->getFirstName(),
            (string) $customerAddress->getLastName(),
            (string) $customerAddress->getStreet(),
            (string) $customerAddress->getZipcode(),
            (string) $customerAddress->getCity(),
            $country !== null ? (string) $country->getIso() : '',
        );

        $streetAdditional = $address->joinAdditionalLines($customerAddress->getAdditionalAddressLine1(), $customerAddress->getAdditionalAddressLine2());
        if ($streetAdditional !== '') {
            $address->setStreetAdditional($streetAdditional);
        }

        $phone = $customerAddress->getPhoneNumber();
        if ($phone !== null && $phone !== '') {
            $address->setPhone($phone);
        }

        $company = $customerAddress->getCompany();
        if ($company !== null && $company !== '') {
            $address->setOrganizationName($company);
        }

        return $address;
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function fromResponseBody(array $body): self
    {
        $address = new self($body['email'] ?? '',
            '',
            $body['givenName'] ?? '',
            $body['familyName'] ?? '',
            $body['streetAndNumber'] ?? '',
            $body['postalCode'] ?? '',
            $body['city'] ?? '',
            $body['country'] ?? ''
        );
        $phone = $body['phone'] ?? null;
        $streetAdditional = $body['streetAdditional'] ?? null;
        if ($streetAdditional !== null) {
            $address->setStreetAdditional($streetAdditional);
        }
        if ($phone !== null) {
            $address->setPhone($phone);
        }

        return $address;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = [
            'title' => $this->limitTitle(trim($this->title)),
            'givenName' => $this->givenName,
            'familyName' => $this->familyName,
            'streetAndNumber' => $this->streetAndNumber,
            'postalCode' => trim($this->postalCode),
            'email' => trim($this->email),
            'city' => $this->city,
            'country' => trim($this->country),
        ];

        if ($this->streetAdditional !== '') {
            $data['streetAdditional'] = $this->streetAdditional;
        }
        if ($this->organizationName !== '') {
            $data['organizationName'] = $this->organizationName;
        }
        $phone = trim($this->phone);
        if ($phone !== '') {
            $data['phone'] = $phone;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function toRegisterFormArray(): array
    {
        return [
            'firstName' => $this->givenName,
            'lastName' => $this->familyName,
            'email' => $this->email,
            'street' => $this->streetAndNumber,
            'zipcode' => $this->postalCode,
            'city' => $this->city,
        ];
    }

    public function setOrganizationName(string $organizationName): void
    {
        $this->organizationName = $this->sanitize($organizationName, self::UNSUPPORTED_ADDRESS_CHARACTERS);
    }

    public function setStreetAdditional(string $streetAdditional): void
    {
        $this->streetAdditional = $this->sanitize($streetAdditional, self::UNSUPPORTED_ADDRESS_CHARACTERS);
    }

    /**
     * Mollie rejects phone numbers that are not in E.164 format and fails the whole payment.
     * We therefore normalize on assignment: an already valid (or empty) number is kept as is,
     * otherwise we try to convert it to E.164 and drop it (empty string) when that is not possible.
     * The country has to be set before the phone for the national-format conversion to work.
     */
    public function setPhone(string $phone): void
    {
        if ($phone === '' || PhoneNumber::isValidE164($phone)) {
            $this->phone = $phone;

            return;
        }

        $this->phone = PhoneNumber::toE164($phone, $this->country);
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getGivenName(): string
    {
        return $this->givenName;
    }

    public function getFamilyName(): string
    {
        return $this->familyName;
    }

    public function getOrganizationName(): string
    {
        return $this->organizationName;
    }

    public function getStreetAndNumber(): string
    {
        return $this->streetAndNumber;
    }

    public function getStreetAdditional(): string
    {
        return $this->streetAdditional;
    }

    public function getPostalCode(): string
    {
        return $this->postalCode;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function getId(): string
    {
        $keys = [
            $this->givenName,
            $this->familyName,
            $this->email,
            $this->streetAndNumber,
            $this->streetAdditional,
            $this->postalCode,
            $this->city,
            $this->country
        ];
        if (mb_strlen($this->organizationName) > 0) {
            $keys[] = $this->organizationName;
        }
        if (mb_strlen($this->phone) > 0) {
            $keys[] = $this->phone;
        }

        return md5(implode('-', $keys));
    }

    private function joinAdditionalLines(?string $line1, ?string $line2): string
    {
        $lines = [];

        $line1 = trim((string) $line1);
        if ($line1 !== '') {
            $lines[] = $line1;
        }

        $line2 = trim((string) $line2);
        if ($line2 !== '') {
            $lines[] = $line2;
        }

        return implode(' ', $lines);
    }

    /**
     * Mollie rejects an address field that carries characters outside the Latin blocks - an
     * emoji from an Apple Pay wallet, an underscore in a last name - with an "Unprocessable
     * Entity" and aborts the payment after the order already exists in the shop. The value is
     * therefore cleaned on assignment, not on serialization: the express checkout reads the
     * address back out of the Mollie session and writes it into the shop, so every reader of
     * this struct has to see the same value.
     *
     * A field that survives nothing at all keeps its original value, because an empty name
     * would fail the Shopware address validation before Mollie is ever reached.
     */
    private function sanitize(string $value, string $unsupportedCharacters): string
    {
        $normalized = \Normalizer::normalize($value, \Normalizer::FORM_C);
        if ($normalized === false) {
            return trim($value);
        }

        $normalized = strtr((string) preg_replace(self::WHITESPACES, ' ', $normalized), self::CHARACTER_REPLACEMENTS);
        $accepted = preg_replace_callback(
            $unsupportedCharacters,
            fn (array $match): string => $this->toAcceptedCharacters($match[0], $unsupportedCharacters),
            $normalized
        );
        if ($accepted === null) {
            return trim($value);
        }

        $accepted = trim((string) preg_replace(self::WHITESPACES, ' ', $accepted));
        if ($accepted === '') {
            return trim($value);
        }

        return $accepted;
    }

    /**
     * Replacement for a single character Mollie does not accept. Dropping it outright would
     * turn "Nguyễn" into "Nguyn", so the character is first folded onto its base letter, which
     * yields "Nguyen" and keeps the name readable and comparable for Klarna.
     *
     * A letter that has no accepted base, a Cyrillic or Greek one, is kept as it is: it is
     * accepted by every method except Klarna today, and mangling it would corrupt the address
     * the merchant ships to. Everything that is not a letter - emoji, symbols, control
     * characters - has no place in an address and is dropped.
     */
    private function toAcceptedCharacters(string $character, string $unsupportedCharacters): string
    {
        $decomposed = \Normalizer::normalize($character, \Normalizer::FORM_KD);
        if ($decomposed === false) {
            $decomposed = $character;
        }

        $withoutMarks = (string) preg_replace(self::COMBINING_MARKS, '', $decomposed);
        $base = (string) preg_replace($unsupportedCharacters, '', $withoutMarks);
        if ($base !== '') {
            return $base;
        }

        if (preg_match(self::LETTER, $character) === 1) {
            return $character;
        }

        return '';
    }

    /**
     * The salutation display name is used as the Mollie "title". Some (especially
     * localized) salutations are longer than Mollie's 20 character limit and would
     * abort the payment, so the value is trimmed down to the accepted length.
     */
    private function limitTitle(string $title): string
    {
        if (mb_strlen($title) > self::MAX_TITLE_LENGTH) {
            return mb_substr($title, 0, self::MAX_TITLE_LENGTH);
        }

        return $title;
    }
}
