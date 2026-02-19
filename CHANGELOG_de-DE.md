# Unreleased
- Apple Pay Direct: Telefonnummer wird nun auch bei Gast-Checkout korrekt übernommen, wenn sie nachträglich geändert wird.
- Kompatibilität mit Set-Plugins verbessert. Produkte werden nun bei Mollie einzeln statt als zusammengefasstes Set aufgelistet.
# 4.22.1
- Die Verarbeitung von Positionen mit negativen Beträgen wurde behoben.

# 4.22.0
- Behebung eines falschen Warenkorbpreises bei Apple Pay Direct in Kombination mit Netto-Anzeigepreise bei Kundengruppen. Hier wurden keine Steuern miteinberechnet. 
- Behebung eines Problems, bei dem in manchen zufälligen Fällen die Kreditkartenfelder nicht funktionieren. (mollie.js defer-sync Laden wurde entfernt).
- Wenn ein Kunde die Zahlungsart eines Abonnements ändert, werden alle älteren, noch stornierbaren Zahlungen automatisch abgebrochen.
- Die Kompatibilität mit dem Plugin „Zusatzoptionen/Garantien“ wurde implementiert.
- Kunden mit bestehendem Abonnement haben nun eine Übersichtsseite in der Administration zum Kündigen ihrer Abonnements.
- Die Darstellung der Zahlarten in älteren Shopware-Versionen wurde korrigiert.
- Der „Test API Keys“-Button in den Plugin-Einstellungen wurde für Shopware 6.7 korrigiert.
- Die Zahlungsstatus-Aktion wurde versionsabhängig angepasst, sodass in älteren Shopware-Versionen wieder die korrekte Action verwendet wird.
- iDEAL wurde zu iDEAL | Wero umbenannt.

# 4.21.0
- Versandkosten werden bei Erstattung über Shopware Return Management berücksichtig
- Behoben: Fehler bei Warenkörben mit unterschiedlichen Steuersätzen und Promotion mit proportionaler Steuerberechnung.
- Aktualisiert: Dokumentation zum Endpoint für das Validieren und Erstellen einer Apple-Pay-Zahlungssession korrigiert.
- Behoben: Versandarten wurden in Apple Pay Express angezeigt, obwohl der Versand für diese Länder in der Administration deaktiviert war.
- Aktualisiert: Die Abhängigkeit zum Basecom Fixture Plugin wurde entfernt und durch unser eigenes Fixture Plugin ersetzt.
- Behoben: MolliePaymentMethodAvailabilityRemover berücksichtigt nun auch Warenkörbe mit dem Preis 0, um zu vermeiden, dass alle Zahlungsmethoden entfernt werden.
- Kompatibilität mit Click & Collect Plugin
- Behoben: Beschreibungen von Zahlungsarten wurden beim Checkout angezeigt, obwohl diese nicht ausgewählt waren.
- Die Profilnavigation wurde erweitert und umfasst nun die Verwaltung gespeicherter Kreditkartendaten (nur sichtbar, wenn Kreditkartendaten vorhanden sind).
- Tracking-Parameter sind jetzt optional für alle Versand-API-Routen.

# 4.20.1 
- Problem behoben. In Shopware 6.5 war die Order Übersicht nicht aufrufbar

# 4.20.0 - 2025-11-19
- Order builder wurde angepasst, sodass Bestell-Adressen statt Standard-Kunden-Adressen verwendet werden. So wird sichergestellt, dass die Adressinformationen in Mollie und Shopware übereinstimmen.
- Behoben: Ein Problem, bei dem Apple Pay Direct nicht funktionierte, wenn im Shop die Telefonnummer als Pflichtfeld konfiguriert war.
- Kompatiblitätsprobleme mit Shopware Commercial Plugin behoben
- Behoben: Im Admin wurden bei Bestellungen fälschlicherweise Mollie-Daten angezeigt, obwohl die finale Transaktion nicht von Mollie stammte.
- Shopware Refunds wendet nun den korrekt erstatteten Betrag an.
- Überschrift in der Konfiguration wurde behoben

## [4.19.0] - 2025-10-09
- Unterstützung für die estnische Sprache hinzugefügt
- Unterstützung für die griechische Sprache hinzugefügt
- Unterstützung für die kroatische Sprache hinzugefügt
- Unterstützung für die isländische Sprache hinzugefügt
- Unterstützung für die litauische Sprache hinzugefügt
- Unterstützung für die lettische Sprache hinzugefügt
- Unterstützung für die rumänische Sprache hinzugefügt
- Unterstützung für die slowakische Sprache hinzugefügt
- PayByBank kann jetzt auch für Abonnements verwendet werden.
- Das Problem wurde behoben, dass ein Abonnement nicht neu gestartet werden konnte, wenn das nächste Zahlungsdatum in der Zukunft lag.

## [4.18.0] - 2025-09-08
### Hinzugefügt
- Die Zahlungsmethode Bizum ist jetzt für Mollie Payments verfügbar.

### Geändert
- Der Bestell- und Zahlungsstatus wird nun ausschließlich über Webhooks geändert. Dadurch wird verhindert, dass der Status doppelt geändert wird, wenn der Kunde gleichzeitig zum Shop zurückgeleitet wird und der Webhook ausgelöst wird. Falls Sie ein Testsystem verwenden, das keine externen Webhooks akzeptiert, setzen Sie bitte die Umgebungsvariable `MOLLIE_DEV_MODE=1`.
- Die Finalize Action nutzt nun den SalesChannel aus der Bestellung. In manchen Fällen wird der SalesChannel jedoch nicht korrekt gesetzt, was dazu führen kann, dass während der Finalize Action falsche API-Keys verwendet werden.
- Polyfill-Klassen wurden so angepasst, dass sie nur noch geladen werden, wenn sie in Shopware nicht mehr existieren.
- Twig-Variable „formCheckInputClass” zu den Zahlungsmethoden hinzugefügt
- Kreditkartenzahlung wird nun über Twig statt über JavaScript dargestellt

### Behoben
- Fehlerhafte Doctrine-Parametertypen in Elasticsearch und Migrationen behoben.
- Fehler beim Logging behoben, wenn der automatische Versand nicht funktionierte.
- Problem beim Speichern von Kreditkarteninformationen behoben.
- Fehler in der Payment Method Route der Store-API behoben.
- Problem bei der Konfigurationszuweisung des Refund Managers behoben.
- Fehler behoben, bei dem die letzten verbleibenden Abonnement-Laufzeiten zurückgesetzt wurden, wenn pausiert und fortgesetzt wurde.
- Fehler behoben, bei dem der Ordner `storefront/dist` nicht existierte.
- Fehler beim automatischen Versand behoben, wenn die Tracking-Codes lediglich leere Strings waren.

## [4.17.0] - 2025-08-04
### Hinzugefügt
- Validierungsfehler werden nun angezeigt, wenn ein Gastkonto über Express Checkout erstellt wird, z. B. mit PayPal Express oder Apple Pay Direct.

### Geändert
- Der Refundmanager ist jetzt für Bestellungen im Status „Autorisiert“ deaktiviert. Eine Rückerstattung ist nicht möglich, solange noch kein Betrag erfasst wurde.
- Die Position ausstehender Rückerstattungen im Refundmanager wurde angepasst.

### Behoben
- Webhook-Problem behoben: Sie wurden gleichzeitig mit der Rückleitung in den Shop ausgeführt, was den Zahlungsstatus doppelt geändert wurde.
- Sprache der Fehlermeldungen in Zahlungsformularen korrigiert.
- Fehler beim Klonen von Bestellungen bei Abo-Verlängerungen behoben.
- Anzeige zurückerstatteter Artikel im Refundmanager korrigiert, wenn die Rückerstattung noch aussteht.
- Button-Styling im Refundmanager behoben.
- Division-durch-null-Fehler bei Rabatten ohne Betrag behoben.

## [4.16.0] - 2025-06-23
### Hinzugefügt
- Kompatibilität mit Shopware 6.7 hinzugefügt.
- Unterstützung für die norwegische Sprache hinzugefügt.
- Unterstützung für die schwedische Sprache hinzugefügt.
- Unterstützung für die polnische Sprache hinzugefügt.
- Unterstützung für die tschechische Sprache hinzugefügt.
- Unterstützung für die slowenische Sprache hinzugefügt.
- Unterstützung für die ungarische Sprache hinzugefügt.
- Unterstützung für die finnische Sprache hinzugefügt.
- Unterstützung für die dänische Sprache hinzugefügt.
- `orderId` zur JSON-Antwort der Apple Pay Direct-Zahlungsroute in der Store API hinzugefügt.

### Geändert
- Typehints für `EntityRepository` entfernt, um die Dekoration der Repositories in Shopware 6.4 zu ermöglichen.
- Banküberweisungszahlungen werden nun auf den Status „In Bearbeitung“ statt „Unbestätigt“ gesetzt, da diese Zahlungsarten mehrere Tage zur Verarbeitung benötigen und nicht verändert werden sollten.
- Mandate für Mollie-Kunden werden nicht mehr geladen, wenn der Kunde im Mollie-Dashboard gelöscht wurde.
- Die Funktionalität „Webhook zu früh“ wurde entfernt, indem Race Conditions bei `updatePayment` auf andere Weise behoben wurden. Webhook-Updates sind dadurch wieder schneller.
- Der Zahlungsstatus „offen“ ist jetzt wieder gültig für Kreditkarten. In früheren Abläufen war dies problematisch, aber durch neue asynchrone Abläufe ist dies nun absichtlich ein gültiger Status.
- Logs im `PaymentMethodRemover` entfernt, die dazu führten, dass Logdateien und Speicherplatz übermäßig befüllt wurden, wenn Symfony Anfragen zu Assets wie CSS oder Bildern verarbeitet hat.
- Minimale PHP-Version auf 8.0 erhöht.

