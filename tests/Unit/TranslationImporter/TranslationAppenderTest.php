<?php
declare(strict_types=1);

namespace MolliePayments\Unit\TranslationImporter;

use Mollie\Shopware\Component\TranslationImporter\TranslationAppender;
use PHPUnit\Framework\TestCase;

final class TranslationAppenderTest extends TestCase
{
    public function testAppendOnRoot(): void
    {
        $expectedXML = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<config>
    <card>
        <title lang="de-DE">Test</title>
    </card>
</config>
XML;


        $exampleXML = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<config>
    <card>
    </card>
</config>
XML;
        $dom = new \DOMDocument();
        $dom->loadXML($exampleXML);


        $appender = new TranslationAppender();
        $result = $appender->append($dom, 'card.title', 'Test', 'de-DE');


        $expectedXML = preg_replace('/\s+/', '', $expectedXML);
        $actualXML = preg_replace('/\s+/', '', $dom->saveXML());
        $this->assertEquals($expectedXML, $actualXML);
    }

    public function testReplaceOldValue(): void
    {
        $expectedXML = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<config>
    <card>
        <title lang="de-DE">Test</title>
    </card>
</config>
XML;


        $exampleXML = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<config>
    <card>
         <title lang="de-DE">Old</title>
    </card>
</config>
XML;
        $dom = new \DOMDocument();
        $dom->loadXML($exampleXML);


        $appender = new TranslationAppender();
        $result = $appender->append($dom, 'card.title', 'Test', 'de-DE');


        $expectedXML = preg_replace('/\s+/', '', $expectedXML);
        $actualXML = preg_replace('/\s+/', '', $dom->saveXML());
        $this->assertEquals($expectedXML, $actualXML);
    }

    public function testAppendTitle(): void
    {
        $expectedXML = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<config>
    <card>
        <title >API</title>
        <title lang="de-DE">API</title>
        <title lang="nl-NL">API</title>
        <title lang="it-IT">API</title>
        <title lang="pt-PT">API</title>
    </card>
</config>
XML;


        $exampleXML = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<config>
    <card>
        <title >API</title>
        <title lang="de-DE">API</title>
        <title lang="nl-NL">API</title>
        <title lang="it-IT">API</title>
    </card>
</config>
XML;
        $dom = new \DOMDocument();
        $dom->loadXML($exampleXML);


        $appender = new TranslationAppender();
        $result = $appender->append($dom, 'card.api.title', 'API', 'pt-PT');


        $expectedXML = preg_replace('/\s+/', '', $expectedXML);
        $actualXML = preg_replace('/\s+/', '', $dom->saveXML());
        $this->assertEquals($expectedXML, $actualXML);
    }

    public function testReplaceMultipleOldValues(): void
    {
        $exampleXML = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/shopware/platform/master/src/Core/System/SystemConfig/Schema/config.xsd">
  <card>
        <title >API</title>
        <title lang="de-DE">API</title>
        <title lang="nl-NL">API</title>
        <title lang="it-IT">API</title>
        <title lang="pt-PT">API</title>

        <input-field type="password">
            <name>liveApiKey</name>
            <copyable>true</copyable>
            <label>Live Key</label>
            <label lang="de-DE">Live Key</label>
            <label lang="nl-NL">Live-sleutel</label>
            <label lang="it-IT">Chiave Live</label><label lang="pt-PT">Chave ativa</label>
        </input-field>
        <input-field type="password">
            <name>testApiKey</name>
            <copyable>true</copyable>
            <label>Test Key</label>
            <label lang="de-DE">Test Key</label>
            <label lang="nl-NL">Test-sleutel</label>
            <label lang="it-IT">Chiave Test</label><label lang="pt-PT">Chave de teste</label>
        </input-field>
        <input-field type="bool">
            <name>testMode</name>
            <label>Test Mode</label>
            <label lang="de-DE">Test Modus</label>
            <label lang="nl-NL">Test Modus</label>
            <label lang="it-IT">Modalità test</label><label lang="pt-PT">Modo de Teste</label>
            <defaultValue>true</defaultValue>
            <helpText>Enables the test mode with the Mollie Sandbox payment page. The Storefront will also show (Test Mode) next to the payment method names.</helpText>
            <helpText lang="de-DE">Aktiviert den Testmodus mit der Mollie Sandbox Zahlungsseite. In der Storefront wird (Test Modus) neben den Zahlungsarten angezeigt.</helpText>
            <helpText lang="nl-NL">Staat de test modus toe met de Mollie Sandbox betalingspagina. De Storefront zal ook "(Test Mode)" tonen naast de naam van de betaalmethode.</helpText>
            <helpText lang="it-IT">Abilita la modalità test con la pagina di pagamento di Mollie Sandbox. Lo Storefront mostrerà anche (Modalità test) accanto ai nomi dei metodi di pagamento.</helpText><helpText lang="pt-PT">Ativa o modo de teste com a página de pagamento do Mollie Sandbox. A loja também será mostrada (Modo de teste) ao lado dos nomes dos métodos de pagamento.</helpText>
        </input-field>
        <component name="mollie-pluginconfig-section-api">
            <name>molliePluginConfigSectionApi</name>
        </component>
    </card>
</config>
XML;
        $expectedXML = $exampleXML;

        $dom = new \DOMDocument();
        $dom->loadXML($exampleXML);


        $appender = new TranslationAppender();
        $result = $appender->append($dom, 'card.api.title', 'API', 'pt-PT');
        $result = $appender->append($dom, 'card.api.liveApiKey.label', 'Chave ativa', 'pt-PT');
        $result = $appender->append($dom, 'card.api.testApiKey.label', 'Chave de teste', 'pt-PT');
        $result = $appender->append($dom, 'card.api.testMode.label', 'Modo de Teste', 'pt-PT');
        $result = $appender->append($dom, 'card.api.testMode.helpText', 'Ativa o modo de teste com a página de pagamento do Mollie Sandbox. A loja também será mostrada (Modo de teste) ao lado dos nomes dos métodos de pagamento.', 'pt-PT');


        $expectedXML = preg_replace('/\s+/', '', $expectedXML);
        $actualXML = preg_replace('/\s+/', '', $dom->saveXML());
        $this->assertEquals($expectedXML, $actualXML);

    }

