<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie;

use Mollie\Shopware\Component\Mollie\Address;
use Mollie\Shopware\Component\Mollie\Exception\MissingCountryException;
use Mollie\Shopware\Component\Mollie\Exception\MissingSalutationException;
use Mollie\Shopware\Unit\Fake\CustomerEntityBuilder;
use Mollie\Shopware\Unit\Fake\OrderEntityBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Address::class)]
final class AddressTest extends TestCase
{
    private CustomerEntityBuilder $customerRepository;
    private OrderEntityBuilder $orderRepository;

    public function setUp(): void
    {
        $this->customerRepository = new CustomerEntityBuilder();
        $this->orderRepository = new OrderEntityBuilder();
    }

    public function testCanCreateFromEntity(): void
    {
        $customer = $this->customerRepository->getDefaultCustomer();
        $orderAddress = $this->orderRepository->getOrderAddress($customer);
        $orderAddress->setPhoneNumber('+1234567890');
        $orderAddress->setAdditionalAddressLine1('Appartment 2');
        $orderAddress->setAdditionalAddressLine2('Block C');
        $orderAddress->setCompany('Test Company');
        $actual = Address::fromAddress($customer, $orderAddress);
        $expected = [
            'title' => 'Not specified',
            'givenName' => 'Tester',
            'familyName' => 'Test',
            'organizationName' => 'Test Company',
            'streetAndNumber' => 'Test Street',
            'streetAdditional' => 'Appartment 2 Block C',
            'postalCode' => '12345',
            'email' => 'fake@unit.test',
            'phone' => '+1234567890',
            'city' => 'Test City',
            'country' => 'DE',
        ];
        $this->assertInstanceOf(Address::class, $actual);

        $this->assertSame($expected['givenName'], $actual->getGivenName());
        $this->assertSame($expected['familyName'], $actual->getFamilyName());
        $this->assertSame($expected['organizationName'], $actual->getOrganizationName());
        $this->assertSame($expected['streetAndNumber'], $actual->getStreetAndNumber());
        $this->assertSame($expected['streetAdditional'], $actual->getStreetAdditional());
        $this->assertSame($expected['phone'], $actual->getPhone());
        $this->assertSame($expected['postalCode'], $actual->getPostalCode());
        $this->assertSame($expected['email'], $actual->getEmail());
        $this->assertSame($expected['city'], $actual->getCity());
        $this->assertSame($expected['country'], $actual->getCountry());
        $this->assertSame($expected['title'], $actual->getTitle());
    }

    public function testWhitespaceOnlyAdditionalAddressLinesAreIgnored(): void
    {
        $customer = $this->customerRepository->getDefaultCustomer();
        $orderAddress = $this->orderRepository->getOrderAddress($customer);
        $orderAddress->setAdditionalAddressLine1('   ');
        $orderAddress->setAdditionalAddressLine2('   ');
        $orderAddress->setCompany('   ');

        $actual = Address::fromAddress($customer, $orderAddress);

        $this->assertSame('', $actual->getStreetAdditional());
        $this->assertSame('', $actual->getOrganizationName());
        $this->assertArrayNotHasKey('streetAdditional', $actual->jsonSerialize());
        $this->assertArrayNotHasKey('organizationName', $actual->jsonSerialize());
    }

    public function testAdditionalAddressLinesAreTrimmedBeforeTheyAreJoined(): void
    {
        $customer = $this->customerRepository->getDefaultCustomer();
        $orderAddress = $this->orderRepository->getOrderAddress($customer);
        $orderAddress->setAdditionalAddressLine1('  Appartment 2  ');
        $orderAddress->setAdditionalAddressLine2('   ');

        $actual = Address::fromAddress($customer, $orderAddress);

        $this->assertSame('Appartment 2', $actual->getStreetAdditional());
    }

    public function testJsonSerializeTrimsWhitespace(): void
    {
        $address = new Address(
            ' fake@unit.test ',
            ' Not specified ',
            ' Tester ',
            ' Test ',
            ' Test Street ',
            ' 12345 ',
            ' Test City ',
            ' DE '
        );
        $address->setStreetAdditional('  Appartment 2  ');
        $address->setOrganizationName('  Test Company  ');
        $address->setPhone('  +1234567890  ');

        $expected = [
            'title' => 'Not specified',
            'givenName' => 'Tester',
            'familyName' => 'Test',
            'streetAndNumber' => 'Test Street',
            'postalCode' => '12345',
            'email' => 'fake@unit.test',
            'city' => 'Test City',
            'country' => 'DE',
            'streetAdditional' => 'Appartment 2',
            'organizationName' => 'Test Company',
            'phone' => '+1234567890',
        ];

        $this->assertSame($expected, $address->jsonSerialize());
    }