### Behoben
- Fehler „Call to a member function info() on null“ behoben.
- Problem behoben, bei dem ein falscher API-Schlüssel verwendet wurde, wenn Positionen in der Administration storniert wurden.
- Problem behoben, bei dem sich die Zahlungsart einer PayPal-Express-Transaktion durch Webhooks fälschlicherweise zu PayPal geändert hat.

## [4.15.0] - 2025-03-04
### Hinzugefügt
- Die Zahlungsmethode Swish ist jetzt für Mollie Payments verfügbar.

### Geändert
- Bisher führte eine Stornierung eines Express-Checkouts dazu, dass der ursprüngliche Warenkorb wiederhergestellt wurde. Dies passiert nun nicht mehr, wenn der vorherige Warenkorb leer war. Das Produkt aus dem stornierten Express-Checkout bleibt daher jetzt im Warenkorb.
- Verbesserung der Art und Weise, wie Express-Checkouts (Apple Pay Direct, PayPal Express) Warenkörbe bei Stornierung sichern und wiederherstellen.
- Vollständige Rückerstattungen berücksichtigen nun bereits ausstehende (teilweise) Rückerstattungen. Es ist jetzt wesentlich einfacher, den Restbetrag einer Bestellung ebenfalls zu erstatten.
- Die NPM-Dev-Abhängigkeiten für die Administration und das Storefront, die wir für Tests verwenden, wurden an einen Ort verschoben, den Shopware nicht nutzt. Dies sollte die Entwicklung eines Shops mit installiertem Mollie-Plugin erheblich beschleunigen.
- Die Abonnementseite im Kundenkonto wurde aktualisiert, um den WCAG-Standards zu entsprechen.

### Behoben
- Ein Problem mit Übergängen bei zu frühen Webhook-Aufrufen von Mollie wurde behoben.
- Ein Fehler im Zusammenhang mit defekten PayPal Express-Checkouts in Kombination mit bestimmten seltenen PayPal-Adressen wurde behoben.
- Ein Problem wurde behoben, bei dem es möglich war, nach Abbruch der Autorisierung im PayPal Express-Modus festzustecken.
- Fehler in den PayPal Express-Abläufen behoben, bei denen Warenkörbe plötzlich fehlten oder nicht korrekt wiederhergestellt wurden.
- Ein Problem in PayPal Express (JavaScript) wurde behoben, bei dem der Checkout bereits initialisiert wurde, bevor das Produkt korrekt zum Warenkorb hinzugefügt wurde.
- Das Problem mit gespeicherten Kreditkarten wurde behoben. Wenn eine Zahlung zuerst mit einer anderen Zahlungsmethode durchgeführt wurde und diese fehlschlug, scheiterte jeder nachfolgende Versuch mit Kreditkarte und gespeichertem Token.

## [4.14.1] - 2025-02-03
### Behoben
- Geplante Aufgaben funktionieren wieder.

## [4.14.0] - 2025-02-03
### Hinzugefügt
- Rückgaben für Shopware Commercial Plugins werden nun an Mollie übertragen, wenn der Rückgabestatus auf „Erledigt“ gesetzt ist, und können mit dem Status „Storniert“ storniert werden. Bitte beachten Sie, dass Rückerstattungen nach zwei Stunden nicht mehr storniert werden können.
- Die Zahlungsmethode MB Way ist jetzt für Mollie Payments verfügbar.
- Die Zahlungsmethode Multibanco ist jetzt für Mollie Payments verfügbar.
- Portugiesische Übersetzung hinzugefügt.
- Spanische Übersetzung hinzugefügt.

### Geändert
- Die minimal unterstützte Shopware-Version ist jetzt 6.4.5.0.
- Ein neuer Monolog-Kanal „mollie“ wurde hinzugefügt. Sie können nun benutzerdefinierte Handler hinzufügen und dem Mollie-Kanal zuweisen.
- Wenn ein Webhook von Mollie zu früh an den Shop gesendet wird, wird nun eine Debug-Nachricht anstelle einer Warnung protokolliert.

### Behoben
- Fehler in den Bestelldetails des Rückerstattungsmanagers für Shopware 6.4.x behoben.
- Ein Problem mit SwagCustomizedProducts wurde behoben, sodass Preise für Optionswerte nun korrekt zur Bestellung hinzugefügt werden.
- Das Problem mit der `OrderNotFoundException` wurde behoben. Diese Klasse wurde in Shopware 6.5.0 entfernt und wird im Plugin nicht mehr verwendet.
- Die Kompatibilität mit dem Shopware B2B Suite Plugin wurde behoben.

## [4.13.0] - 2024-12-17
### Features
- Die Zahlungsmethode Trustly kann jetzt für Abonnements verwendet werden.

### Verbesserungen
- Die Anzahl der Ajax-Calls auf der Bestelldetailseite in der Administration wurde reduziert.
- Der Zahlungsstatus wird nun auf „Unbestätigt“ statt „In Bearbeitung“ gesetzt. Dadurch kann der Kunde die Bestellung abschließen, selbst wenn er die Seite des Zahlungsanbieters geschlossen oder den Zurück-Button des Browsers benutzt hat.
- Webhooks werden nun erst zwei Minuten nach der Bestellerstellung akzeptiert. Dies verringert das Risiko, dass der Webhook den Bestellstatus aktualisiert, bevor die Bestellung im Shop abgeschlossen ist.
- Die automatische Ablaufzeit ignoriert Bestellungen, bei denen die zuletzt genutzte Zahlungsmethode keine Mollie-Zahlung war.
- Die Billie-Zahlungsmethode wird ausgeblendet, wenn in der Rechnungsadresse kein Firmenname angegeben ist.
- Beim Versenden oder Stornieren von Artikeln werden die Versandkosten für Klarna-Zahlungen als „versendet“ markiert.
- Beim Versand über Mollie werden ungültige Tracking-Codes ignoriert. So wird sichergestellt, dass die Bestellung trotzdem als „versendet“ markiert wird, auch wenn die Tracking-Informationen fehlerhaft sind.

### Fehlerbehebungen
- Apple Pay: Gastkonten werden nun für dieselbe E-Mail-Adresse wiederverwendet.
- Das Problem mit der automatischen Ablaufzeit und Banküberweisung wurde behoben. Banküberweisungen wurden zuvor zu früh storniert. Jetzt werden sie nach 100 Tagen storniert. Diese Einstellung kann in der Plugin-Konfiguration angepasst werden.

## [4.12.1] - 2024-11-14
### Hotfix
- Kompatibilität mit Shopware 6.6.8.x wurde behoben.
- Datenschutz-Checkbox ist versteckt, wenn Apple Pay Direct im Browser nicht verfügbar ist.

## [4.12.0] - 2024-11-11
### Features
- PayPal Express ist jetzt für Beta-Tester verfügbar.
- Die neue Zahlungsmethode „PayByBank“ ist jetzt verfügbar.

### Verbesserungen
- Das automatische Laden von Shopware-Kompatibilitätsdateien wird nun während der Plugin-Laufzeit geladen.
- Gutschriften können nun für Rückerstattungen mit benutzerdefinierten Beträgen erstellt werden.
- Italienische Übersetzung zur Konfiguration hinzugefügt.
- Ausführlichere Log-Nachrichten für Statusänderungen hinzugefügt.
- Die Zahlungsmethode Apple Pay wird nun im Warenkorb ausgeblendet, wenn die Versanddetails angezeigt werden und Apple Pay im Browser nicht verfügbar ist.

### Veraltete Funktionen
- Die Apple-Pay-Headless-Route `/mollie/applepay/add-product` ist jetzt veraltet. Bitte verwenden Sie die Standard-`addToCart`-Route von Shopware. Wenn Sie den aktuellen Warenkorb des Benutzers temporär speichern und nur das aktuelle Produkt bezahlen möchten (z. B. direkter Checkout von der Produkt- oder Kategorieseite), fügen Sie der `addToCart`-Anfrage den Parameter `isExpressCheckout=1` hinzu. Nach dem Checkout wird der ursprüngliche Warenkorb wiederhergestellt.

### Fehlerbehebungen
- Benutzerdefinierte Produkte mit konfigurierten Zusatzbeträgen werden nun korrekt zum Checkout hinzugefügt.
- Benutzerdefinierte Produkte können nicht über Apple Pay direkt gekauft werden, bis alle erforderlichen Felder ausgefüllt sind.

## [4.11.2] - 2024-10-17
### Hotfix
- Kompatibilitätsprobleme mit Shopware 6.6.7.0 wurden behoben

## [4.11.1] - 2024-10-09
### Hotfix
- "Zum Warenkorb hinzufügen" auf der Produkt-Detailseite funktioniert wieder, wenn Apple Pay Direct aktiviert ist und Datenschutzbestimmungen über eine Checkbox akzeptiert werden müssen.
- Anlegen der Bestellungen in der Administration funktioniert wieder.

## [4.11.0] - 2024-10-08
### Features
- Gutschriften können während der Rückerstattung erstellt werden.
- Die Zahlungsmethode "Billie" wird nur für Geschäftskunden angezeigt.
- Abonnement-Bestellungen haben ein benutzerdefiniertes Tag.
- Apple Pay Direct: Wenn DSGVO in der Administration aktiviert ist, werden zusätzliche Kontrollkästchen über den Buttons angezeigt.
- Apple Pay Direct: Der Selektor zum Auffinden und Ausblenden von Apple-Pay-Direct-Buttons in JavaScript wurde geändert, um die Verwendung mit benutzerdefinierten Themes zu verbessern.
- Apple Pay Direct ist jetzt mit dem Shopware Custom Product Plugin kompatibel.
- Der Refund Manager ist nur verfügbar, wenn die Bestellung erstattungsfähige Artikel enthält.

### Verbesserungen
- Die Installation des Mollie-Plugins über Composer zeigt nicht mehr den Fehler an, dass der "dist"-Ordner nicht existiert.
- Apple Pay Direct findet die richtige Versandmethode, wenn der Kunde die Adresse im Apple Pay Overlay ändert.
- Kunden können bei Mollie mit unterschiedlichen Profilen in verschiedenen Vertriebskanälen erstellt werden.
- Italienische Übersetzung zur Administration hinzugefügt.