    public function testReplaceOldValuesInOptions(): void
    {
        $exampleXML = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/shopware/platform/master/src/Core/System/SystemConfig/Schema/config.xsd">
  <card>
        <title>Order State Automation</title>
        <title lang="de-DE">Automatisch Order Status setzen</title>
        <title lang="nl-NL">Automatisch bestelstatus instellen</title>
        <title lang="it-IT">Automazione dello Stato dell'Ordine</title>
        <title lang="pt-PT">Automatização do estado da encomenda</title>
            <input-field type="single-select">
            <name>orderStateWithPartialRefundTransaction</name>
            <label>Order state with a partial refund transaction</label>
            <label lang="de-DE">Bestellstatus bei einer teilweise erstatteten Transaktion</label>
            <label lang="nl-NL">Bestelstatus bij een gedeeltelijk terugbetaalde transactie</label>
            <label lang="it-IT">Stato dell'ordine per una transazione di rimborso parziale</label>
            <label lang="pt-PT">Estado da encomenda com uma transação de reembolso parcial</label>
            <options>
                <option>
                    <id>skip</id>
                    <name>Skip this status</name>
                    <name lang="de-DE">nichts machen</name>
                    <name lang="nl-NL">Sla deze status over</name>
                    <name lang="it-IT">Salta questo stato</name>
                    <name lang="pt-PT">Ignorar este estado</name>
                </option>
                <option>
                    <id>open</id>
                    <name>Open</name>
                    <name lang="de-DE">Offen</name>
                    <name lang="nl-NL">Open</name>
                    <name lang="it-IT">Aperto</name>
                    <name lang="pt-PT">Aberto</name>
                </option>
                <option>
                    <id>in_progress</id>
                    <name>In progress</name>
                    <name lang="de-DE">In Bearbeitung</name>
                    <name lang="nl-NL">In uitvoering</name>
                    <name lang="it-IT">In corso</name>
                    <name lang="pt-PT">Em curso</name>
                </option>
                <option>
                    <id>completed</id>
                    <name>Completed</name>
                    <name lang="de-DE">Komplett</name>
                    <name lang="nl-NL">Voltooid</name>
                    <name lang="it-IT">Completato</name>
                    <name lang="pt-PT">Concluído</name>
                </option>
                <option>
                    <id>cancelled</id>
                    <name>Cancelled</name>
                    <name lang="de-DE">Abgebrochen</name>
                    <name lang="nl-NL">Geannuleerd</name>
                    <name lang="it-IT">Annullato</name>
                    <name lang="pt-PT">Cancelado</name>
                </option>
            </options>
        </input-field>
</card>
</config>
XML;

$expectedXML = $exampleXML;

        $dom = new \DOMDocument();
        $dom->loadXML($exampleXML);


        $appender = new TranslationAppender();
        $result = $appender->append($dom, 'card.orderStateAutomation.orderStateWithPartialRefundTransaction.label', 'Estado da encomenda com uma transação de reembolso parcial', 'pt-PT');
        $result = $appender->append($dom, 'card.orderStateAutomation.orderStateWithPartialRefundTransaction.options.1.name', 'Ignorar este estado', 'pt-PT');
        $result = $appender->append($dom, 'card.orderStateAutomation.orderStateWithPartialRefundTransaction.options.2.name', 'Aberto', 'pt-PT');
        $result = $appender->append($dom, 'card.orderStateAutomation.orderStateWithPartialRefundTransaction.options.3.name', 'Em curso', 'pt-PT');
        $result = $appender->append($dom, 'card.orderStateAutomation.orderStateWithPartialRefundTransaction.options.4.name', 'Concluído', 'pt-PT');
        $result = $appender->append($dom, 'card.orderStateAutomation.orderStateWithPartialRefundTransaction.options.5.name', 'Cancelado', 'pt-PT');

        $expectedXML = preg_replace('/\s+/', '', $expectedXML);
        $actualXML = preg_replace('/\s+/', '', $dom->saveXML());
        $this->assertEquals($expectedXML, $actualXML);
    }

    public function testReplaceOldValueByNameOrTitle(): void
    {
        $expectedXML = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<config>
    <card>
        <title>api</title>
          <input-field type="password">
            <name>liveApiKey</name>
            <label lang="en-GB">Other Key</label><label lang="de-DE">New Key</label>
          </input-field>
    </card>
</config>
XML;


        $exampleXML = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<config>
    <card>
        <title>api</title>
          <input-field type="password">
            <name>liveApiKey</name>
            <label lang="en-GB">Other Key</label><label lang="de-DE">Old Key</label>
          </input-field>
    </card>
</config>
XML;
        $dom = new \DOMDocument();
        $dom->loadXML($exampleXML);


        $appender = new TranslationAppender();
        $result = $appender->append($dom, 'card.api.liveApiKey.label', 'New Key', 'de-DE');


        $expectedXML = preg_replace('/\s+/', '', $expectedXML);
        $actualXML = preg_replace('/\s+/', '', $dom->saveXML());
        $this->assertEquals($expectedXML, $actualXML);
    }

    public function testFindByNameAndAppendNewTitle(): void
    {
        $expectedXML = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<config>
    <card>
        <title>api</title>
        <title lang="en-EN">Test</title>
        <title lang="de-DE">Test</title>
    </card>
</config>
XML;


        $exampleXML = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<config>
    <card>
         <title>api</title>
         <title lang="en-EN">Test</title>
    </card>
</config>
XML;
        $dom = new \DOMDocument();
        $dom->loadXML($exampleXML);


        $appender = new TranslationAppender();
        $result = $appender->append($dom, 'card.api.title', 'Test', 'de-DE');


        $expectedXML = preg_replace('/\s+/', '', $expectedXML);
        $actualXML = preg_replace('/\s+/', '', $dom->saveXML());
        $this->assertEquals($expectedXML, $actualXML);
    }

    public function testFindByChildNameAndAppendNewLabel(): void
    {

        $expectedXML = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<config>
    <card>
        <title>API</title>
        <title lang="de-DE">API</title>
        <title lang="nl-NL">API</title>
        <title lang="it-IT">API</title>
        <title lang="pt-PT">API</title>

        <input-field type="password">
            <name>liveApiKey</name>
            <copyable>true</copyable>
            <label>Live Key</label>
            <label lang="nl-NL">Live-sleutel</label>
            <label lang="it-IT">Chiave Live</label>
            <label lang="pt-PT">Chave ativa</label>
            <label lang="de-DE">Live Key</label>
        </input-field>
        </card>
</config>
XML;


        $exampleXML = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<config>
    <card>
        <title>API</title>
        <title lang="de-DE">API</title>
        <title lang="nl-NL">API</title>
        <title lang="it-IT">API</title>
        <title lang="pt-PT">API</title>
        
        <input-field type="password">
            <name>liveApiKey</name>
            <copyable>true</copyable>
            <label>Live Key</label>
            <label lang="nl-NL">Live-sleutel</label>
            <label lang="it-IT">Chiave Live</label>
            <label lang="pt-PT">Chave ativa</label>
        </input-field>
        </card>
</config>
XML;
        $dom = new \DOMDocument();
        $dom->loadXML($exampleXML);


        $appender = new TranslationAppender();
        $result = $appender->append($dom, 'card.api.liveApiKey.label', 'Live Key', 'de-DE');


        $expectedXML = preg_replace('/\s+/', '', $expectedXML);
        $actualXML = preg_replace('/\s+/', '', $dom->saveXML());
        $this->assertEquals($expectedXML, $actualXML);
    }

    public function testAppendOptionsInArray(): void
    {
        $expectedXML = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<config>
  <card>
        <title>Apple Pay</title>
        
        
        <input-field type="multi-select">
            <name>ignore</name>
            <label>Apple Pay Display Restrictions</label>
            <helpText>Restrict Apple Pay Direct from being displayed on these pages.</helpText>
            <options>
                <option>
                    <id>plp</id>
                    <name>Category pages</name>
                </option>
                <option>
                    <id>pdp</id>
                    <name>Product pages</name>
                   
                </option>
                <option>
                    <id>offcanvas</id>
                    <name>Offcanvas cart</name>
                </option>
                <option>
                    <id>cart</id>
                    <name>Cart page</name>
                </option>
            </options>
        </input-field>
                <input-field>
        <name>test</name>
        <options>
        <option>
           <name>Category pages</name>
</option>
        <option>
           <name>Category pages</name>
</option>
        <option>
           <name>Category pages</name>
</option>
        <option>
           <name>Category pages</name>
</option>
</options>
</input-field>
        <input-field type="multi-select">
            <name>applePayDirectRestrictions</name>
            <label>Apple Pay Display Restrictions</label>
            <helpText>Restrict Apple Pay Direct from being displayed on these pages.</helpText>
            <options>
                <option>
                    <id>plp</id>
                    <name>Category pages</name>
                </option>
                <option>
                    <id>pdp</id>
                    <name>Product pages</name>
                     <name lang="de-DE">Produktseiten</name>
                </option>
                <option>
                    <id>offcanvas</id>
                    <name>Offcanvas cart</name>
                </option>
                <option>
                    <id>cart</id>
                    <name>Cart page</name>
                </option>
            </options>
        </input-field>
</card>
</config>
XML;


        $exampleXML = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<config>
  <card>
        <title>Apple Pay</title>

        <input-field type="multi-select">
            <name>ignore</name>
            <label>Apple Pay Display Restrictions</label>
            <helpText>Restrict Apple Pay Direct from being displayed on these pages.</helpText>
            <options>
                <option>
                    <id>plp</id>
                    <name>Category pages</name>
                </option>
                <option>
                    <id>pdp</id>
                    <name>Product pages</name>
                </option>
                <option>
                    <id>offcanvas</id>
                    <name>Offcanvas cart</name>
                </option>
                <option>
                    <id>cart</id>
                    <name>Cart page</name>
                </option>
            </options>
        </input-field>
        <input-field>
        <name>test</name>
        <options>
        <option>
           <name>Category pages</name>
</option>
        <option>
           <name>Category pages</name>
</option>
        <option>
           <name>Category pages</name>
</option>
        <option>
           <name>Category pages</name>
</option>
</options>
</input-field>
            <input-field type="multi-select">
            <name>applePayDirectRestrictions</name>
            <label>Apple Pay Display Restrictions</label>
            <helpText>Restrict Apple Pay Direct from being displayed on these pages.</helpText>
            <options>
                <option>
                    <id>plp</id>
                    <name>Category pages</name>
                </option>
                <option>
                    <id>pdp</id>
                    <name>Product pages</name>
                </option>
                <option>
                    <id>offcanvas</id>
                    <name>Offcanvas cart</name>
                </option>
                <option>
                    <id>cart</id>
                    <name>Cart page</name>
                </option>
            </options>
        </input-field>
</card>
</config>
XML;

        $dom = new \DOMDocument();
        $dom->loadXML($exampleXML);


        $appender = new TranslationAppender();
        $result = $appender->append($dom, 'card.applePay.applePayDirectRestrictions.options.2.name', 'Produktseiten', 'de-DE');

        $expectedXML = preg_replace('/\s+/', '', $expectedXML);
        $actualXML = preg_replace('/\s+/', '', $dom->saveXML());
        $this->assertEquals($expectedXML, $actualXML);

    }