    public function testJsonSerializeDropsWhitespaceOnlyOptionalFields(): void
    {
        $address = new Address('fake@unit.test', 'Not specified', 'Tester', 'Test', 'Test Street', '12345', 'Test City', 'DE');
        $address->setStreetAdditional('   ');
        $address->setOrganizationName('   ');
        $address->setPhone('   ');

        $actual = $address->jsonSerialize();

        $this->assertArrayNotHasKey('streetAdditional', $actual);
        $this->assertArrayNotHasKey('organizationName', $actual);
        $this->assertArrayNotHasKey('phone', $actual);
    }

    public function testJsonSerializeTruncatesTitleToMollieLimit(): void
    {
        // Mollie rejects a "title" longer than 20 characters with an Unprocessable Entity error.
        $address = new Address(
            'fake@unit.test',
            'This salutation is way too long',
            'Tester',
            'Test',
            'Test Street',
            '12345',
            'Test City',
            'DE'
        );

        $actual = $address->jsonSerialize();

        $this->assertSame('This salutation is w', $actual['title']);
        $this->assertLessThanOrEqual(20, mb_strlen($actual['title']));
    }

    public function testExpectExceptionOnEmptySalutation()
    {
        $customer = $this->customerRepository->getDefaultCustomerWithoutSalutation();
        $orderAddress = $this->orderRepository->getOrderAddress($customer);
        $this->expectException(MissingSalutationException::class);
        Address::fromAddress($customer, $orderAddress);
    }

    public function testExpectExceptionOnEmptyCountry()
    {
        $customer = $this->customerRepository->getDefaultCustomer();
        $orderAddress = $this->orderRepository->getOrderAddressWithoutCountry($customer);
        $this->expectException(MissingCountryException::class);
        Address::fromAddress($customer, $orderAddress);
    }