### Fehlerbehebungen
- Das Problem wurde behoben, dass in einigen Fällen der Webhook von Apple Pay Direct schneller ausgelöst wurde als die Aktualisierung der Bestellung in Shopware.
- Fehlendes MailActionInterface für Shopware 6.4 hinzugefügt.

## [4.10.2] - 2024-09-27
### Hotfix
- Problem mit fehlendem Code für die automatische Lieferung behoben.
- Mehr Log-Informationen für bessere Nachverfolgung hinzugefügt.
- Sicherstellung, dass Lieferinformationen auch bei fehlendem Code an Mollie übermittelt werden.
- Automatisches Verfallen von Bestellungen kann jetzt in den Plug-in-Einstellungen deaktiviert werden.
- Das automatische Verfallsystem findet alle Bestellungen mit dem Zahlungsstatus "In Bearbeitung" der letzten zwei Monate und storniert sie, wenn das Bestelldatum nach Ablauf der festgelegten Zahlungslink-Gültigkeitsdauer liegt.

## [4.10.1] - 2024-09-05
### Hotfix
- Problem mit Speicherverbrauch in der neuen geplanten Aufgabe "mollie.order_status.expire" wurde behoben.
- Probleme mit dem Markieren der Bestellung als Versendet wurde behoben.

## [4.10.0] - 2024-08-28
### Features
- Neue Zahlungsmethode „Riverty“ ist jetzt verfügbar.
- Neue Zahlungsmethode „Payconiq“ ist jetzt verfügbar.
- Neue Zahlungsmethode „Satispay“ ist jetzt verfügbar.
- Neues Event hinzugefügt: SubscriptionCartItemAddedEvent. Dies ermöglicht es Ihnen, benutzerdefinierte Logik zu implementieren, wenn ein Abonnementartikel zum Warenkorb hinzugefügt wird.
- Italienische Übersetzungen hinzugefügt.

### Verbesserungen
- Apple Pay Direct fragt jetzt nach der Telefonnummer, wenn das Telefonfeld in der Administration aktiviert ist.
- Apple Pay Direct-Gastkonten werden nun wiederverwendet, anstatt jedes Mal neu erstellt zu werden.
- Der ElasticSearch Indexer ist jetzt mit Mollie kompatibel.
- Beim Verwenden des Buttons "Über Mollie versenden" kann jetzt eine vollständige URL im Code-Eingabefeld eingegeben werden. Die URL wird automatisch aus dem Code extrahiert.
- Bestellungen, die im Status "in Bearbeitung" festhängen, werden nun storniert, wenn der Zahlungslink in Shopware abläuft. Die Ablaufzeit kann in den Shopware-Warenkorbeinstellungen konfiguriert werden.

### Fehlerbehebungen
- Bestellungen können nun erstellt werden, wenn Bildnamen Sonderzeichen in den Abfragewerten enthalten, z. B. `product.png?width={width}`.
- Ein Problem wurde behoben, bei dem Polyfill-Klassen falsch geladen wurden.
- Ein Problem wurde behoben, bei dem Lieferstatus nicht korrekt geladen wurden, was zu Problemen beim automatischen Versand führte.

## [4.9.3] - 2024-07-04
### Hotfix
- Apple Pay Direct funktioniert wieder, wenn die Telefonnummer bei der Registrierung nicht erforderlich ist.

## [4.9.2] - 2024-07-03
### Neuerungen
- Neue Zahlungsmethode "Trustly" ist nun verfügbar.
- Neue Zahlungsmethode "Payconiq" ist nun verfügbar.

### Verbesserungen
- Die Anzahl der Anfragen an die Datenbank beim Laden von Konfigurationsdaten wurde reduziert.

### Fehlerbehebungen
- Erstellen einer Bestellung wurde behoben. Wenn ein SalesChannel eine ungültige Lokalisierung hatte, führte es zu Fehler.

## [4.9.1] - 2024-06-27
### Neuerungen
- Giropay ist eingestellt und wird nach dem Update nicht aktiviert. Bitte deaktivieren Sie die Zahlungsmethode und entfernen Sie die Zuordnung zum Verkaufskanal.

### Verbesserungen
- Die Apple Pay Direct: Telefonnummer wird beim Bezahlvorgang abgefragt, wenn die Telefonnummer in der Shopware-Konfiguration erforderlich ist.

### Fehlerbehebungen
- Produkte in der Bestellung wieder sichtbar bei nicht mollie Zahlungen.
- Die Apple Pay Direct: Verifizierung funktioniert auch für Domains mit Sonderzeichen.
- Apple Pay Direkt: Versandarten berücksichtigen nun Verfügbarkeitsregeln.

## [4.9.0] - 2024-06-25
### Neuerungen
- In Vorbereitung auf die vollständige Kompatibilität für iDeal 2.0 haben wir die Bank-/Ausstellerauswahl im Checkout entfernt. Dies geschieht, um dem Käufer ein möglichst angenehmes Erlebnis zu gewährleisten.
- Autorisierte Produkte, die über Klarna bestellt wurden, können nun in Shopware in der Bestellung abgebrochen werden.
- OpenApi-Definition wurde hinzugefügt. Plugin-Routen werden jetzt in Shopware Swagger angezeigt.

### Fehlerbehebungen
- Polyfill-Klassen werden jetzt mit dem richtigen Namespace geladen.

## [4.8.1] - 2024-05-23
### Hotfix
- Die Kreditkarteneingabefelder in Shopware 6.6 wurden behoben.

## [4.8.0] - 2024-05-21
### Features
- Neue Zahlungsmethode "Alma" ist jetzt verfügbar.
- Neue Zahlungsmethode "MyBank" ist jetzt verfügbar.
- Neue Zahlungsmethode "Bancomat Pay" ist jetzt verfügbar.

### Verbesserungen
- Verbesserte Kompatibilität mit dem Plugin AcrisPersistentCart.

### Fehlerbehebungen
- Ein JavaScript-Warnhinweis im Storefront auf Seiten ohne Offcanvas wurde behoben.
- Ein Problem bei der Erstellung von Bestellungen und ImageURLs wurde behoben. Wenn ein Produkt ein Sonderzeichen im Produktbilddateinamen hatte, konnte die Bestellung nicht erstellt werden.
- Die Definition der CSS-Klasse "d-none" wurde korrigiert. Sie wird nun nur noch innerhalb der Mollie-Klassen und nicht global angewendet.

## [4.7.2] - 2024-04-30
### Hotfix
- Kompatibilität mit Klarnapayment Plugin wurde behoben.
- SnippetFileInterface wurde nachgereicht.

## [4.7.1] - 2024-04-30
### Hotfix
- Probleme mit Routen wurden behoben.
- CSS-Klasse d-none greift nur innerhalb von Mollie Komponenten.

## [4.7.0] - 2024-04-29
### Features
- Kompatibilität mit Shopware 6.6.
- Support für Shopware 6.4.0.0 wurde eingestellt, neue minimale Version ist 6.4.1.0.
- Weitere Checkbox im Refundmanager. Es gibt die Möglichkeit, die Steuern zu erstatten bei Netto-Bestellungen.

### Verbesserungen
- Das Laden der mollie-payments.js wurde optimiert.

### Fehlerbehebungen
- Polyfill Klassen für Shopware 6.4.20.2 wurde behoben. Mit dem Feature FEATURE_NEXT_17858 gab es Probleme im Flowbuilder.

## [4.6.0] - 2024-03-26
### Features
- Neue Zahlungsmethode Klarna One jetzt in Großbritannien verfügbar. Die Verfügbarkeitsregeln von Mollie für Zahlungsmethoden in den Plugin-Einstellungen können jetzt die Methode für jeden Kunden anzeigen oder ausblenden.

### Verbesserungen
- Optimiertes ACL-Verhalten für Admin-Benutzer mit weniger Berechtigungen. Das Plugin erfordert nicht mehr die Berechtigung system:config:read.
- Die Mollie-JS-Datei wird jetzt nicht mehr auf jeder Storefront-Seite geladen, sondern nur, wenn sie tatsächlich benötigt wird.
- Apple Pay kann jetzt als Standardzahlungsmethode im Kontobereich in anderen Browsern als Safari ausgewählt werden.
- Die Erstellung von Gastkonten für Apple Pay Direct verwendet das Standardverhalten und die Einstellungen von Shopware aus der Administration. Zum Beispiel Kunden an Vertriebskanal binden.
- Die Versandmethoden für Apple Pay Direct verwenden jetzt das Standardverhalten von Shopware.

### Fehlerbehebungen
- Behobenes Problem beim Speichern von Zahlungsmethoden im Admin, wenn die Systemsprache von Shopware auf etwas anderes als en-GB geändert wurde.
- Behobener Tippfehler in der "OrderAware" Kompatibilitätsklasse für ältere Shopware-Versionen.

## [4.5.0] - 2024-02-19
### Features
- Neue Zahlungsmethode "Blik" ist nun verfügbar für die Währung Zloty.
- "Mollie Limits" wurde erweitert und umbenannt in "Mollie Verfügbarkeitsregeln". Wenn diese Option im Plugin aktiviert ist, werden alle Zahlungsarten, die nicht aktiv im Mollie-Dashboard sind, deaktiviert. Außerdem werden Zahlungsarten deaktiviert, wenn folgende Regeln eintreffen:
    - Minimaler Wert im Warenkorb nicht erreicht.
    - Maximaler Wert im Warenkorb überschritten ist.
    - Nur vorgegebene Währungen sind erlaubt.
    - Nur für vorgegebene Rechnungsadressen erlaubt.

### Verbesserungen
- Shopware Cache bei der Auflistung von Zahlungsmethoden berücksichtigt nun den Wert des Warenkorbs, Währungswechsel und Lieferanschrift.

### Fehlerbehebungen
- Beim Kauf eines Abonnements wurde man als Gast angemeldet nach der Registrierung. Das wurde nun behoben.

