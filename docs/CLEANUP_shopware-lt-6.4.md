# Cleanup-Report: Entfernung Shopware `< 6.4` Legacy

**Datum:** 2026-07-20
**Kontext:** `composer.json` verlangt bereits `shopware/core >=6.5.8.0 <6.8`. Damit sind alle
reinen `< 6.4`- und `6.4.x`-Codepfade toter Code. Der neue Code unter `/shopware/` war bereits
sauber – alle Fundstellen lagen im Altbereich `/src/`.

> Hinweis: Der Arbeitsstand war zum Zeitpunkt der Analyse **identisch mit dem Upstream
> `mollie/Shopware6` (master, Stand 2026-07-17)**. Mollie selbst hat diesen Legacy-Code noch
> nicht entfernt – es gibt also keine „bereinigte" Referenz zum Abgleichen. Jede Änderung
> wurde einzeln auf ihre Auswirkung für 6.5 / 6.6 / 6.7 geprüft.

**Status:** Umgesetzt, **noch nicht committet**.

---

## Umgesetzte Änderungen

### D — `mollie-pluginconfig-section-info/index.js`
`hasSalesChannelList()` prüfte per `versionCompare` auf `>= 6.4.2`. Da das Minimum jetzt 6.5.8
ist, ist die Bedingung immer wahr.

- `hasSalesChannelList()` → `return true;`
- Damit wird `versionCompare` in dieser Komponente nicht mehr gebraucht → Import, `data`-Feld
  und `created()`-Hook entfernt.

**Warum ungefährlich:** Die Saleschannel-Liste existiert ab 6.4.2 – bei Minimum 6.5.8 also immer.

### F — `mollie-subscriptions-list/index.js`
Der Suchaufbau hatte eine 6.4.4-Weiche (`'addQueryScores' in this`), weil die Admin-Suche erst
ab 6.4.5 `addQueryScores` bereitstellte.

```js
// vorher
if ('addQueryScores' in this) {
    criteria = await this.addQueryScores(this.term, criteria);
} else {
    criteria.setTerm(this.term);
}
// nachher
criteria = await this.addQueryScores(this.term, criteria);
```

**Warum ungefährlich:** `addQueryScores` ist ab 6.5+ immer vorhanden.

### H — `mollie-subscriptions-detail/index.js`
`getFormattedDate()` hatte einen Fallback für Shopware `< 6.4.10` (kein
`dateWithUserTimezone`).

```js
// vorher: if (shopwareObj.Utils.format.dateWithUserTimezone) { ... } return ...date(...);
// nachher
const formattedDate = shopwareObj.Utils.format.dateWithUserTimezone(new Date(date));
return formattedDate.toLocaleDateString() + ' ' + formattedDate.toLocaleTimeString();
```

**Warum ungefährlich:** `dateWithUserTimezone` existiert ab 6.4.10 – bei Minimum 6.5.8 immer.

### A — Buy-Widget Twigs (sauber gelöst, nach CI-Korrektur)
`storefront/page/product-detail/buy-widget-form.html.twig` und
`storefront/component/buy-widget/buy-widget-form.html.twig`.

Beide hatten neben `buyable` (über `product.*`) einen `buyableLegacy`-Zweig (über `page.product.*`),
verbunden per `((buyableLegacy) or (buyable and productPrice) > 0)`. Der `< 6.4`-Kommentar war
**irreführend**: Es gab keine Versionsabfrage, sondern ein stilles ODER zweier **verschiedener
Datenquellen** für dasselbe „ist kaufbar".

Root Cause (per Core-Templates verifiziert):

- Im **page**-Template setzt der Core-Block intern `{% set product = page.product %}`. Dieses `set`
  aus `{{ parent() }}` **leakt in Twig nicht** in den Plugin-Override → dort ist `product` undefined,
  nur `page.product` ist verfügbar.
- Auf **6.5** rendert die PDP noch dieses page-Template. Auf **6.6** ist es „no longer loaded by
  default" (deprecated), auf **6.7** ganz entfernt – dort rendert die PDP das **component**-Template,
  wo `product` im Scope liegt.
- Deshalb war `buyableLegacy` (page.product) auf 6.5 tragend; das erste (zu aggressive) Entfernen
  brach `paypal-express.cy.js` **nur auf 6.5**.

Saubere Lösung statt Fallback-Dualismus – pro Template die **eine** verlässlich vorhandene Referenz:

- page-Template: `{% set mollieProduct = page.product %}`
- component-Template: `{% set mollieProduct = product %}` (dort ist `page` nicht garantiert, z.B. Listing)
- danach einheitlich `buyable`/`productPrice` aus `mollieProduct`, Sichtbarkeit
  `… and buyable and productPrice > 0`.

Twig ist serverseitig → **kein** Storefront-Rebuild nötig. Details zum Hergang im Nachtrag.

### E — `mollie-pluginconfig-support-modal/index.js`
Der injizierte `shopwareExtensionService` hatte `{ default: null }`, weil er „vor 6.4 nicht
existierte". Daran hing ein kompletter Pre-6.4-Zweig.

