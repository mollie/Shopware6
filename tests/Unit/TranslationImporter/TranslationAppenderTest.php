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
        $actualXML = preg_replace('/\s+/', '', $result->saveXML());
        $this->assertEquals($expectedXML, $actualXML);
    }

    public function testReplaceOldValue():void
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
        $actualXML = preg_replace('/\s+/', '', $result->saveXML());
        $this->assertEquals($expectedXML, $actualXML);
    }

    public function testfindByNameAndAppendNewTitle():void
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
        $actualXML = preg_replace('/\s+/', '', $result->saveXML());
        $this->assertEquals($expectedXML, $actualXML);
    }
    public function testFindByChildNameAndAppendNewLabel():void
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
        $actualXML = preg_replace('/\s+/', '', $result->saveXML());
        $this->assertEquals($expectedXML, $actualXML);
    }

    public function testAppendOptionsInArray():void
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
        $actualXML = preg_replace('/\s+/', '', $result->saveXML());
        $this->assertEquals($expectedXML, $actualXML);

    }

    public function testAppendElementToTheLastElementOfTheSameType():void
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
        $actualXML = preg_replace('/\s+/', '', $result->saveXML());
        $this->assertEquals($expectedXML, $actualXML);
    }

    public function testAppendMultipleTexts():void
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
        $result = $appender->append($result, 'card.payments.shopwareFailedPayment.label', 'Utilizar o comportamento padrão do Shopware para pagamentos falhados', 'pt-PT');
        $result = $appender->append($result, 'card.payments.shopwareFailedPayment.helpText', 'Utilizar o comportamento padrão do Shopware em pagamentos falhados. Se este estiver desativado, a Mollie fornecerá automaticamente uma maneira de tentar novamente o pagamento redirecionando o utilizador para a página de pagamentos da Mollie.', 'pt-PT');


        $expectedXML = preg_replace('/\s+/', '', $expectedXML);
        $actualXML = preg_replace('/\s+/', '', $result->saveXML());
        $this->assertEquals($expectedXML, $actualXML);
    }
}