## [4.4.2] - 2024-01-24
### Verbesserungen
- Kompatibilität mit neuer Shopware Version 6.5.8.2.

## [4.4.1] - 2024-01-22
### Hotfix
- Der Technische Name der Zahlarten wurde angepasst, dieser wird automatisch gesetzt sobald das Plugin aktualisiert bzw. installiert wurde.

## [4.4.0] - 2024-01-18
### Features
- Neues Feature um einzelne Produkte einer Bestellung zu versenden. Es ist nun möglich in einer Bestellung einzelne Produkte und die Anzahl der Produkte als Bestellt zu markieren und diese Information an Mollie weiterzugeben.
- Neue Konfiguration im Plugin, damit lässt es sich einstellen wie lange die Logs gespeichert werden sollten.

### Verbesserungen
- Der Technische Name der Zahlarten wurde angepasst, dieser wird automatisch gesetzt sobald das Plugin aktualisiert bzw. installiert wurde.
- Wenn in Shopware der Lagerbestand über den Stockmanager verwaltet wird, wird das auch im Refundmanager berücksichtigt und der Lagerbestand wird nicht erhöht nach einem Refund.
- Mollie Bank Informationen werden an der Bestellung in customFields mit gespeichert.
- Geringe Performance Verbesserungen auf der checkout Seite.
- Automatisches Versenden einer Order über Einstellung oder Flowbuilder sendet nun auch den konfigurierten Tracking Code an Mollie.

### Fehlerbehebungen
- In seltenen Fällen hat der Refundmanager nicht funktioniert wenn ein Gutschein kein label hatte, das wurde nun behoben.
- Die Übersicht der Abonnements funktioniert nun wieder wenn ein Kunde gelöscht wurde.

## [4.3.0] - 2023-11-08
### Verbesserungen
- Refund Manager kann nun geöffnet werden in Kombination mit dem SwagCommercial Plugin.
- Kompilieren der Assets ohne Datenbank ist nun möglich.
- Installation des Plugins über Composer zeigt keine Warnungen.
- Timeout für Mollie API Anfragen wurde auf 10 Sekunden erhöht.
- Einige externe mollie links wurden mit einem "noopener" und "noreferrer" anker tag versehen.

### Fehlerbehebungen
- Gutscheine können nun mit Bundle-Produkten eingesetzt werden.

## [4.2.0] - 2023-10-04
### Features
- Die neue Zahlungsmethode POS (Point of Sale) ist nun verfügbar. Gemeinsam mit den POS Terminals von Mollie kann Shopware nun auch für Offline-Zahlungen in Ihrem Geschäft benutzt werden. Mehr über die Mollie POS Terminals gibt es hier: https://www.mollie.com/de/products/pos-payments.
- Die neue Zahlungsmethode TWINT ist nun verfügbar (bald in Ihrem Mollie Account verfügbar).

### Verbesserungen
- Refunds via Refund Manager unterstützen nun eine beliebige Anzahl von Line-Items bei Rückerstattungen. Das Problem mit der maximalen Größe des Metadata Speichers ab ca. 10 Stück ist nun behoben.
- Der Refund Manager unterstützt nun auch einen Line-Item Refund mit Stückzahl 0. Dies ermöglicht es einen Freibetrag für einen Artikel ohne Stückzahl zu erstatten und diesen Artikel auch in der Zusammensetzung der Rückerstattung zu sehen.
- Das RefundStarted Flow Builder Event enthält nun auch eine Variable "amount" für den Wert der Rückerstattung.
- Abonnements in der Administration werden nun in der Suche unterstützt.

## [4.1.0] - 2023-09-05
### Verbesserungen
- Apple Pay Direct beinhaltet nun eine zusätzliche Adresszeile.
- Die Abhängigkeit der JS Bibliothek regenerator-runtime wurde entfernt, dies führte in seltenen Fällen zu Fehlern in der Storefront.

### Fehlerbehebungen
- In seltenen Fällen, waren nicht alle Zahlarten sichtbar, wenn man nach einer abgebrochenen Zahlung wieder zurück zum Shop weitergeleitet wurde.
- Die Aktivierung des Mollie-Fehlermodus führt nicht mehr zu einem Fehler, wenn eine Zahlung storniert wird.
- Einige Kompatibilitätsprobleme mit Shopware 6.4.3.1 wurden behoben.
- Business-Events in der Administration können in Shopware 6.4.3.1 wieder eingesehen werden.
- Darstellung der Zahlarten im Checkout wurden in Shopware 6.5 behoben.
- Darstellung der Liefermethoden im Warenkorb wurden in Shopware 6.5 behoben.
- Die Löschung der mollie-payments.js beim bauen des Administrators wurden behoben.

## [4.0.0] - 2023-06-07
### Wichtige Änderungen
- Die neue Version 4.0 wurde umstrukturiert, um sowohl Shopware 6.4 als auch das neue Shopware 6.5 mit einem einzigen Plugin zu unterstützen. Das bedeutet, dass das Javascript in der Storefront nun aus einer separaten mollie-payments.js Datei geladen wird. Dieses Verhalten kann natürlich deaktiviert werden, wenn Sie die Storefront selbst kompilieren möchten (weitere Informationen finden Sie in der Dokumentation). Wenn Sie keine iDEAL-Dropdown-Menüs oder Kreditkartenkomponenten sehen, kann dies bedeuten, dass Ihr (benutzerdefiniertes) Theme versehentlich das Shopware-Standard-Theme auf falsche Weise überschreibt.

### Features
- Volle Unterstützung für Shopware 6.5.
- Die Zahlungsart „Kreditkarte” wurden nun in „Karte” umbenannt, da sie auch Debitkarten zulässt.

### Fehlerbehebungen
- Falsche fixe Menge von „1" beim Erstellen von Versandzeilen (Shipping Items) für Mollie behoben. Benutzerdefinierte Implementierungen mit unterschiedlichen Mengen werden nun auch korrekt an Mollie weitergegeben.
- Fehler der “Division durch Null” bei fehlenden Steuersätzen in der Bestellung in seltenen Fällen von Shop-Konfigurationen behoben.
- Fehler in der Refund Manager ACL behoben. Bei eingeschränkten Benutzerrollen trat beim Erstellen von Rückerstattungen ein Fehler auf, obwohl die Rückerstattung immer korrekt an Mollie weitergeleitet wurde.

## [3.6.0] - 2023-03-16
### Features
- Neue Zahlungsmethode "Billie" ist nun verfügbar.
- Mit dem neuen Feature "Automatische Stornierung" in der Plugin Konfiguration kann nun das bisher fest integrierte Stornieren von Klarna Bestellungen optional deaktiviert werden.
- Mittels neuem Platzhalter "customernumber" für das benutzerdefinierte Bestellnummern-Format, kann nun auch die Kundennummer in der Bestellnummer integriert werden.

### Verbesserungen
- [Entwickler] Das deprecated Feld "mollieStatus" wurde nun aus der Subscription entfernt. Seit einiger Zeit wird hier das Feld "status" benutzt.

### Fehlerbehebungen
- Bestellungen mit Rückerstattungen können nun wieder gemäß Shopware-Standard gelöscht werden.
- Kompatibilitätsproblem mit Plugin "Preise nach Login..." von NetInventors behoben.
- Fehlerbehebung von Problemen mit dem automatischen Routenermittler für Webhooks in Headless-Shops auf Basis von Symfony Flex (.ENV Parameter Problem).
- Entfernung des Logs-Eintrages "Produkt ist kein Abo-Produkt mehr.." welches fälschlicherweise immer erstellt wurde.
- Fehlerbehebung eines TWIG Template Fehlers in Kombination mit One-Click Payments und Shopware 6.3.5.x.
- Es wurden falsche "Assoziationen" beim Laden von Bestellungen entfernt, welche zu unschönen Log-Einträgen führten.

## [3.5.0] - 2023-02-23
### Hinweise
- Die Plugin Konfiguration "finaler Bestellstatus" besitzt nun nur mehr die erwarteten Einträge der Statusliste. Bitte prüft, ob hierbei die Konfiguration nach dem Update noch korrekt ist.

### Features
- Mit der Integration von One-Click Payments können Kunden, Kreditkartendaten auf einfache Art und Weise für erneute Bestellungen speichern. Dabei werden keine sensiblen Daten in Shopware hinterlegt.
- Der Refund Manager bietet nun die Möglichkeit, zusätzlich zu offiziellen Kontoauszugbeschreibungen, interne Kommentare bei Rückerstattungen anzugeben.
- Neue Flow Builder Events CheckoutSuccess, CheckoutFailed und CheckoutCanceled für die Storefront. Damit kann individuell auf Ereignisse während des Zahlvorgangs eingegangen werden.

### Verbesserungen
- Die Spalte "Mollie" in der Bestellübersicht der Administration zeigt nun auch die Mollie ID der Bestellung.
- Neuer DEBUG Log Eintrag, sofern ein Abonnement aufgrund invalider Daten nicht korrekt erstellt werden konnte.
- Die Plugin Konfiguration zeigt nun sofort Anleitungen für den Bereich individuelle Bestellnummer, und nicht erst dann, wenn man etwas konfiguriert.
- Die Plugin Konfiguration für den finalen Bestellstatus zeigt nun nur mehr die normalen Statuseinträge von Bestellungen.

### Fehlerbehebungen
- Behebung des Javascript Problems durch Apple Pay Direct in der Storefront.
- Behebung des Problems, bei dem ein automatischer "Abbruch" von Klarna Bestellungen via Administration nicht den korrekten API Key des Sales-Channels benutzt hat.
- Behebung des Problems bei dem eine Anonymisierung der URL in den Logs nicht richtig funktionierte. Dies betrifft jedoch nur einmalig benutzte Tokens während des Bezahlvorganges.