- `shopwareExtensionService: {}` (ab 6.5 immer vorhanden).
- `isLoading()` und `plugins()` auf den `shopwareExtensionService`-Pfad reduziert.
- `mountedComponent()`: `else`-Zweig (`loadPluginsLegacy()`) entfernt.
- Methode `loadPluginsLegacy()` komplett entfernt – und damit die nur dort genutzten
  Abhängigkeiten: `Criteria`-Import, destrukturiertes `State`, `repositoryFactory`-Inject,
  `isLoadingPlugins`.

**Bewusst behalten:** Die `Shopware.State.get(...) → Shopware.Store.get(...)`-Fallbacks für
`session` und `shopwareExtensions`. Das ist **keine** 6.4-Kompatibilität, sondern die
State→Store-Migration von Shopware **6.6/6.7** – muss bleiben.

### L — `docs/ARCHITECTURE.md`
Verweise auf ein `/polyfill`-Verzeichnis entfernt. Dieses Verzeichnis existiert im Repo nicht
(mehr); die Doku war veraltet.

### R — Rename Storefront-Plugin `creditcard-components-sw64`
Das `sw64` (= Shopware 6.4) im Namen war nur noch irreführend – die alte `< 6.4`-Variante des
Plugins wurde bereits früher entfernt, übrig blieb allein die „sw64"-Version.

- Datei `creditcard-components-sw64.plugin.js` → `creditcard-components.plugin.js` (per
  `git mv`, Historie bleibt erhalten).
- Klasse `MollieCreditCardComponentsSw64` → `MollieCreditCardComponents`.
- In `register.js`: Import, Registrierungsname und Klassensymbol angepasst.

### C — `sw64`-Flag entfernt (`cc-fields.html.twig`, `payment-method.html.twig`, `register.js`)
Das `sw64`-Flag war ab 6.5 immer `true`; der `else`-Zweig war toter `< 6.4`-Code. `cc-fields`
wird ausschließlich von `payment-method` inkludiert (immer mit `sw64: true`), daher konnte der
Zweig gefahrlos raus.

- `payment-method.html.twig`: `sw64: true` entfernt (das `with {}` fiel damit weg) + der
  veraltete Kommentar `{# compatible with >= sw6.4 #}`.
- `cc-fields.html.twig`: `{% if sw64 %}`-Weichen an allen drei Stellen entfernt → feste,
  saubere Namen: id `mollie_components_credit_card`, Template-Attribut
  `data-mollie-template-creditcard-components`, Options-Attribut
  `data-mollie-credit-card-components-options`.
- `register.js`: Selektor auf `[data-mollie-template-creditcard-components]` angeglichen.

**Wichtiger Zusammenhang mit R (Lern-Notiz):** Shopware liest die Plugin-Optionen aus einem
Attribut namens `data-<kebab(pluginName)>-options`. Durch den Rename auf
`MollieCreditCardComponents` erwartet Shopware jetzt `data-mollie-credit-card-components-options`.
Hätte man nur das Flag „aufgehübscht" und das Attribut auf `…-sw64-options` gelassen, wären
`profileId`, `locale`, `testMode` usw. **nicht mehr angekommen** und die Kreditkarten-Komponente
wäre still kaputtgegangen. R und C gehören daher zusammen.

> **Build erforderlich:** R und C fassen Storefront-Quellcode an. Das aktuell eingecheckte
> Bundle bindet noch auf den alten Selektor `…-sw64`. Vor Test/Merge einmal `make build`
> (Storefront) ausführen – erst danach passt das kompilierte JS zum neuen Selektor, und die
> dann entstehenden `dist/storefront/js/...`-Änderungen gehören mitcommittet.

### I — Cypress `< 6.4`-Bereinigung (Test-Code)
Alle toten `6.4`-Versionsweichen in der Cypress-Suite entfernt; `6.5`/`6.6`/`6.7`-Weichen
bleiben (die unterscheiden weiterhin unterstützte Versionen).

- `PaymentAction.js`: Methoden `openPaymentsModal()` (nur `< 6.4`) und `closePaymentsModal()`
  (ab 6.4 ein No-Op) entfernt; `showPaymentMethods()` auf den `>= 6.4`-Pfad reduziert (6.7-Guard
  bleibt); `switchPaymentMethod()` ohne den `closePaymentsModal()`-Aufruf. Dadurch ungenutzt und
  ebenfalls entfernt: `PaymentsRepository`-Import + `repoPayments`.
- `AdminPluginAction.js`: `openPluginConfiguration()` fest auf die Extension-Config-Route
  (`sw/extension/config`), der alte `sw/plugin/settings`-Zweig raus.
- `creditcard.cy.js`: fünf `if (isVersionGreaterEqual(6.4)) {…} else { closePaymentsModal }`
  auf den `>=`-Zweig reduziert, zwei `isVersionLower(6.4)`-Close-Blöcke, fünf
  `isVersionEqual('6.4.0.0')`-Skips (Shopware-Bug NEXT-15044) und zwei lose
  `closePaymentsModal()`-Aufrufe entfernt.