    #[DataProvider('nameProvider')]
    public function testNameIsCleanedForMollie(string $name, string $expected): void
    {
        $address = new Address('fake@unit.test', 'Not specified', $name, 'Test', 'Test Street', '12345', 'Test City', 'DE');

        $actual = $address->getGivenName();

        $this->assertSame($expected, $actual);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function nameProvider(): array
    {
        return [
            'underscore is dropped' => ['Mus_termann', 'Mustermann'],
            'emoji from a wallet is dropped' => ['Müller 😀', 'Müller'],
            'slash is dropped' => ['Doe/Smith', 'DoeSmith'],
            'multiplication sign is dropped' => ['Anna×Maria', 'AnnaMaria'],
            'division sign is dropped' => ['Anna÷Maria', 'AnnaMaria'],
            'hyphen, apostrophe and dot are kept' => ["St. Anne-Marie O'Hara", "St. Anne-Marie O'Hara"],
            'accepted umlaut is kept' => ['Müller', 'Müller'],
            'accepted nordic letter is kept' => ['Ørsted', 'Ørsted'],
            'accepted ordinal indicator is kept' => ['Mariª', 'Mariª'],
            'accepted phonetic letter is kept' => ["M\u{1D00}x", "M\u{1D00}x"],
            'digits are kept' => ['Müller 4711', 'Müller 4711'],
            'vietnamese letter folds onto its base letter' => ['Nguyễn', 'Nguyen'],
            'romanian comma below folds onto its base letter' => ['Ștefan', 'Stefan'],
            'decomposed accent is recomposed' => ["Jose\u{0301}", 'José'],
            'typographic apostrophe becomes a straight one' => ['O’Brien', "O'Brien"],
            'opening single quote becomes a straight apostrophe' => ['O‘Brien', "O'Brien"],
            'acute accent becomes a straight apostrophe' => ['O´Brien', "O'Brien"],
            'backtick becomes a straight apostrophe' => ['O`Brien', "O'Brien"],
            'en dash becomes a hyphen' => ['Jean–Luc', 'Jean-Luc'],
            'em dash becomes a hyphen' => ['Jean—Luc', 'Jean-Luc'],
            'minus sign becomes a hyphen' => ['Jean−Luc', 'Jean-Luc'],
            'non breaking hyphen becomes a hyphen' => ["Jean\u{2011}Luc", 'Jean-Luc'],
            'non breaking space becomes a space' => ["Anna\u{00A0}Maria", 'Anna Maria'],
            'line break becomes a space' => ["Max\nMustermann", 'Max Mustermann'],
            'dropping a character leaves no double space' => ['Anna _ Maria', 'Anna Maria'],
            'cyrillic letters are kept' => ['Мария', 'Мария'],
            'emoji next to cyrillic letters is dropped' => ['Иван 🙂', 'Иван'],
        ];
    }

    public function testFamilyNameIsCleanedLikeTheGivenName(): void
    {
        $address = new Address('fake@unit.test', 'Not specified', 'Tester', 'Mus_termann 🎉', 'Test Street', '12345', 'Test City', 'DE');

        $actual = $address->getFamilyName();

        $this->assertSame('Mustermann', $actual);
    }

    #[DataProvider('streetProvider')]
    public function testStreetIsCleanedWithTheWiderPunctuationSet(string $street, string $expected): void
    {
        $address = new Address('fake@unit.test', 'Not specified', 'Tester', 'Test', $street, '12345', 'Test City', 'DE');

        $actual = $address->getStreetAndNumber();

        $this->assertSame($expected, $actual);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function streetProvider(): array
    {
        return [
            'ampersand and slash are kept' => ['c/o Müller & Söhne', 'c/o Müller & Söhne'],
            'underscore is kept, unlike in a name' => ['Haupt_str. 5', 'Haupt_str. 5'],
            'degree sign is kept' => ['Rue Hugo n°3', 'Rue Hugo n°3'],
            'catalan middle dot is kept' => ['Paral·lel 12', 'Paral·lel 12'],
            'spanish ordinal indicator is kept' => ['Calle Mayor 1ª', 'Calle Mayor 1ª'],
            'emoji is dropped' => ['Hauptstr. 5 🚀', 'Hauptstr. 5'],
            'numero sign folds onto its base letters' => ['№3', 'No3'],
        ];
    }

    public function testCityIsCleaned(): void
    {
        $address = new Address('fake@unit.test', 'Not specified', 'Tester', 'Test', 'Test Street', '12345', 'Köln 🏠', 'DE');

        $actual = $address->getCity();

        $this->assertSame('Köln', $actual);
    }

    public function testCompanyIsCleaned(): void
    {
        $address = new Address('fake@unit.test', 'Not specified', 'Tester', 'Test', 'Test Street', '12345', 'Test City', 'DE');
        $address->setOrganizationName('Müller & Söhne GmbH 🚀');

        $actual = $address->getOrganizationName();

        $this->assertSame('Müller & Söhne GmbH', $actual);
    }

    public function testAdditionalAddressLineIsCleaned(): void
    {
        $address = new Address('fake@unit.test', 'Not specified', 'Tester', 'Test', 'Test Street', '12345', 'Test City', 'DE');
        $address->setStreetAdditional('c/o Meyer #2 😀');

        $actual = $address->getStreetAdditional();

        $this->assertSame('c/o Meyer #2', $actual);
    }

    public function testNameKeepsItsOriginalWhenNothingIsAccepted(): void
    {
        // An empty name fails the Shopware address validation before Mollie is ever reached,
        // so a value we cannot clean up is passed on unchanged.
        $address = new Address('fake@unit.test', 'Not specified', '🎉', 'Test', 'Test Street', '12345', 'Test City', 'DE');

        $actual = $address->getGivenName();

        $this->assertSame('🎉', $actual);
    }

    public function testCityKeepsItsOriginalWhenNothingIsAccepted(): void
    {
        $address = new Address('fake@unit.test', 'Not specified', 'Tester', 'Test', 'Test Street', '12345', '🎉', 'DE');

        $actual = $address->getCity();

        $this->assertSame('🎉', $actual);
    }

    public function testCleaningAnAlreadyCleanedAddressChangesNothing(): void
    {
        // The express checkout reads the address back out of the Mollie session response and
        // writes it into the shop, from where it is turned into an Address again. Cleaning has
        // to be a no-op the second time, otherwise the dedup hash in getId() changes and a
        // duplicate customer address is created.
        $address = new Address('fake@unit.test', '', 'Nguyễn_Anna 😀', 'O’Brien', 'Paral·lel 12 🏠', '12345', 'Köln 🏠', 'ES');
        $address->setOrganizationName('Müller & Söhne GmbH 🚀');
        $address->setStreetAdditional('c/o Meyer #2 😀');
        $address->setPhone('+34123456789');

        $cleanedAgain = new Address(
            $address->getEmail(),
            $address->getTitle(),
            $address->getGivenName(),
            $address->getFamilyName(),
            $address->getStreetAndNumber(),
            $address->getPostalCode(),
            $address->getCity(),
            $address->getCountry()
        );
        $cleanedAgain->setOrganizationName($address->getOrganizationName());
        $cleanedAgain->setStreetAdditional($address->getStreetAdditional());
        $cleanedAgain->setPhone($address->getPhone());

        $this->assertSame($address->jsonSerialize(), $cleanedAgain->jsonSerialize());
        $this->assertSame($address->getId(), $cleanedAgain->getId());
    }

    public function testAddressFromAnOrderEntityIsCleaned(): void
    {
        $customer = $this->customerRepository->getDefaultCustomer();
        $orderAddress = $this->orderRepository->getOrderAddress($customer);
        $orderAddress->setFirstName('Anna_Maria 😀');
        $orderAddress->setLastName('Nguyễn');
        $orderAddress->setCompany('Müller & Söhne GmbH 🚀');

        $actual = Address::fromAddress($customer, $orderAddress);

        $this->assertSame('AnnaMaria', $actual->getGivenName());
        $this->assertSame('Nguyen', $actual->getFamilyName());
        $this->assertSame('Müller & Söhne GmbH', $actual->getOrganizationName());
    }
}