## [3.4.0] - 2023-01-10
### Breaking Changes
- Für die zukünftige Erweiterungen für Abonnements mussten wir die Webhooks für diese anpassen. Sollte es Firewall Regeln dafür geben, müssen diese Regeln für die neuen Webhooks angepasst werden: https://github.com/mollie/Shopware6/wiki/Webhooks
- Status (Badges) für Abonnements werden nun nicht mehr direkt von Mollie geladen sondern von der lokalen Datenbank bezogen. Dieses neue und leere Feld wird normalerweise automatisch befüllt. Sollten Statuseinträge unerwartet leer sein, lassen Sie uns das bitte wissen.
- Da wir stets bemüht sind, die beste Qualität abzuliefern, waren wir gezwungen den Support für ältere Shopware Versionen unter 6.3.5 einzustellen. Sollte dies ein Problem sein, bitten wir Sie uns zu kontaktieren um eine mögliche Lösung zu finden. Wir bedauern diesen Schritt und bitten um Verständnis. Nur so ist es möglich langfristig eine hohe Qualität zu bewahren.

### Features
- Neues Management für Abonnements. Diese können nun auch pausiert, erneuert oder einmalig ausgesetzt werden.
- Apple Pay Direct ist nun auch im Offcanvas sowie im Warenkorb als Express Zahlart verfügbar.
- Neues Feature für "Rundungsanpassungen" um auch mit speziellen Rundungseinstellungen in Shopware Zahlungen durchführen zu können.
- Neue Berechtigungsmöglichkeiten für Abonnements und Refund Manager in der Administration.
- Möglichkeit zur Konfiguration eines individuellen Formats von Bestellnummern in Mollie.

### Verbesserungen
- Absicherungen für API Keys. Es ist nun nicht mehr möglich einen Live API Key im Testfeld einzutragen und umgekehrt.
- Die Plugin Konfiguration wurde neu aufgebaut um eine bessere Übersicht zu geben.
- Kreditkarten Komponenten funktionieren nun auch mit dem CSRF Modus von Shopware.
- Verbesserung der Kompatibilität zum Plugin "Best Practice Checkout".
- Icons von Zahlungsmethoden werden nun über einen anderen Weg bei Erstinstallation geladen. Dies ist gut wenn am Server kein "file_get_contents" erlaubt ist.
- Der Refund Manager zeigt nun konkrete Fehlertexte in den Alerts, sofern ein Fehler passiert.
- Unabsichtliche Leerzeichen in der Anrede bei einer Adresse werden nun herausgefiltert. Dies führte zu Problemen bei Bestellungen.
- Neue Debug Logeinträge für sämtliche Änderungen von Zahlstatus und Bestellstatus (Order State Management).
- Apple Pay Logeinträge werden nun nur mehr gemacht, wenn Apple Pay auch aktiv ist. Diese wurden aus Versehen immer erstellt.
- Apple Pay unterstützt keinen Firmennamen. Deshalb wird nun auch bei einer Zahlung mit Apple Pay Direct ein im Account hinterlegter Firmennamen entfernt, da hier stets die Adresse von Apple Pay genommen werden sollte.

### Fehlerbehebungen
- Behebung von kaputten Textbausteinen für Flow Builder Triggers seit Shopware 6.4.18.
- Behebung einer falschen Rundungsanzeige von "Versand" Betrags-Werten in der Administration.
- Behebung des seltenen Problems "Struct::assign() must be type of array" während eines Checkouts.

## [3.3.0] - 2022-11-09
### Verbesserungen
- Der Refund Manager unterstützt nun auch Promotions die sich auf Lieferkosten beziehen.
- Die Einstellung, dass Kunden bei Mollie erzeugt werden, ist nun für neue Installationen im Standard inaktiv.

### Fehlerbehebungen
- Behebung eines Crashes in Kombination mit anderen Zahlungsanbieter-Plugins (Attempted to load class HandleIdentifier and Constant).
- Behebung eines Problems im Refund Manager wo es bei LineItem-basierten Refunds nicht möglich war, den finalen Betrag erneut individuell zu überschreiben.
- Behebung eines Rechtschreibfehlers im Order-State Mapping bei den Plugin Einstellungen.

## [3.2.0] - 2022-10-13
### Features
- SEPA Lastschrift wurde entfernt. Diese ist nicht mehr für normale und initiale Zahlungen möglich.

### Verbesserungen
- Bei Abonnements wurde in der Storefront das Dropdown für das Land beim Editieren der Adresse entfernt, da dies nicht geändert werden kann, und darf.
- Abonnement Formulare in der Storefront unterstützen nun auch den CSRF Modus "Ajax" von Shopware.
- Kleinere Optimierungen für unsere Debug-Logs.

### Fehlerbehebungen
- Behebung des Problems beim Öffnen von Bestellungen in der Administration, die mit AMEX Kreditkarten bezahlt wurden. Aufgrund eines Fehlers durch die Anzeige des Logos der Karte, konnte die Bestellung nicht geöffnet werden.
- Behebung eines Problems in der Storefront mit einem kaputten Link bei der Aktualisierung der Zahlungsmethoden von laufenden Abonnements.
- Hinzufügen eines fehlenden deutschen Textbausteins für eine Fehleranzeige bei Abonnements im Warenkorb ("..nicht alle Zahlungsmethoden verfügbar...").

## [3.1.0] - 2022-09-29
### Verbesserungen
- Die Custom-Fields einer Shopware Bestellungen werden nun auch via Webhooks mit Mollie-Daten angereichert, sofern der Kunde nicht auf die Finish-Seite zurückkommt.
- Die klickbaren Links innerhalb der Plugin Konfiguration wurden nun auch für Shopware Versionen <= 6.3 umgesetzt.

### Fehlerbehebungen
- Behebung des Problems, dass Webhook-Aktualisierungen von bestehenden Abonnementzahlungen womöglich zu neuen Bestellungen in Shopware führten.
- Behebung von abweichenden Bestellzeiten in E-Mails (UTC Zeiten), sofern Bestellbestätigungs-Emails durch die Kombination von Flow Builder + Webhooks angestoßen werden.
- Behebung eines seltenen Fehlers "Customer ID is invalid when creating an order".

## [3.0.0] - 2022-09-12
### (Mögliche) Breaking Changes
- Die neue Version 3.0 bietet eine offizielle Unterstützung für "Headless" Shops an. Mit Hilfe der "automatischen Routen-Erkennung" haben wir versucht "Breaking Changes" für neue und alte Zahlungen zu vermeiden. Sollte doch ein Problem auftauchen, haben wir hier eine entsprechende Anleitung: https://github.com/mollie/Shopware6/wiki/Headless-setup-in-Shopware-6

### Features
- Unterstützung für "Headless" Systeme.
- Out-of-the-Box Unterstützung für die Shopware PWA.
- Anzeige von (anonymen) Kreditkartendaten bei einer Bestellung innerhalb der Administration (für neue Bestellungen).
- Abonnement-Feature kann nun auch deaktiviert werden, wenn nicht benötigt.
- Neue Funktion um fehlgeschlagene Abonnement Erneuerungen zu ignorieren, damit nur für valide Zahlungen eine neue Bestellung in Shopware angelegt wird.

### Verbesserungen
- Buttons im Refund-Manager zeigen nun einen Fortschritt, wenn ein Refund etwas länger dauert.

### Fehlerbehebungen
- Behebung eines NULL Problems in OrderLineItemAttributes, dass in wenigen Shops vorkommen konnte.

## [2.5.0] - 2022-08-29
### Verbesserungen
- Alle Mollie Flow Builder Events unterstützen nun die Verwendung von E-Mail Actions.
- Rückerstattungen im Refund Manager können nun mit mehr Positionen als zuvor erstellt werden. Aufgrund einer Limitierung auf Seite von Mollie werden die Daten nun intern komprimiert und somit reduziert.

### Fehlerbehebungen
- Mollie Abonnements werden nun erst mit dem nächsten Intervall gestartet, um eine initiale Doppelbuchung zu vermeiden.

## [2.4.0] - 2022-08-10
### Features
- Der Refund Manager kann nun in den Plugin Einstellungen deaktiviert werden. Somit kann verhindert werden, dass Mitarbeiter diesen benutzen, wenn ein anderes System für Rückerstattungen zuständig ist.

### Verbesserungen
- Die Auswahl von iDEAL Banken im Checkout ist nun verpflichtend. Dadurch kann der Kunde dies nicht mehr vergessen, und der Checkout Prozess auf der Mollie Zahlungsseite wird somit um 1 Schritt reduziert.
- Das Shopware Standardverhalten für fehlerhafte Zahlungen ist bei erstmaliger Installation des Plugins nun standardmäßig aktiviert.

### Fehlerbehebungen
- Beim Erstellen von Abonnements wurde nicht explizit das Mandat der initialen Zahlung verwendet. Hat der Kunde bereits mehrere Mandate, kann es sein, dass das falsche Mandat für die Zahlung von Mollie benutzt wurde.
- Das Feld "Additional" in der Adresse wird nun nicht mehr unabsichtlich an Mollie geschickt, wenn sich nur Leerzeichen darin befinden. Dies führte zu einem Problem beim Erstellen der Zahlung.
- Behebung von Warning-Ausgaben bei "CLI Kommandos" in Kombination mit dem PSR Logger, Shopware 6.4.10.1 und PHP 8.0

## [2.3.1] - 2022-08-01
### Fehlerbehebungen
- Behebung des Problems von MailTemplates Fehlern bei Installation/Update des Plugins in Kombination mit einem Shop der als Standardsprache nur DE hat
- Behebung von Problemen beim internen Laden von LineItems wenn die CustomFields leer sind (NULL)

## [2.3.0] - 2022-07-13
### (Mögliche) Breaking Changes
Diese Version bietet Unterstützung für die Massenbearbeitung von Produkten in der Administration. Aufgrund interner Änderungen überprüfen Sie bitte nach dem Update die konfigurierten "Gutschein Typen" Ihrer Produkte. Es sollte kein Problem geben, aber überprüfen Sie bitte, ob diese Einstellungen noch vorhanden sind, oder legen Sie diese erneut fest.