    public function testAppendElementToTheLastElementOfTheSameType(): void
    {

        $expectedXML = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<config>
    <card>
        <title>API</title>
        <title lang="de-DE">API</title>
        <title lang="nl-NL">API</title>
        <title lang="it-IT">API</title>
        <title lang="pt-PT">API</title>

        <input-field type="password">
            <name>liveApiKey</name>
            <copyable>true</copyable>
            <label>Live Key</label>
            <label lang="nl-NL">Live-sleutel</label>
            <label lang="it-IT">Chiave Live</label>
            <label lang="pt-PT">Chave ativa</label>
            <label lang="de-DE">Live Key</label>
            <helpText>Help Text</helpText>
        </input-field>
        </card>
</config>
XML;


        $exampleXML = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<config>
    <card>
        <title>API</title>
        <title lang="de-DE">API</title>
        <title lang="nl-NL">API</title>
        <title lang="it-IT">API</title>
        <title lang="pt-PT">API</title>
        
        <input-field type="password">
            <name>liveApiKey</name>
            <copyable>true</copyable>
            <label>Live Key</label>
            <label lang="nl-NL">Live-sleutel</label>
            <label lang="it-IT">Chiave Live</label>
            <label lang="pt-PT">Chave ativa</label>
            <helpText>Help Text</helpText>
        </input-field>
        </card>
</config>
XML;
        $dom = new \DOMDocument();
        $dom->loadXML($exampleXML);


        $appender = new TranslationAppender();
        $result = $appender->append($dom, 'card.api.liveApiKey.label', 'Live Key', 'de-DE');

        $expectedXML = preg_replace('/\s+/', '', $expectedXML);
        $actualXML = preg_replace('/\s+/', '', $dom->saveXML());
        $this->assertEquals($expectedXML, $actualXML);
    }

