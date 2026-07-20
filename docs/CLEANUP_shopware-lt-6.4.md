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

### A — Buy-Widget Twigs
`storefront/component/buy-widget/buy-widget-form.html.twig` und
`storefront/page/product-detail/buy-widget-form.html.twig`.

Beide setzten neben `buyable` (>= 6.4) zusätzlich `buyableLegacy` (< 6.4, über
`page.product.isCloseout` etc.). Die Sichtbarkeit der Express-Buttons war
`((buyableLegacy) or (buyable and productPrice) > 0)`.

- `buyableLegacy`-Zeile + Kommentare entfernt.
- Bedingung reduziert auf `((buyable and productPrice) > 0)` – die `>= 6.4`-Logik bleibt
  **exakt** erhalten (gleiche Klammerung, gleiches Verhalten).

**Warum ungefährlich:** `buyableLegacy` war reiner < 6.4-Fallback; ab 6.5 ist ausschließlich
`buyable` maßgeblich.
**Empfohlener Test:** Einmal im Storefront prüfen, dass Apple Pay Direct / PayPal Express auf
PDP und in Listing/Buy-Widget wie gewohnt erscheinen.

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