### Features
- Brandneue Unterstützung für Abonnements in Shopware. Konfigurieren Sie Produkte und verkaufen Sie diese basierend auf täglichen, wöchentlichen oder monatlichen Abonnements.  
  [Mehr Infos hier](https://github.com/mollie/Shopware6/wiki/Subscription)
- Erweiterung der Status-Mappings für Rückerstattungen und Teilrückerstattungen.
- Neue Zahlungsmethode: in3
- Neue Zahlungsmethode: SEPA-Lastschrift

### Verbesserungen
- Mollie-Produkteigenschaften sind jetzt bei der Massenbearbeitung in der Administration verfügbar.
- Die Detailansicht einer Bestellung in der Administration zeigt nun die Paypal- bzw. SEPA-Referenznummer.
- Der Refund Manager zeigt nun die Gesamtsumme mit Steuern und ohne Steuern.

### Fehlerbehebungen
- Behebung eines seltenen Fehlers, bei dem eine abgelaufene Bestellung innerhalb der Storefront nicht erneut bezahlt werden konnte.

## [2.2.2] - 2022-05-11
### Features
- Mit der neuen Einstellung "Finaler Bestellstatus" könnt ihr einen gewählten Status als finalen Status fixieren. Ab diesem Zeitpunkt wird nur mehr im Fall von Rückerstattungen und Rückbuchungen eine Änderung des Status durchgeführt. Dieses Feature hilft besonders in Kombination mit Logistik Plugins und von Mollie abweichenden Logistik-Abläufen.

### Verbesserungen
- Der Zahlstatus "abgelaufen" führt nun zu einem Bestellstatus "abgebrochen" anstatt von "fehlerhaft". Dies entspricht mehr der Realität.
- Unterstützung der Kompatibilität für das Plugin "Artikelnummer direkt per URL aufrufen".

### Fehlerbehebungen
- Das Plugin ignoriert ab sofort Webhook-Aktualisierungen von Bestellungen, die mit Mollie gestartet wurden, aber letztendlich nicht mit Mollie abgeschlossen wurden.
- Behebung eines Problems beim Bestellabschluss in Kombination mit gemischten Steuersätzen, Rabattcodes und der Verwendung einer Netto-Preis Kundengruppe.

## [2.2.1] - 2022-04-27
### Features
- Wir haben das brandneue "Smart Kontaktformular" im Bereich der Plugin Konfigurationen hinzugefügt. Bei Verwendung dieses Formulars für den Support erhalten wir automatisch alle notwendigen Informationen, um noch besser zu unterstützen.

### Verbesserungen
- Verbesserung und Fehlerbehebung von Bestellungen mit verschiedenen Steuersätzen und Rabatten. Durch technische Gegebenheiten werden Rabatte in Mollie aktuell als 1 Bestellposition gespeichert. Da allerdings nur 1 Steuersatz bei einer Position möglich ist, wird für diese Art von Bestellung nun ein Misch-Steuersatz berechnet, um die Bestellung zumindest abschließen zu können.

### Fehlerbehebungen
- Behebung des Problems bei dem der Refund Manager nicht den korrekten API Schlüssel des Verkaufskanals genommen hat. Es werden nun immer die Konfigurationen des Verkaufskanals der Bestellung benutzt.
- Behebung des Problems bei dem die Mollie Limits unbeabsichtigt Einfluss auf Zahlungsarten im Account sowie Logos im Footer genommen haben. Bitte nach Update zusätzlich den Cache löschen.

## [2.2.0] - 2022-03-23
### Features
- Veröffentlichung des brandneuen "Refund Manager" in der Administration und für die API. Mit dem Refund Manager könnt ihr ganze Retouren-Prozesse inklusive Transaktionen, Lagerbeständen und Flow Builder Events in einer intuitiven Oberfläche bedienen.
- Neue Apple Pay Anzeige-Einschränkungen in der Plugin Konfiguration erlauben euch die Apple Pay Direct Buttons einfach und ohne Programmierung auf verschiedenen Seiten auszublenden.
- Neues `MollieOrderBuilder` Event, um eigene Metadaten zu einer Bestellung hinzufügen zu können (Feature für Entwickler).

### Verbesserungen
- Wichtige Änderung und Fehlerbehebungen für Order Transaktionen in Shopware. Wenn ein Kunde zusätzliche Zahlungsversuche durchführt, nachdem der erste Versuch scheiterte, kam es manchmal dazu, dass die Anzeige der Statuseinträge in Administration und API nicht mehr passten. Mollie benutzt nun stets die aktuellste Transaktion in Shopware und fügt sämtliche Aktualisierungen dieser hinzu, um alle Daten konsistent zu halten.
- SEPA Zahlungen bleiben nun auf "In Progress", wenn diese gestartet wurden, und springen nicht mehr zurück auf "Open".
- Zahlungen mit Status "Created" werden nun als "fehlerhaft" erkannt.
- Kreditkartenzahlungen mit Status "Open" werden nun als "fehlerhaft" erkannt.
- Apple Pay Direct benutzt nun die korrekte Shopware Kundennummern-Kreise beim Erstellen von Gäste-Accounts.
- Javascript Code von Apple Pay Direct wurde angepasst, damit im Internet Explorer keine Fehler mehr kommen.
- jQuery wurde entfernt als Vorbereitung auf Shopware 6.5.
- Verbesserung der Performance für Erkennung der "Gutschein Verfügbarkeit" in Kombination mit vielen Produkten.
- ING'Homepay wird nun immer deaktiviert beim Aktualisieren der Zahlungsarten. Diese Zahlungsart ist seit einiger Zeit nicht mehr bei Mollie verfügbar.
- Entfernung der automatischen Aktivierung von Zahlungsarten im Mollie Dashboard beim Installieren des Plugins.

### Fehlerbehebungen
- Behebung des Problems, dass manche Daten bei Zahlungsarten (Bilder, Name, ...) bei Aktualisierung überschrieben wurden.
- Behebung des Problems, dass Zahlungsarten doppelt erstellt wurden. (Bereits existierende duplizierte Zahlungsarten werden hier nicht behoben).
- Behebung des Problems mit dem IPAnonymizer in Kombination mit IPv6 Adressen innerhalb des Logger Modules.
- Behebung des kaputten "Zahlungsarten aktualisieren" Button in der Plugin Konfiguration in älteren Shopware Versionen.
- Behebung eines seltenen Fehlers, bei dem `checkSystemConfigChange()` im Mollie Plugin zu einem Fehler führte.
- Behebung eines Problems, bei dem ein Versand bzw. Bearbeiten einer Bestellung in der Administration mit fehlenden Daten nicht möglich war (`Cannot read a.shippedQuantity`).
- Behebung eines sehr seltenen Problems mit einem Fehler verursacht durch das "Mollie Limits" Feature.
- Behebung von Javascript Fehlern, wenn iDEAL dem Verkaufskanal zugewiesen wurde, aber Mollie nicht korrekt konfiguriert war.

## [2.1.2] - 2022-03-16
Diese Version bringt nur die Unterstützung für die aktuellste Shopware Version 6.4.9.0.  
Die vorherige Version sollte nie dafür freigegeben worden sein. Der Shopware Store hat die neue Shopware-Version leider automatisch freigegeben. Dies wurde natürlich für die Zukunft deaktiviert.  
Wir entschuldigen uns für etwaige Unannehmlichkeiten.

## [2.1.1] - 2022-02-16
### Verbesserungen
- Aufgrund eines Bugs (NEXT-20128) in der aktuellen Shopware Version 6.4.8.x wirft das Mollie Plugin auf jeder Seite einen "HTML element not found" Javascript Fehler. Da wir nur das Beste für euch wollen, gibt es nun eine Anpassung, die diesen Fehler verhindert.

## [2.1.0] - 2022-02-15
### Features
- Die neue Konfiguration "Mollie Zahlungsarten Limits" hilft euch dabei, automatisch alle Zahlungsarten auszublenden, die laut Mollie nicht für den aktuellen Warenkorbwert zulässig sind. Ihr könnt weiterhin eure Verfügbarkeitsregeln via Rule Builder benutzen, und zusätzlich optional die Mollie Limits verwenden.
- Wir präsentieren euch die neue Plugin Konfiguration. Besseres Onboarding, bessere Strukturierung und mehr Hilfstexte unterstützen bei einer noch einfacheren Konfiguration des Plugins.

### Verbesserungen
- Apple Pay Direct Zahlungen bekommen nun auch zusätzliche Informationen wie Mollie-ID, etc. in den "Custom Fields" der Shopware Bestellungen.
- Vermeidung von Javascript Fehleranzeigen in der Konsole im Bereich der Versandfunktion einer Bestellung in der Administration.

### Fehlerbehebungen
- SEPA Zahlungen mit Status "offen" führen nun zu einer erfolgreichen Bestellung.
- PayPal Zahlungen mit Status "wartend" führen nun zu einer erfolgreichen Bestellung.

## [2.0.0] - 2022-01-31
Willkommen bei MolliePayments v2.0! 🎉  
Wir hoffen, dass Ihnen die vielen neuen Funktionen, Updates und Korrekturen gefallen.  
Schön, Sie als unseren Kunden zu haben :)

### Grundlegende Änderungen
Aufgrund des Flow Builders mussten wir die fest integrierten Versand/Erstattungen, die bei Statusübergängen erfolgten, entfernen.  
Aber keine Sorge, es gibt eine neue Funktion **"Automatischer Versand"**, die standardmäßig aktiviert ist und dafür sorgt, dass es nach dem Update auf v2.0.0 genauso funktioniert.