    public function testAppendMultipleTexts(): void
    {
        $expectedXML = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<config>
    <card>
        <title>Payments</title>
        <title lang="de-DE">Zahlungen</title>
        <title lang="nl-NL">Betalingen</title>
        <title lang="it-IT">Pagamenti</title>
        <title lang="pt-PT">Pagamentos</title>
        <input-field type="bool">
            <name>shopwareFailedPayment</name>
            <label>Use Shopware standard behaviour for failed payments</label>
            <label lang="de-DE">Shopware Standardverhalten für fehlerhafte Zahlungen verwenden</label>
            <label lang="nl-NL">Gebruik de standaard Shopware instelling voor mislukte betalingen</label>
            <label lang="it-IT">Utilizza il comportamento standard di Shopware per i pagamenti non riusciti</label>
            <label lang="pt-PT">Utilizar o comportamento padrão do Shopware para pagamentos falhados</label>
            <defaultValue>true</defaultValue>
            <helpText>Use the default Shopware behaviour on failed payments. If disabled, Mollie will automatically provide a way to retry the payment by redirecting the user to the Mollie payments page.</helpText>
            <helpText lang="de-DE">Aktiviert das Standardverhalten von Shopware für fehlerhafte Zahlungen. Wenn nicht aktiv, kümmert sich das Mollie Plugin um einen erneuten Versuch der Zahlung und leitet den Käufer auf die externe Mollie Zahlungsauswahl.</helpText>
            <helpText lang="nl-NL">Gebruik de standaard Shopware instelling voor mislukte betalingen. Indien uitgeschakeld, zal Mollie automatisch een manier aanbieden om de betaling opnieuw te proberen door de user om te leiden naar de Mollie betalingspagina.</helpText>
            <helpText lang="it-IT">Usa il comportamento predefinito di Shopware per i pagamenti non riusciti. Se disabilitato, Mollie fornirà automaticamente un modo per riprovare il pagamento reindirizzando l'utente alla pagina dei pagamenti di Mollie.</helpText>
            <helpText lang="pt-PT">Utilizar o comportamento padrão do Shopware em pagamentos falhados. Se este estiver desativado, a Mollie fornecerá automaticamente uma maneira de tentar novamente o pagamento redirecionando o utilizador para a página de pagamentos da Mollie.</helpText>
        </input-field>
        <input-field type="bool">
            <name>createCustomersAtMollie</name>
            <label>Create customers at Mollie</label>
            <label lang="de-DE">Kunden bei Mollie anlegen</label>
            <label lang="nl-NL">Klanten creëren bij Mollie</label>
            <label lang="it-IT">Crea clienti presso Mollie</label>
            <defaultValue>false</defaultValue>
            <helpText>Automatically have customers being created inside your Mollie Dashboard to see all payments of a specific customer.</helpText>
            <helpText lang="de-DE">Erstellt automatisch Kunden im Mollie Dashboard. Dadurch hat man einen zusätzlichen Überblick über alle Zahlungen dieses Kunden innerhalb von Mollie.</helpText>
            <helpText lang="nl-NL">Automatisch klanten laten aanmaken in het Mollie Dashboard om alle betalingen van een specifieke klant te zien.</helpText>
            <helpText lang="it-IT">Crea automaticamente i clienti all'interno della tua Mollie Dashboard per vedere tutti i pagamenti di un cliente specifico.</helpText>
        </input-field>
        <input-field type="bool">
            <name>useMolliePaymentMethodLimits</name>
            <defaultValue>false</defaultValue>
            <label>Use Mollie's availability rules for payment methods</label>
            <label lang="de-DE">Mollies Verfügbarkeitsregeln für Zahlungsarten verwenden</label>
            <label lang="nl-NL">Gebruik de beschikbaarheidsregels van Mollie voor betaalmethoden</label>
            <label lang="it-IT">Usa le regole di disponibilità di Mollie per i metodi di pagamento</label>
            <helpText>Automatically hides payment methods in the checkout based on the availability rules for payment methods. Only active payment methods from your mollie dashboard will be shown. If the payment method has a cart limit, currency restriction or billing address restriction, it will be hidden during checkout.</helpText>
            <helpText lang="de-DE">Blendet automatisch Zahlungsart im Checkout basierend auf Verfügbarkeitsregeln von Mollie. Es werden nur die aktiven Zahlungsarten aus dem Mollie Dashboard angezeigt. Wenn die Zahlungsart eine Einschränkung auf den Warenkorbwert, Währung oder Rechnungsadresse hat, wird diese auch ausgeblendet.</helpText>
            <helpText lang="nl-NL">Automatische betalingsmethode wordt verborgen tijdens het afrekenen op basis van beschikbaarheidsregels van Mollie. Alleen actieve betalingsmethoden uit het Mollie-dashboard worden weergegeven. Als de betalingsmethode beperkingen heeft op de winkelwagenwaarde, valuta of factuuradres, wordt deze ook verborgen.</helpText>
            <helpText lang="it-IT">Nasconde automaticamente i metodi di pagamento nel checkout in base alle regole di disponibilità per i metodi di pagamento. Verranno mostrati solo i metodi di pagamento attivi dalla tua Mollie Dashboard. Se il metodo di pagamento ha un limite di carrello, una restrizione di valuta o una restrizione all'indirizzo di fatturazione, verrà nascosto durante il checkout.</helpText>
        </input-field>
        <input-field type="text">
            <name>formatOrderNumber</name>
            <label>Custom format for Mollie order numbers (Dashboard, PayPal, ...)</label>
            <label lang="de-DE">Format für Mollie Bestellnummern (Dashboard, PayPal, ...)</label>
            <label lang="nl-NL">Aangepast formaat voor Mollie bestelnummers (Dashboard, PayPal, ...)</label>
            <label lang="it-IT">Formato personalizzato per i numeri d'ordine di Mollie (Dashboard, PayPal, ...)</label>
            <helpText>If set, this format will be used before the order number in the Mollie dashboard. This will also be passed on to PayPal as invoice number.</helpText>
            <helpText lang="de-DE">Wenn gesetzt, wird dieses Format vor der Bestellnummer im Mollie-Dashboard verwendet. Dieser Wert wird auch an PayPal als Rechnungsnummber übergeben</helpText>
            <helpText lang="nl-NL">Indien ingesteld, wordt dit formaat vóór het bestelnummer in het Mollie-dashboard gebruikt. Dit wordt ook als factuurnummer doorgegeven aan PayPal.</helpText>
            <helpText lang="it-IT">Se impostato, questo formato verrà utilizzato prima del numero d'ordine nella Mollie Dashboard. Verrà inoltre passato a PayPal come numero di fattura.</helpText>
        </input-field>
        <component name="mollie-pluginconfig-section-payments-format">
            <name>molliePluginConfigSectionPaymentsFormat</name>
        </component>
        <component name="mollie-pluginconfig-section-payments">
            <name>molliePluginConfigSectionPayments</name>
        </component>
    </card>
</config>
XML;


        $exampleXML = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<config>
    <card>
        <title>Payments</title>
        <title lang="de-DE">Zahlungen</title>
        <title lang="nl-NL">Betalingen</title>
        <title lang="it-IT">Pagamenti</title>
        <input-field type="bool">
            <name>shopwareFailedPayment</name>
            <label>Use Shopware standard behaviour for failed payments</label>
            <label lang="de-DE">Shopware Standardverhalten für fehlerhafte Zahlungen verwenden</label>
            <label lang="nl-NL">Gebruik de standaard Shopware instelling voor mislukte betalingen</label>
            <label lang="it-IT">Utilizza il comportamento standard di Shopware per i pagamenti non riusciti</label>
            <defaultValue>true</defaultValue>
            <helpText>Use the default Shopware behaviour on failed payments. If disabled, Mollie will automatically provide a way to retry the payment by redirecting the user to the Mollie payments page.</helpText>
            <helpText lang="de-DE">Aktiviert das Standardverhalten von Shopware für fehlerhafte Zahlungen. Wenn nicht aktiv, kümmert sich das Mollie Plugin um einen erneuten Versuch der Zahlung und leitet den Käufer auf die externe Mollie Zahlungsauswahl.</helpText>
            <helpText lang="nl-NL">Gebruik de standaard Shopware instelling voor mislukte betalingen. Indien uitgeschakeld, zal Mollie automatisch een manier aanbieden om de betaling opnieuw te proberen door de user om te leiden naar de Mollie betalingspagina.</helpText>
            <helpText lang="it-IT">Usa il comportamento predefinito di Shopware per i pagamenti non riusciti. Se disabilitato, Mollie fornirà automaticamente un modo per riprovare il pagamento reindirizzando l'utente alla pagina dei pagamenti di Mollie.</helpText>
        </input-field>
        <input-field type="bool">
            <name>createCustomersAtMollie</name>
            <label>Create customers at Mollie</label>
            <label lang="de-DE">Kunden bei Mollie anlegen</label>
            <label lang="nl-NL">Klanten creëren bij Mollie</label>
            <label lang="it-IT">Crea clienti presso Mollie</label>
            <defaultValue>false</defaultValue>
            <helpText>Automatically have customers being created inside your Mollie Dashboard to see all payments of a specific customer.</helpText>
            <helpText lang="de-DE">Erstellt automatisch Kunden im Mollie Dashboard. Dadurch hat man einen zusätzlichen Überblick über alle Zahlungen dieses Kunden innerhalb von Mollie.</helpText>
            <helpText lang="nl-NL">Automatisch klanten laten aanmaken in het Mollie Dashboard om alle betalingen van een specifieke klant te zien.</helpText>
            <helpText lang="it-IT">Crea automaticamente i clienti all'interno della tua Mollie Dashboard per vedere tutti i pagamenti di un cliente specifico.</helpText>
        </input-field>
        <input-field type="bool">
            <name>useMolliePaymentMethodLimits</name>
            <defaultValue>false</defaultValue>
            <label>Use Mollie's availability rules for payment methods</label>
            <label lang="de-DE">Mollies Verfügbarkeitsregeln für Zahlungsarten verwenden</label>
            <label lang="nl-NL">Gebruik de beschikbaarheidsregels van Mollie voor betaalmethoden</label>
            <label lang="it-IT">Usa le regole di disponibilità di Mollie per i metodi di pagamento</label>
            <helpText>Automatically hides payment methods in the checkout based on the availability rules for payment methods. Only active payment methods from your mollie dashboard will be shown. If the payment method has a cart limit, currency restriction or billing address restriction, it will be hidden during checkout.</helpText>
            <helpText lang="de-DE">Blendet automatisch Zahlungsart im Checkout basierend auf Verfügbarkeitsregeln von Mollie. Es werden nur die aktiven Zahlungsarten aus dem Mollie Dashboard angezeigt. Wenn die Zahlungsart eine Einschränkung auf den Warenkorbwert, Währung oder Rechnungsadresse hat, wird diese auch ausgeblendet.</helpText>
            <helpText lang="nl-NL">Automatische betalingsmethode wordt verborgen tijdens het afrekenen op basis van beschikbaarheidsregels van Mollie. Alleen actieve betalingsmethoden uit het Mollie-dashboard worden weergegeven. Als de betalingsmethode beperkingen heeft op de winkelwagenwaarde, valuta of factuuradres, wordt deze ook verborgen.</helpText>
            <helpText lang="it-IT">Nasconde automaticamente i metodi di pagamento nel checkout in base alle regole di disponibilità per i metodi di pagamento. Verranno mostrati solo i metodi di pagamento attivi dalla tua Mollie Dashboard. Se il metodo di pagamento ha un limite di carrello, una restrizione di valuta o una restrizione all'indirizzo di fatturazione, verrà nascosto durante il checkout.</helpText>
        </input-field>
        <input-field type="text">
            <name>formatOrderNumber</name>
            <label>Custom format for Mollie order numbers (Dashboard, PayPal, ...)</label>
            <label lang="de-DE">Format für Mollie Bestellnummern (Dashboard, PayPal, ...)</label>
            <label lang="nl-NL">Aangepast formaat voor Mollie bestelnummers (Dashboard, PayPal, ...)</label>
            <label lang="it-IT">Formato personalizzato per i numeri d'ordine di Mollie (Dashboard, PayPal, ...)</label>
            <helpText>If set, this format will be used before the order number in the Mollie dashboard. This will also be passed on to PayPal as invoice number.</helpText>
            <helpText lang="de-DE">Wenn gesetzt, wird dieses Format vor der Bestellnummer im Mollie-Dashboard verwendet. Dieser Wert wird auch an PayPal als Rechnungsnummber übergeben</helpText>
            <helpText lang="nl-NL">Indien ingesteld, wordt dit formaat vóór het bestelnummer in het Mollie-dashboard gebruikt. Dit wordt ook als factuurnummer doorgegeven aan PayPal.</helpText>
            <helpText lang="it-IT">Se impostato, questo formato verrà utilizzato prima del numero d'ordine nella Mollie Dashboard. Verrà inoltre passato a PayPal come numero di fattura.</helpText>
        </input-field>
        <component name="mollie-pluginconfig-section-payments-format">
            <name>molliePluginConfigSectionPaymentsFormat</name>
        </component>
        <component name="mollie-pluginconfig-section-payments">
            <name>molliePluginConfigSectionPayments</name>
        </component>
    </card>
</config>
XML;
        $dom = new \DOMDocument();
        $dom->loadXML($exampleXML);


        $appender = new TranslationAppender();
        $result = $appender->append($dom, 'card.payments.title', 'Pagamentos', 'pt-PT');
        $result = $appender->append($dom, 'card.payments.shopwareFailedPayment.label', 'Utilizar o comportamento padrão do Shopware para pagamentos falhados', 'pt-PT');
        $result = $appender->append($dom, 'card.payments.shopwareFailedPayment.helpText', 'Utilizar o comportamento padrão do Shopware em pagamentos falhados. Se este estiver desativado, a Mollie fornecerá automaticamente uma maneira de tentar novamente o pagamento redirecionando o utilizador para a página de pagamentos da Mollie.', 'pt-PT');


        $expectedXML = preg_replace('/\s+/', '', $expectedXML);
        $actualXML = preg_replace('/\s+/', '', $dom->saveXML());
        $this->assertEquals($expectedXML, $actualXML);
    }