- `checkout-failed.cy.js`: zwei `6.4.10.0`-Weichen auf den (immer wahren) `>=`-Zweig reduziert;
  die `6.6.8.0`-Weiche bleibt.
- `checkout-states.cy.js`: `isVersionLower('6.4.1')`-Sonderfall („Paid") entfernt.
- `vouchers.cy.js` / `subscription.cy.js`: je ein `isVersionLower(6.4)`-Skip entfernt.

Summe Cypress: 7 Dateien, ~132 Zeilen entfernt. Rein Test-Code, keine Produktivlogik betroffen.

---

## Bewusst NICHT geändert

| Stelle | Grund |
|---|---|
| `mollie/component/apple-pay-direct-button.html.twig` (`{% if page.product %}`) | Irreführend als „older shopware" kommentiert, ist aber eine **Kontext-Weiche** (PDP-Seite hat `page.product`, Cart/Listing nur `product`). Beide Pfade sind in 6.5+ aktiv. Entfernen würde den Apple-Pay-Button auf der Produktseite brechen. |
| `mollie-subscriptions-list/index.js` → `compatibilityIcons()` | Kommentar „Shopware's compatibility mapping will be removed in 6.5" bezieht sich auf **Shopware-internes** Icon-Mapping. Genau weil Shopware das gedroppt hat, sichert das Plugin den Icon-Namen selbst ab → für 6.5+ potenziell nötig. Vor einer Vereinfachung erst prüfen, ob `icons-regular-undo` in 6.6/6.7 registriert ist. |
| `core/service/utils/version-compare.utils.js` (+ Spec) | Wird weiterhin von der Support-Modal für `getHumanReadableVersion()` gebraucht (kein Versions-Gate). |

## Aufgeschoben

Keine offenen `< 6.4`/`6.4.x`-Punkte mehr – alle Fundstellen sind entweder bereinigt
(D, F, H, A, E, L, R, C, I) oder bewusst behalten (siehe oben).

**Optionaler nächster Schritt (außerhalb des 6.4-Themas):** Da das Minimum bereits `6.5.8` ist,
sind streng genommen auch reine `6.5`-Weichen (z.B. `isVersionGreaterEqual('6.5')`) immer wahr.
Deren Bereinigung wäre ein eigener Durchgang und wurde hier bewusst nicht angefasst.

---

## Nachtrag: CI-Fehlschläge & Korrektur

Nach dem ersten Push meldete die PR-Pipeline zwei fehlschlagende E2E-Specs:

| Spec | Betroffene Versionen | Ursache |
|---|---|---|
| `storefront/payment-methods/paypal-express.cy.js` | nur 6.5 | **Von diesem PR verursacht (Item A).** Der PDP-Test `getPayPalExpressButton().should('be.visible')` hängt an `paypalExpressVisible`. Durch das Entfernen von `buyableLegacy` wurde die Sichtbarkeit nur noch über `buyable` (`product.available/calculatedMaxPurchase`) bestimmt. Auf dem 6.5-PDP-Template greift dieser Pfad nicht zuverlässig; der `page.product`-basierte `buyableLegacy`-Fallback war dort tragend. |
| `storefront/checkout/checkout-path-prefix.cy.js` | 6.5, 6.6, 6.7 | **Nicht von diesem PR verursacht.** Der Test nutzt nur `switchPaymentMethod('PayPal')` (durch unsere Änderung verhaltensneutral für 6.5–6.7), regulären PayPal-Checkout und die Prefix-Domain-Fixture. Keiner der bereinigten Codepfade wird berührt. Verdacht: vorbestehend/umgebungsbedingt (Prefix-Domain-Fixture bzw. PayPal-Sandbox). **Empfehlung:** prüfen, ob der Test auch auf `main`/ohne diesen PR fehlschlägt. |

**Korrektur (final):** Zunächst wurde Item A auf den Originalstand zurückgesetzt (Fallback wieder
da), anschließend **sauber neu gelöst**: `buyableLegacy` ist raus, stattdessen pro Template die
korrekte Einzelreferenz (`page.product` im page-Template, `product` im component-Template) – siehe
Abschnitt A. Damit ist der PDP-Button auf 6.5 **und** 6.6/6.7 sichtbar, ohne den irreführenden
Dual-Zweig. Twig serverseitig → **kein** Storefront-Rebuild nötig.

**Warum grün auf allen Versionen:** 6.5 → page-Template → `page.product` immer vorhanden →
`buyable` korrekt. 6.6/6.7 → component-Template → `product` im Scope → `buyable` korrekt.
Restriction-Tests bleiben grün (die `('pdp' not in restrictions)`-Prüfung ist unverändert).

**Lektion:** Wie beim Apple-Pay-Button war der `< 6.4`-Kommentar irreführend – es war gar keine
Versions-, sondern eine **Datenquellen-/Template-Kontext-Weiche** (`page.product` vs `product`).
Solche Stellen nicht nach Kommentar löschen, sondern die real verfügbare Referenz pro Kontext
bestimmen und per E2E gegen alle unterstützten Versionen absichern.