### Features
- Brandneue Flow Builder-Integration: Verarbeiten Sie eingehende Mollie-Webhooks oder lösen Sie Sendungen und Erstattungen automatisch aus.
- Neue Funktion **"Automatischer Versand"**, die ausgeschaltet werden kann, wenn Sie mit anderen Funktionen wie Flow Builder versenden möchten.
- Die Sendungsverfolgung ist jetzt in der Administration verfügbar.
- Teillieferungen für Positionen sind nun wieder in der Verwaltung möglich.
- Neue Mollie-Aktionsschaltflächen für Versand und Erstattungen in der Administration.
- Der Zahlungsstatus "Rückbuchung / Chargeback" wird jetzt im Plugin unterstützt.
- Rückerstattungen können jetzt über die Shopware API durchgeführt werden.
- Neues Logging-System (Logs im Dateisystem bei den Shopware Logs).
- Neue Schaltfläche zum Aktualisieren von Zahlungsmethoden in der Plugin-Konfiguration.
- Neuer CLI-Befehl, um die Apple Pay Domain Verification Datei zu aktualisieren.
- Mollie-Informationen (Bestell-ID, Transaktions-ID, PayPal-Referenz, SEPA-Referenz) werden nun in den CustomFields gespeichert.
- Apple Pay Direct wird jetzt auch auf CMS-Produktseiten unterstützt.

### Verbesserungen
- Hinzufügen von Twig-Blöcken zu Apple Pay Direct Buttons.
- Verbesserung der Margins von Apple Pay Direct Buttons.
- Das `lastUpdated` der Bestellung wird nun auch bei eingehenden Zahlungsstatusänderungen aktualisiert.
- Das Plugin installiert bei Updates automatisch neue Zahlungsarten.

### Fehlerbehebungen
- Behebung der Kreditkarten-Komponenten im Internet Explorer.
- Behebung der Kreditkarten-Komponenten auf der Seite "Bestellung bearbeiten" nach einer fehlgeschlagenen Zahlung.
- Behebung eines Kompatibilitätsproblems mit dem offiziellen Klarna-Plugin.
- Behebung eines Problems mit dem Checkout in Shopware 6.3.3.1.
- Behebung eines falschen Routers im `PaymentController`.
- Behebung des Problems der doppelten Zahlungsmethoden nach der Umbenennung und Aktualisierung des Plugins.

## [1.5.8] - 2021-12-14
### Verbesserungen
- Kompatibilität zu EasyCoupon Plugin von Net Inventors GmbH

## [1.5.7] - 2021-11-15
### Features
- Neue Zahlungsmethode "Klarna Pay Now" ist nun verfügbar

### Verbesserungen
- Apple Pay Direct Formulare werden nun nicht mehr in Storefront geladen, wenn Apple Pay Direct nicht aktiv ist

## [1.5.6] - 2021-11-08
### Verbesserungen
- Fehlerhafte Zahlungen bekommen nun den Zahlungsstatus "fehlgeschlagen" und nicht mehr "abgebrochen"
- Mollie ist nun kompatibel mit dem Plugin "ACRIS Checkout Shipping Payment Preselection"
- Mollie ist nun kompatibel mit dem Plugin "Custom Products"

### Fehlerbehebungen
- Behebung eines seltenen Rundungsfehlers in Kombination mit Netto-Shops sowie vertikaler Steuerberechnung
- Problembehebung eines Javascript Fehlers, welcher die Anzeige von Bestellpositionen in der Administration verhindert

## [1.5.5] - 2021-10-27
### Features
- Neue Zahlungsmethode "Gutschein" verfügbar. Konfigurieren Sie Artikel als Öko-, Mahlzeit- oder Geschenkgutschein und lassen Sie Ihre Kunden mit unterstützten Gutschein-Systemen einkaufen.
- Neue Shopware API Routen für den Versand von Bestellungen. Verwenden Sie diese einfachen Routen für Integrationen von ERP Systemen und anderen.

### Verbesserungen
- Timeout für die Kommunikation mit Mollie wurde nun erhöht, um auch in Spitzenzeiten stabile Zahlungen anbieten zu können
- API Keys werden nun als Passwort-Feld in der Administration angezeigt
- Optimierung von Plugin-Kompatibilitäten durch Verwendung des RouterInterfaces statt des Routers

### Fehlerbehebungen
- Nicht unterstützte Apple Pay Karten wie EMV wurden nun von Apple Pay Direct entfernt
- Bei Individualisierung des Checkouts kam es unter Umständen zu Javascript-Problemen durch die Kreditkarten-Komponenten. Diese wurden nun abgefangen

## [1.5.4] - 2021-09-15
### Features
- Das Feature "Kunden in Mollie erstellen" ist zurück und funktionsfähig für Multi-Sales-Channel Setups und Test-, sowie Live-Modus. Sofern aktiviert, werden in Mollie Kundeneinträge erstellt und mit Bestellungen und Zahlungen verknüpft.

### Verbesserungen
- Komplette Überarbeitung von Apple Pay Direct für bessere Stabilität, Funktionsfähigkeit und Performance
- Apple Pay Direct verwendet nun Shopware-Kundeneinträge wieder, sofern man im Shop angemeldet ist
- Apple Pay Direct funktioniert nun auch in älteren Shopware-Versionen 6.1.x

### Fehlerbehebungen
- Behebung von Weiterleitungsproblemen auf die Mollie-Zahlungsseite bei erneutem Versuch nach einer fehlerhaften Zahlung (führte in Shopware 6.4.3.1 zu einem NOT_FOUND Fehler)
- Behebungen von Problemen wie verlorenen Sessions, Warenkörben oder verschwundenen Discounts in Kombination mit verschiedenen Sales Channels durch falsche Apple Pay ID Prüfungen im Hintergrund
- Behebung eines Problems beim Logging von Daten mit falschen Parametern – führte in manchen Situationen zu einem Fehler im Checkout
- Angabe der optionalen `MOLLIE_SHOP_DOMAIN` Variable für eigene Webhook-URLs funktioniert nun wieder
- Behebung des Fehlers "PROMOTION_LINE_ITEM Not Found" in älteren Shopware 6.1.x Versionen
- Allgemeine Fehlerbehebungen im Checkout in älteren Shopware 6.1.x Versionen

## [1.5.3] - 2021-08-11
### Fehlerbehebungen
- Symfony-Registrierungsfehler bei Apple Pay Direct behoben
- Standardwerte in der Plugin-Config korrigiert, wenn das Plugin neu installiert wird (Verhinderung falscher Anzeige von Live-/Testmodus)

## [1.5.2] - 2021-08-05
### Refactoring
- Code-Verbesserungen beim Ändern von Zahlungsübergängen

### Fehlerbehebungen
- Fehler bei der Eingabe falscher Kreditkarteninformationen behoben (Bezahlvorgang blockiert nicht mehr)
- Fehler behoben, der das Bezahlen verhinderte, wenn Kunden eine Promotion eingelöst haben
- Router im MollieOrderBuilder auf Shopware Router anstelle von Symfony Router geändert

## [1.5.1] - 2021-07-21
### Fehlerbehebungen
- Versandkosten wurden bei Übertragung an Mollie nicht beachtet

## [1.5.0] - 2021-07-21
### Features
- Vollständige Unterstützung von Teilrückerstattungen (tragen Sie einfach den gewünschten Rückerstattungsbetrag in der Administration ein und erstellen Sie eine neue Rückerstattung direkt bei Mollie)

### Refactoring
- PaymentHandler komplett überarbeitet für bessere Codestabilität
- Neuer Transition-Service für Order Payments hinzugefügt
- Mollie-Bestellungen werden nun wiederverwendet – im Falle stornierter oder fehlgeschlagener Zahlungen werden keine neuen Mollie-Bestellungen erstellt
- Mollie Payments werden nach Möglichkeit wiederverwendet; falls nicht möglich, wird eine neue Zahlung erstellt (wenn die vorherige fehlgeschlagen oder storniert wurde)

### Fehlerbehebungen
- Bug behoben, der verhinderte, dass Shipping Transitions bei Mollie gemeldet wurden

## [1.4.3] - 2021-07-07
### Fehlerbehebungen
- Fix für Backwards-Compatibility

## [1.4.2] - 2021-07-06
### Fehlerbehebungen
- Verifizierungsprozess der Domainüberprüfung für Apple Pay Direct angepasst
- Webhook-Benachrichtigungen überarbeitet – manche Bestell- und Bezahlstatus wurden nicht korrekt erkannt
- Apple Pay wird im Storefront nicht als Zahlungsmethode angezeigt, wenn Browser oder Gerät Apple Pay nicht unterstützen
- iDeal Dropdown-Menü in Shopware 6.4 Templates hinzugefügt
- Fehler behoben, der den Wechsel des Bestellstatus in mehrsprachigen Shops verhinderte
- Fehler bei Shopware-Versionen > 6.4 mit der return-URL behoben (lange URLs wurden gekürzt)

### Features
- Link zum Entwicklerbereich des Mollie-Dashboards in der Administrationskonfiguration hinzugefügt
- Mollie Zahlungs-URL zu Bestellungen im Shopware-Backend hinzugefügt
- Bei aktiviertem Testmodus in der Administration erhalten Zahlungsmethoden in der Storefront den Zusatz "Testmodus"

## [1.4.1] - 2021-05-17
### Fehlerbehebungen
- Shopware Payment Status wird auf „bezahlt“ gesetzt, sobald Mollie den Bezahltstatus bei Klarna-Bestellungen von `authorized` auf `completed` stellt

## [1.4.0] - 2021-05-06
### Features
- Plugin ist jetzt kompatibel mit Shopware 6.4
- „Create customer at Mollie“-Feature deaktiviert und aus Administration entfernt

### Fehlerbehebungen
- Kreditkarten-Komponenten return URL gefixt (Dank an fjbender fürs Finden und Beheben des Bugs)

### Hinweis
- Falls das neue Shopware-Währungsrunden-Feature (auf Total Sum) benutzt wird, berechnen wir den Auf-/Abschlag genau wie Shopware mit 0% Steuern

## [1.3.16] - 2021-04-22
### Fehlerbehebungen
- Bug behoben, der das Editieren einer Bestellung in der Administration verhindert hat