    public function testFindOptionInComplexXML(): void
    {
        $expectedXML = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<config>
        <card>
        <title>Order State Automation</title>
        <title lang="de-DE">Automatisch Order Status setzen</title>
        <title lang="nl-NL">Automatisch bestelstatus instellen</title>
        <title lang="it-IT">Automazione dello Stato dell'Ordine</title><title lang="pt-PT">Automatização do estado da encomenda</title>

        <input-field type="single-select">
            <name>orderStateWithAPaidTransaction</name>
            <label>Order state with a paid transaction</label>
            <label lang="de-DE">Bestellstatus bei einer bezahlten Transaktion</label>
            <label lang="nl-NL">Bestelstatus bij een betaalde transactie</label>
            <label lang="it-IT">Stato dell'ordine per una transazione pagata</label><label lang="pt-PT">Estado da encomenda com uma transação paga</label>
            <options>
                <option>
                    <id>skip</id>
                    <name>Skip this status</name>
                    <name lang="de-DE">nichts machen</name>
                    <name lang="nl-NL">Sla deze status over</name>
                    <name lang="it-IT">Salta questo stato</name>
                    <name lang="pt-PT">Ignorar este estado</name>
                </option>
                <option>
                    <id>open</id>
                    <name>Open</name>
                    <name lang="de-DE">Offen</name>
                    <name lang="nl-NL">Open</name>
                    <name lang="it-IT">Aperto</name>
                </option>
                <option>
                    <id>in_progress</id>
                    <name>In progress</name>
                    <name lang="de-DE">In Bearbeitung</name>
                    <name lang="nl-NL">In uitvoering</name>
                    <name lang="it-IT">In corso</name>
                </option>
                <option>
                    <id>completed</id>
                    <name>Completed</name>
                    <name lang="de-DE">Komplett</name>
                    <name lang="nl-NL">Voltooid</name>
                    <name lang="it-IT">Completato</name>
                </option>
                <option>
                    <id>cancelled</id>
                    <name>Cancelled</name>
                    <name lang="de-DE">Abgebrochen</name>
                    <name lang="nl-NL">Geannuleerd</name>
                    <name lang="it-IT">Annullato</name>
                </option>
            </options>
        </input-field>
        <input-field type="single-select">
            <name>orderStateWithAFailedTransaction</name>
            <label>Order state with a failed transaction</label>
            <label lang="de-DE">Bestellstatus bei einer fehlgeschlagenen Transaktion</label>
            <label lang="nl-NL">Bestelstatus bij een mislukte transactie</label>
            <label lang="it-IT">Stato dell'ordine per una transazione non riuscita</label><label lang="pt-PT">Estado da encomenda com uma transação falhada</label>
            <options>
                <option>
                    <id>skip</id>
                    <name>Skip this status</name>
                    <name lang="de-DE">nichts machen</name>
                    <name lang="nl-NL">Sla deze status over</name>
                    <name lang="it-IT">Salta questo stato</name>
                </option>
                <option>
                    <id>open</id>
                    <name>Open</name>
                    <name lang="de-DE">Offen</name>
                    <name lang="nl-NL">Open</name>
                    <name lang="it-IT">Aperto</name>
                </option>
                <option>
                    <id>in_progress</id>
                    <name>In progress</name>
                    <name lang="de-DE">In Bearbeitung</name>
                    <name lang="nl-NL">In uitvoering</name>
                    <name lang="it-IT">In corso</name>
                </option>
                <option>
                    <id>completed</id>
                    <name>Completed</name>
                    <name lang="de-DE">Komplett</name>
                    <name lang="nl-NL">Voltooid</name>
                    <name lang="it-IT">Completato</name>
                </option>
                <option>
                    <id>cancelled</id>
                    <name>Cancelled</name>
                    <name lang="de-DE">Abgebrochen</name>
                    <name lang="nl-NL">Geannuleerd</name>
                    <name lang="it-IT">Annullato</name>
                </option>
            </options>
        </input-field>
        <input-field type="single-select">
            <name>orderStateWithACancelledTransaction</name>
            <label>Order state with a cancelled transaction</label>
            <label lang="de-DE">Bestellstatus bei einer abgebrochenen Transaktion</label>
            <label lang="nl-NL">Bestelstatus bij een geannuleerde transactie</label>
            <label lang="it-IT">Stato dell'ordine per una transazione annullata</label><label lang="pt-PT">Estado da encomenda com uma transação cancelada</label>
            <options>
                <option>
                    <id>skip</id>
                    <name>Skip this status</name>
                    <name lang="de-DE">nichts machen</name>
                    <name lang="nl-NL">Sla deze status over</name>
                    <name lang="it-IT">Salta questo stato</name>
                </option>
                <option>
                    <id>open</id>
                    <name>Open</name>
                    <name lang="de-DE">Offen</name>
                    <name lang="nl-NL">Open</name>
                    <name lang="it-IT">Aperto</name>
                </option>
                <option>
                    <id>in_progress</id>
                    <name>In progress</name>
                    <name lang="de-DE">In Bearbeitung</name>
                    <name lang="nl-NL">In uitvoering</name>
                    <name lang="it-IT">In corso</name>
                </option>
                <option>
                    <id>completed</id>
                    <name>Completed</name>
                    <name lang="de-DE">Komplett</name>
                    <name lang="nl-NL">Voltooid</name>
                    <name lang="it-IT">Completato</name>
                </option>
                <option>
                    <id>cancelled</id>
                    <name>Cancelled</name>
                    <name lang="de-DE">Abgebrochen</name>
                    <name lang="nl-NL">Geannuleerd</name>
                    <name lang="it-IT">Annullato</name>
                </option>
            </options>
        </input-field>
        <input-field type="single-select">
            <name>orderStateWithAAuthorizedTransaction</name>
            <label>Order state with a authorized transaction</label>
            <label lang="de-DE">Bestellstatus bei einer authorisierten Transaktion</label>
            <label lang="nl-NL">Bestelstatus bij een geautoriseerde transactie</label>
            <label lang="it-IT">Stato dell'ordine per una transazione autorizzata</label><label lang="pt-PT">Estado da encomenda com uma transação autorizada</label>
            <options>
                <option>
                    <id>skip</id>
                    <name>Skip this status</name>
                    <name lang="de-DE">nichts machen</name>
                    <name lang="nl-NL">Sla deze status over</name>
                    <name lang="it-IT">Salta questo stato</name>
                </option>
                <option>
                    <id>open</id>
                    <name>Open</name>
                    <name lang="de-DE">Offen</name>
                    <name lang="nl-NL">Open</name>
                    <name lang="it-IT">Aperto</name>
                </option>
                <option>
                    <id>in_progress</id>
                    <name>In progress</name>
                    <name lang="de-DE">In Bearbeitung</name>
                    <name lang="nl-NL">In uitvoering</name>
                    <name lang="it-IT">In corso</name>
                </option>
                <option>
                    <id>completed</id>
                    <name>Completed</name>
                    <name lang="de-DE">Komplett</name>
                    <name lang="nl-NL">Voltooid</name>
                    <name lang="it-IT">Completato</name>
                </option>
                <option>
                    <id>cancelled</id>
                    <name>Cancelled</name>
                    <name lang="de-DE">Abgebrochen</name>
                    <name lang="nl-NL">Geannuleerd</name>
                    <name lang="it-IT">Annullato</name>
                </option>
            </options>
        </input-field>
        <input-field type="single-select">
            <name>orderStateWithAChargebackTransaction</name>
            <label>Order state with a charged back transaction</label>
            <label lang="de-DE">Bestellstatus bei einer zurückgebuchten Transaktion</label>
            <label lang="nl-NL">Bestelstatus bij een teruggevorderde transactie</label>
            <label lang="it-IT">Stato dell'ordine per una transazione rimborsata</label><label lang="pt-PT">Estado da encomenda com uma transação estornada</label>
            <options>
                <option>
                    <id>skip</id>
                    <name>Skip this status</name>
                    <name lang="de-DE">nichts machen</name>
                    <name lang="nl-NL">Sla deze status over</name>
                    <name lang="it-IT">Salta questo stato</name>
                </option>
                <option>
                    <id>open</id>
                    <name>Open</name>
                    <name lang="de-DE">Offen</name>
                    <name lang="nl-NL">Open</name>
                    <name lang="it-IT">Aperto</name>
                </option>
                <option>
                    <id>in_progress</id>
                    <name>In progress</name>
                    <name lang="de-DE">In Bearbeitung</name>
                    <name lang="nl-NL">In uitvoering</name>
                    <name lang="it-IT">In corso</name>
                </option>
                <option>
                    <id>completed</id>
                    <name>Completed</name>
                    <name lang="de-DE">Komplett</name>
                    <name lang="nl-NL">Voltooid</name>
                    <name lang="it-IT">Completato</name>
                </option>
                <option>
                    <id>cancelled</id>
                    <name>Cancelled</name>
                    <name lang="de-DE">Abgebrochen</name>
                    <name lang="nl-NL">Geannuleerd</name>
                    <name lang="it-IT">Annullato</name>
                </option>
            </options>
        </input-field>
        <input-field type="single-select">
            <name>orderStateWithRefundTransaction</name>
            <label>Order state with a refund transaction</label>
            <label lang="de-DE">Bestellstatus bei einer zurückerstatteten Transaktion</label>
            <label lang="nl-NL">Bestelstatus bij een terugbetaalde transactie</label>
            <label lang="it-IT">Stato dell'ordine per una transazione di rimborso</label><label lang="pt-PT">Estado da encomenda com uma transação de reembolso</label>
            <options>
                <option>
                    <id>skip</id>
                    <name>Skip this status</name>
                    <name lang="de-DE">nichts machen</name>
                    <name lang="nl-NL">Sla deze status over</name>
                    <name lang="it-IT">Salta questo stato</name>
                </option>
                <option>
                    <id>open</id>
                    <name>Open</name>
                    <name lang="de-DE">Offen</name>
                    <name lang="nl-NL">Open</name>
                    <name lang="it-IT">Aperto</name>
                </option>
                <option>
                    <id>in_progress</id>
                    <name>In progress</name>
                    <name lang="de-DE">In Bearbeitung</name>
                    <name lang="nl-NL">In uitvoering</name>
                    <name lang="it-IT">In corso</name>
                </option>
                <option>
                    <id>completed</id>
                    <name>Completed</name>
                    <name lang="de-DE">Komplett</name>
                    <name lang="nl-NL">Voltooid</name>
                    <name lang="it-IT">Completato</name>
                </option>
                <option>
                    <id>cancelled</id>
                    <name>Cancelled</name>
                    <name lang="de-DE">Abgebrochen</name>
                    <name lang="nl-NL">Geannuleerd</name>
                    <name lang="it-IT">Annullato</name>
                </option>
            </options>
        </input-field>
        <input-field type="single-select">
            <name>orderStateWithPartialRefundTransaction</name>
            <label>Order state with a partial refund transaction</label>
            <label lang="de-DE">Bestellstatus bei einer teilweise erstatteten Transaktion</label>
            <label lang="nl-NL">Bestelstatus bij een gedeeltelijk terugbetaalde transactie</label>
            <label lang="it-IT">Stato dell'ordine per una transazione di rimborso parziale</label><label lang="pt-PT">Estado da encomenda com uma transação de reembolso parcial</label>
            <options>
                <option>
                    <id>skip</id>
                    <name>Skip this status</name>
                    <name lang="de-DE">nichts machen</name>
                    <name lang="nl-NL">Sla deze status over</name>
                    <name lang="it-IT">Salta questo stato</name><name lang="pt-PT">Ignorar este estado</name>
                </option>
                <option>
                    <id>open</id>
                    <name>Open</name>
                    <name lang="de-DE">Offen</name>
                    <name lang="nl-NL">Open</name>
                    <name lang="it-IT">Aperto</name><name lang="pt-PT">Aberto</name>
                </option>
                <option>
                    <id>in_progress</id>
                    <name>In progress</name>
                    <name lang="de-DE">In Bearbeitung</name>
                    <name lang="nl-NL">In uitvoering</name>
                    <name lang="it-IT">In corso</name><name lang="pt-PT">Em curso</name>
                </option>
                <option>
                    <id>completed</id>
                    <name>Completed</name>
                    <name lang="de-DE">Komplett</name>
                    <name lang="nl-NL">Voltooid</name>
                    <name lang="it-IT">Completato</name><name lang="pt-PT">Concluído</name>
                </option>
                <option>
                    <id>cancelled</id>
                    <name>Cancelled</name>
                    <name lang="de-DE">Abgebrochen</name>
                    <name lang="nl-NL">Geannuleerd</name>
                    <name lang="it-IT">Annullato</name><name lang="pt-PT">Cancelado</name>
                </option>
            </options>
        </input-field>
        <component name="mollie-pluginconfig-element-orderstate-select">
            <name>orderStateFinalState</name>
            <entity>state_machine_state</entity>
            <label>Final order state</label>
            <label lang="de-DE">Finaler Bestellstatus</label>
            <label lang="nl-NL">Definitieve Bestelstatus</label>
            <label lang="it-IT">Stato finale dell'ordine</label>
            <helpText>If enabled, the plugin will not transition to a new order state, if the final state is already set in the order. Use this feature in combination with plugins and systems if their workflows do not match the one from Mollie.</helpText>
            <helpText lang="de-DE">Wenn aktiv, wechselt das Plugin nicht in einen neuen Bestellstatus, wenn der finale Status bereits in der Bestellung verwendet wird. Verwenden Sie diese Funktion in Kombination mit Plugins und -Systemen, wenn deren Workflows nicht mit denen von Mollie übereinstimmen.</helpText>
            <helpText lang="nl-NL">Indien ingeschakeld, gaat de plug-in niet over naar een nieuwe bestelstatus, als de definitieve status al in de bestelling is ingesteld. Gebruik deze functie in combinatie met plug-ins en -systemen als hun workflows niet overeenkomen met die van Mollie.</helpText>
            <helpText lang="it-IT">Se abilitato, il plugin non passerà a un nuovo stato dell'ordine se lo stato finale è già impostato nell'ordine. Usa questa funzione in combinazione con plugin e sistemi se i loro flussi di lavoro non corrispondono a quello di Mollie.</helpText>
        </component>

    </card>
</config>
XML;

        $exampleXML = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<config>
        <card>
        <title>Order State Automation</title>
        <title lang="de-DE">Automatisch Order Status setzen</title>
        <title lang="nl-NL">Automatisch bestelstatus instellen</title>
        <title lang="it-IT">Automazione dello Stato dell'Ordine</title><title lang="pt-PT">Automatização do estado da encomenda</title>

        <input-field type="single-select">
            <name>orderStateWithAPaidTransaction</name>
            <label>Order state with a paid transaction</label>
            <label lang="de-DE">Bestellstatus bei einer bezahlten Transaktion</label>
            <label lang="nl-NL">Bestelstatus bij een betaalde transactie</label>
            <label lang="it-IT">Stato dell'ordine per una transazione pagata</label><label lang="pt-PT">Estado da encomenda com uma transação paga</label>
            <options>
                <option>
                    <id>skip</id>
                    <name>Skip this status</name>
                    <name lang="de-DE">nichts machen</name>
                    <name lang="nl-NL">Sla deze status over</name>
                    <name lang="it-IT">Salta questo stato</name>
                </option>
                <option>
                    <id>open</id>
                    <name>Open</name>
                    <name lang="de-DE">Offen</name>
                    <name lang="nl-NL">Open</name>
                    <name lang="it-IT">Aperto</name>
                </option>
                <option>
                    <id>in_progress</id>
                    <name>In progress</name>
                    <name lang="de-DE">In Bearbeitung</name>
                    <name lang="nl-NL">In uitvoering</name>
                    <name lang="it-IT">In corso</name>
                </option>
                <option>
                    <id>completed</id>
                    <name>Completed</name>
                    <name lang="de-DE">Komplett</name>
                    <name lang="nl-NL">Voltooid</name>
                    <name lang="it-IT">Completato</name>
                </option>
                <option>
                    <id>cancelled</id>
                    <name>Cancelled</name>
                    <name lang="de-DE">Abgebrochen</name>
                    <name lang="nl-NL">Geannuleerd</name>
                    <name lang="it-IT">Annullato</name>
                </option>
            </options>
        </input-field>
        <input-field type="single-select">
            <name>orderStateWithAFailedTransaction</name>
            <label>Order state with a failed transaction</label>
            <label lang="de-DE">Bestellstatus bei einer fehlgeschlagenen Transaktion</label>
            <label lang="nl-NL">Bestelstatus bij een mislukte transactie</label>
            <label lang="it-IT">Stato dell'ordine per una transazione non riuscita</label><label lang="pt-PT">Estado da encomenda com uma transação falhada</label>
            <options>
                <option>
                    <id>skip</id>
                    <name>Skip this status</name>
                    <name lang="de-DE">nichts machen</name>
                    <name lang="nl-NL">Sla deze status over</name>
                    <name lang="it-IT">Salta questo stato</name>
                </option>
                <option>
                    <id>open</id>
                    <name>Open</name>
                    <name lang="de-DE">Offen</name>
                    <name lang="nl-NL">Open</name>
                    <name lang="it-IT">Aperto</name>
                </option>
                <option>
                    <id>in_progress</id>
                    <name>In progress</name>
                    <name lang="de-DE">In Bearbeitung</name>
                    <name lang="nl-NL">In uitvoering</name>
                    <name lang="it-IT">In corso</name>
                </option>
                <option>
                    <id>completed</id>
                    <name>Completed</name>
                    <name lang="de-DE">Komplett</name>
                    <name lang="nl-NL">Voltooid</name>
                    <name lang="it-IT">Completato</name>
                </option>
                <option>
                    <id>cancelled</id>
                    <name>Cancelled</name>
                    <name lang="de-DE">Abgebrochen</name>
                    <name lang="nl-NL">Geannuleerd</name>
                    <name lang="it-IT">Annullato</name>
                </option>
            </options>
        </input-field>
        <input-field type="single-select">
            <name>orderStateWithACancelledTransaction</name>
            <label>Order state with a cancelled transaction</label>
            <label lang="de-DE">Bestellstatus bei einer abgebrochenen Transaktion</label>
            <label lang="nl-NL">Bestelstatus bij een geannuleerde transactie</label>
            <label lang="it-IT">Stato dell'ordine per una transazione annullata</label><label lang="pt-PT">Estado da encomenda com uma transação cancelada</label>
            <options>
                <option>
                    <id>skip</id>
                    <name>Skip this status</name>
                    <name lang="de-DE">nichts machen</name>
                    <name lang="nl-NL">Sla deze status over</name>
                    <name lang="it-IT">Salta questo stato</name>
                </option>
                <option>
                    <id>open</id>
                    <name>Open</name>
                    <name lang="de-DE">Offen</name>
                    <name lang="nl-NL">Open</name>
                    <name lang="it-IT">Aperto</name>
                </option>
                <option>
                    <id>in_progress</id>
                    <name>In progress</name>
                    <name lang="de-DE">In Bearbeitung</name>
                    <name lang="nl-NL">In uitvoering</name>
                    <name lang="it-IT">In corso</name>
                </option>
                <option>
                    <id>completed</id>
                    <name>Completed</name>
                    <name lang="de-DE">Komplett</name>
                    <name lang="nl-NL">Voltooid</name>
                    <name lang="it-IT">Completato</name>
                </option>
                <option>
                    <id>cancelled</id>
                    <name>Cancelled</name>
                    <name lang="de-DE">Abgebrochen</name>
                    <name lang="nl-NL">Geannuleerd</name>
                    <name lang="it-IT">Annullato</name>
                </option>
            </options>
        </input-field>
        <input-field type="single-select">
            <name>orderStateWithAAuthorizedTransaction</name>
            <label>Order state with a authorized transaction</label>
            <label lang="de-DE">Bestellstatus bei einer authorisierten Transaktion</label>
            <label lang="nl-NL">Bestelstatus bij een geautoriseerde transactie</label>
            <label lang="it-IT">Stato dell'ordine per una transazione autorizzata</label><label lang="pt-PT">Estado da encomenda com uma transação autorizada</label>
            <options>
                <option>
                    <id>skip</id>
                    <name>Skip this status</name>
                    <name lang="de-DE">nichts machen</name>
                    <name lang="nl-NL">Sla deze status over</name>
                    <name lang="it-IT">Salta questo stato</name>
                </option>
                <option>
                    <id>open</id>
                    <name>Open</name>
                    <name lang="de-DE">Offen</name>
                    <name lang="nl-NL">Open</name>
                    <name lang="it-IT">Aperto</name>
                </option>
                <option>
                    <id>in_progress</id>
                    <name>In progress</name>
                    <name lang="de-DE">In Bearbeitung</name>
                    <name lang="nl-NL">In uitvoering</name>
                    <name lang="it-IT">In corso</name>
                </option>
                <option>
                    <id>completed</id>
                    <name>Completed</name>
                    <name lang="de-DE">Komplett</name>
                    <name lang="nl-NL">Voltooid</name>
                    <name lang="it-IT">Completato</name>
                </option>
                <option>
                    <id>cancelled</id>
                    <name>Cancelled</name>
                    <name lang="de-DE">Abgebrochen</name>
                    <name lang="nl-NL">Geannuleerd</name>
                    <name lang="it-IT">Annullato</name>
                </option>
            </options>
        </input-field>
        <input-field type="single-select">
            <name>orderStateWithAChargebackTransaction</name>
            <label>Order state with a charged back transaction</label>
            <label lang="de-DE">Bestellstatus bei einer zurückgebuchten Transaktion</label>
            <label lang="nl-NL">Bestelstatus bij een teruggevorderde transactie</label>
            <label lang="it-IT">Stato dell'ordine per una transazione rimborsata</label><label lang="pt-PT">Estado da encomenda com uma transação estornada</label>
            <options>
                <option>
                    <id>skip</id>
                    <name>Skip this status</name>
                    <name lang="de-DE">nichts machen</name>
                    <name lang="nl-NL">Sla deze status over</name>
                    <name lang="it-IT">Salta questo stato</name>
                </option>
                <option>
                    <id>open</id>
                    <name>Open</name>
                    <name lang="de-DE">Offen</name>
                    <name lang="nl-NL">Open</name>
                    <name lang="it-IT">Aperto</name>
                </option>
                <option>
                    <id>in_progress</id>
                    <name>In progress</name>
                    <name lang="de-DE">In Bearbeitung</name>
                    <name lang="nl-NL">In uitvoering</name>
                    <name lang="it-IT">In corso</name>
                </option>
                <option>
                    <id>completed</id>
                    <name>Completed</name>
                    <name lang="de-DE">Komplett</name>
                    <name lang="nl-NL">Voltooid</name>
                    <name lang="it-IT">Completato</name>
                </option>
                <option>
                    <id>cancelled</id>
                    <name>Cancelled</name>
                    <name lang="de-DE">Abgebrochen</name>
                    <name lang="nl-NL">Geannuleerd</name>
                    <name lang="it-IT">Annullato</name>
                </option>
            </options>
        </input-field>
        <input-field type="single-select">
            <name>orderStateWithRefundTransaction</name>
            <label>Order state with a refund transaction</label>
            <label lang="de-DE">Bestellstatus bei einer zurückerstatteten Transaktion</label>
            <label lang="nl-NL">Bestelstatus bij een terugbetaalde transactie</label>
            <label lang="it-IT">Stato dell'ordine per una transazione di rimborso</label><label lang="pt-PT">Estado da encomenda com uma transação de reembolso</label>
            <options>
                <option>
                    <id>skip</id>
                    <name>Skip this status</name>
                    <name lang="de-DE">nichts machen</name>
                    <name lang="nl-NL">Sla deze status over</name>
                    <name lang="it-IT">Salta questo stato</name>
                </option>
                <option>
                    <id>open</id>
                    <name>Open</name>
                    <name lang="de-DE">Offen</name>
                    <name lang="nl-NL">Open</name>
                    <name lang="it-IT">Aperto</name>
                </option>
                <option>
                    <id>in_progress</id>
                    <name>In progress</name>
                    <name lang="de-DE">In Bearbeitung</name>
                    <name lang="nl-NL">In uitvoering</name>
                    <name lang="it-IT">In corso</name>
                </option>
                <option>
                    <id>completed</id>
                    <name>Completed</name>
                    <name lang="de-DE">Komplett</name>
                    <name lang="nl-NL">Voltooid</name>
                    <name lang="it-IT">Completato</name>
                </option>
                <option>
                    <id>cancelled</id>
                    <name>Cancelled</name>
                    <name lang="de-DE">Abgebrochen</name>
                    <name lang="nl-NL">Geannuleerd</name>
                    <name lang="it-IT">Annullato</name>
                </option>
            </options>
        </input-field>
        <input-field type="single-select">
            <name>orderStateWithPartialRefundTransaction</name>
            <label>Order state with a partial refund transaction</label>
            <label lang="de-DE">Bestellstatus bei einer teilweise erstatteten Transaktion</label>
            <label lang="nl-NL">Bestelstatus bij een gedeeltelijk terugbetaalde transactie</label>
            <label lang="it-IT">Stato dell'ordine per una transazione di rimborso parziale</label><label lang="pt-PT">Estado da encomenda com uma transação de reembolso parcial</label>
            <options>
                <option>
                    <id>skip</id>
                    <name>Skip this status</name>
                    <name lang="de-DE">nichts machen</name>
                    <name lang="nl-NL">Sla deze status over</name>
                    <name lang="it-IT">Salta questo stato</name><name lang="pt-PT">Ignorar este estado</name>
                </option>
                <option>
                    <id>open</id>
                    <name>Open</name>
                    <name lang="de-DE">Offen</name>
                    <name lang="nl-NL">Open</name>
                    <name lang="it-IT">Aperto</name><name lang="pt-PT">Aberto</name>
                </option>
                <option>
                    <id>in_progress</id>
                    <name>In progress</name>
                    <name lang="de-DE">In Bearbeitung</name>
                    <name lang="nl-NL">In uitvoering</name>
                    <name lang="it-IT">In corso</name><name lang="pt-PT">Em curso</name>
                </option>
                <option>
                    <id>completed</id>
                    <name>Completed</name>
                    <name lang="de-DE">Komplett</name>
                    <name lang="nl-NL">Voltooid</name>
                    <name lang="it-IT">Completato</name><name lang="pt-PT">Concluído</name>
                </option>
                <option>
                    <id>cancelled</id>
                    <name>Cancelled</name>
                    <name lang="de-DE">Abgebrochen</name>
                    <name lang="nl-NL">Geannuleerd</name>
                    <name lang="it-IT">Annullato</name><name lang="pt-PT">Cancelado</name>
                </option>
            </options>
        </input-field>
        <component name="mollie-pluginconfig-element-orderstate-select">
            <name>orderStateFinalState</name>
            <entity>state_machine_state</entity>
            <label>Final order state</label>
            <label lang="de-DE">Finaler Bestellstatus</label>
            <label lang="nl-NL">Definitieve Bestelstatus</label>
            <label lang="it-IT">Stato finale dell'ordine</label>
            <helpText>If enabled, the plugin will not transition to a new order state, if the final state is already set in the order. Use this feature in combination with plugins and systems if their workflows do not match the one from Mollie.</helpText>
            <helpText lang="de-DE">Wenn aktiv, wechselt das Plugin nicht in einen neuen Bestellstatus, wenn der finale Status bereits in der Bestellung verwendet wird. Verwenden Sie diese Funktion in Kombination mit Plugins und -Systemen, wenn deren Workflows nicht mit denen von Mollie übereinstimmen.</helpText>
            <helpText lang="nl-NL">Indien ingeschakeld, gaat de plug-in niet over naar een nieuwe bestelstatus, als de definitieve status al in de bestelling is ingesteld. Gebruik deze functie in combinatie met plug-ins en -systemen als hun workflows niet overeenkomen met die van Mollie.</helpText>
            <helpText lang="it-IT">Se abilitato, il plugin non passerà a un nuovo stato dell'ordine se lo stato finale è già impostato nell'ordine. Usa questa funzione in combinazione con plugin e sistemi se i loro flussi di lavoro non corrispondono a quello di Mollie.</helpText>
        </component>

    </card>
</config>
XML;

        $dom = new \DOMDocument();
        $dom->loadXML($exampleXML);


        $appender = new TranslationAppender();
        $result = $appender->append($dom, 'card.orderStateAutomation.orderStateWithAPaidTransaction.options.1.name', 'Ignorar este estado', 'pt-PT');


        $expectedXML = preg_replace('/\s+/', '', $expectedXML);
        $actualXML = preg_replace('/\s+/', '', $dom->saveXML());
        $this->assertEquals($expectedXML, $actualXML);

    }
}