## [1.3.15] - 2021-04-21
### Fehlerbehebungen
- Die Webhook-URL wurde zusätzlich an anderen Stellen zu den an Mollie zu übertragenen Daten hinzugefügt, sobald eine Bestellung platziert wird. In manchen Konstellationen konnte dies zu einem dauerhaften Zahlungsstatus „Verarbeitung“ in Shopware führen
- Ein Fehler wurde behoben, wenn die Zahlung in der Administration auf "Rückerstattung" gesetzt wurde. Der Fehler trat nur in Shopkonfigurationen mit mehreren Vertriebskanälen und unterschiedlichen Konfigurationen auf
- Benutzerdefinierte Parameter für Zahlart Banküberweisungen aktualisiert
- Verhaltensoptimierung des Plugins, wenn Änderung des Bestellstatus in der Konfiguration ausgewählt wurde
- Fehlerhafte Behandlung der Funktion „Kunden bei Mollie nicht erstellen“ behoben
- Fehlerhafte Fehlerbehandlung im Checkout behoben

## [1.3.14] - 2021-03-15
### Fehlerbehebungen & Verbesserungen
- Bugs wurden gefixet und Funktionalität der Versandnachricht an die Mollie Api wurde verbessert

## [1.3.13] - 2021-02-24
### Fehlerbehebungen
- Javascript Bug behoben in creditcard-components, der eine Kaufabwicklung verhindern konnte
- Wenn die Betsellung verfällt nach Konfiguration nicht gesetzt wird, wird Mollie überlassen wann Bezahlung verfällt (in der Regel nach 28 Tagen)
- Browser zurück Button Verhalten überarbeitet, wenn nach einer bereits fehlgeschlagenen Bezahlung auf der Mollie Seite der zurück button gedrückt wurde

## [1.3.12] - 2021-02-15
### Verbesserungen
- Verbesserte Übersetzungen für Deutsch und Niederländisch (MOL-137)

### Fehlerbehebungen
- Auf Grund unterschiedlicher Steuerberechnungen zwischen Shopware und Mollie API, konnte in Sonderfällen bei manchen Bestellungsübermittlung an die Zahlseite von Mollie zu Fehlern kommen. Dadurch war es Kunden nicht möglich zu bezahlen. Das Problem wurde behoben (MOL-142)
- War in der Mollie Konfiguration eingestellt, dass das Shopware Standard Verhalten bei fehlgeschlagenen Zahlungen verwendet werden soll konnte es zu folgendem Fehler kommen. Wenn Bezahlungen auf den Mollie Seiten nicht erfolgreich waren, gelangte ein Kunde statt auf die Fehlerseite auf die "Bestellung erfolgreich" Seite. Das Problem wurde behoben (MOL-140)

## [1.3.11] - 2021-01-25
### Fehlerbehebungen
- Fixes an issue where cancelled Klarna orders could not be retried when using the Mollie redirect page
- Fixes an issue where an incorrect router object was being used

## [1.3.10] - 2021-01-14
### Fehlerbehebungen
- Fixes an error thrown when cancelling an order through the administration in Shopware 6.2.x and older
- Fixes an issue where the incorrect API key was being used

## [1.3.9] - 2020-12-28
### Fehlerbehebungen
- Resolves an issue where orders would not be getting the configured state when authorizing payment with Klarna, after a previous payment attempt for this order had been cancelled, using the Mollie redirect feature
- Klarna orders that are cancelled through the Shopware administration will now also get cancelled in Mollie dashboard
- Fixes the "Standard failed payment redirect" from not using the correct Shopware routing
- New installations will now load svg payment icons. Existing installations will keep using the png variants, until they are deleted from the media library and the plugin is reactived

## [1.3.8] - 2020-12-16
### Fehlerbehebungen
- Es wurde behoben, dass verschlüsselte Urls nicht mehr von der API akzeptiert wurden
- API-Client auf 2.27.1 aktualisiert

## [1.3.7] - 2020-12-07
### Features
- Option hinzugefügt, um Apple Pay Direct zu deaktivieren, wenn Apple Pay als Zahlungsmethode verfügbar ist

### Fehlerbehebungen
- Verbesserte Kompatibilität mit der Paypal-Integration von Shopware
- Ein Problem wurde behoben, bei dem die falsche Zahlungsmethode in der Verwaltung angezeigt wurde, wenn in Mollie eine andere Zahlungsmethode ausgewählt wurde
- Mehrere kleinere Fehler behoben

## [1.3.6] - 2020-11-13
### Fehlerbehebungen
- Mehrere Probleme bei der Ausführung von Apple Pay Direct-Bestellungen behoben
- Ein Inkompatibilitätsproblem mit benutzerdefinierten Produkten wurde behoben
- Fehlerbehebung bei Problemen mit mehrwertsteuerbezogener Preisrundung
- Fix für einen illegalen Rückgabetyp

## [1.3.4] - 2020-10-30
### Fehlerbehebungen
- Mollie locale fix for creditcards components
- Order state automation on failed payments fix
- General error handling for issue creating documents in the backend

## [1.3.2] - 2020-10-26
### Fehlerbehebungen
- Bugfix with order states not working on failed payments

## [1.3.1] - 2020-10-21
### Fehlerbehebungen
- Small error fixed with customer registration and headless storefronts

## [1.3.0] - 2020-10-19
### Features
- Added order state automation for Authorized orders (Klarna)
- Added the option to toggle single click payments on and off

### Fehlerbehebungen
- Fixed redirection error on certain sales channels after failed payments
- Fixed VAT calculation errors on certain customer groups

## [1.2.3] - 2020-10-09
### Features
- Added "Authorized" status in order overview for Klarna
- Added Single Click Payments for second time credit card payment users

## [1.2.2] - 2020-09-28
### Fehlerbehebungen
- Es wurde ein Fehler behoben, durch den der Transaktionsstatus von Klarna-Bestellungen nicht auf bezahlt aktualisiert wurde

## [1.2.1] - 2020-09-17
### Features
- Option zum Umschalten hinzugefügt, um standard Shopware oder Mollie Zahlungsumleitungen auszuwählen

## [1.2.01] - 2020-09-07
### Features
- Added Apple Pay Direct on the product and listing page

## [1.0.19] - 2020-08-04
### Features
- Added the Mollie Order ID to the order detail in the administration
- Added the preferred iDeal issuer to the customer detail in the administration
- Added order state automation

### Fehlerbehebungen
- Fixed a bug with product URLs in the request to Mollie
- Fixed issues with the payment state staying in progress after failed payments

## [1.0.18] - 2020-07-29
### Fehlerbehebungen
- Fixed an issue where custom products would cause an error

## [1.0.17] - 2020-07-23
### Features
- An event is triggered when the payment failed or passed, other plugins can act on this event
- SKU number of line items is available in the Mollie Dashboard

### Fehlerbehebungen
- Multiple VAT rates in the basket no longer cause an exception
- API test-button only appears within Mollie's configuration

## [1.0.16] - 2020-07-08
### Features
- Test-button in the plugin configuration to validate the API keys

### Fehlerbehebungen
- Mollie Components is now a javascript plugin and works in live mode

## [1.0.15] - 2020-06-25
### Fehlerbehebungen
- Fixed an issue where changing the delivery status would cause an exception
- Fixed an issue where Apple Pay would show up on not-supported devices

## [1.0.14] - 2020-06-15
### Fehlerbehebungen
- Fixed an issue where the vat amount on orders for net free customers was off
- Fixed an issue where Mollie Components wasn't compatible with Shopware 6.1.5

### Features
- The debug mode now indicates whether a payment is in live or test mode

## [1.0.13] - 2020-05-28
### Hotfix
- Reversed a fix for customers with display of net prices, as it had the unwanted side effect of customers paying the net price

## [1.0.12] - 2020-05-28
### Features
- iDeal issuer selection in the checkout
- German translations for Mollie Components
- Mollie Components now works as a Storefront plugin

### Fehlerbehebungen
- API exception with vat free or net orders
- Possible exceptions during checkout or payment retry
- Possible exceptions when transitioning payment states

## [1.0.11] - 2020-05-20
### Features
- The Mollie PHP SDK is updated
- Payment methods are now installed with icons
- Orders can be partially shipped and/or refunded
- Order payment state will always first be set to in progress when payment at Mollie starts
- Failed payments can now be retried

### Fehlerbehebungen
- Fixed an issue where changing the delivery status of an order would cause an exception
- Fixed an issue where order lifetime had the wrong timezone
- Fixed an issue where tax was calculated on orders from tax free countries
- Fixed an issue where credit card components wasn't available on translated payment methods
- Fixed an issue where credit card components couldn't be loaded in a shop with in a subdirectory
- Fixed an issue where the webhook URL wasn't sent to Mollie

## [1.0.10] - 2020-05-05
### Fehlerbehebungen
- Created a bugfix for backwards compatibility of payment states

## [1.0.9] - 2020-05-04
### Fehlerbehebungen
- Fixed payment state transitions in the latest version of Shopware 6 (backwards compatible)

### Features
- Added a debug mode, to gather extra information in the Shopware 6 log in the administration

## [1.0.8] - 2020-05-04
### Fehlerbehebungen
- Webhook URLs are correctly set in production environments
- Configuration has All Sales Channels as fallback data
- Vat amount can be 0.0 (e.g. when an order is tax free)

## [1.0.7] - 2020-04-06
### Fehlerbehebungen
- Fixed issues with multi channel API keys

## [1.0.4] - 2020-03-30
### Features
- Added Mollie Components

### Fehlerbehebungen
- Fixed an issue where API keys couldn't be different for each sales channel

## [1.0.3] - 2020-01-13
### Fehlerbehebungen
- Created fix voor version 6.1+ of Shopware

## [1.0.2] - 2019-11-06
### Fehlerbehebungen
- Fixed activation of the plugin
- Payment methods are now activated in your Mollie dashboard also when they're being activated in the shop

## [1.0.1] - 2019-10-14
### Fehlerbehebungen
- Fixed an issue where the monolog logger service wasn't available during the activate lifecycle
