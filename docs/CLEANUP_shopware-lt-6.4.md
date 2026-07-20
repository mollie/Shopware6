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

---

## Bewusst NICHT geändert

| Stelle | Grund |
|---|---|
| `mollie/component/apple-pay-direct-button.html.twig` (`{% if page.product %}`) | Irreführend als „older shopware" kommentiert, ist aber eine **Kontext-Weiche** (PDP-Seite hat `page.product`, Cart/Listing nur `product`). Beide Pfade sind in 6.5+ aktiv. Entfernen würde den Apple-Pay-Button auf der Produktseite brechen. |
| `mollie-subscriptions-list/index.js` → `compatibilityIcons()` | Kommentar „Shopware's compatibility mapping will be removed in 6.5" bezieht sich auf **Shopware-internes** Icon-Mapping. Genau weil Shopware das gedroppt hat, sichert das Plugin den Icon-Namen selbst ab → für 6.5+ potenziell nötig. Vor einer Vereinfachung erst prüfen, ob `icons-regular-undo` in 6.6/6.7 registriert ist. |
| `core/service/utils/version-compare.utils.js` (+ Spec) | Wird weiterhin von der Support-Modal für `getHumanReadableVersion()` gebraucht (kein Versions-Gate). |

## Aufgeschoben (größerer/eigener Umfang)

| Stelle | Warum aufgeschoben |
|---|---|
| `payment-method.html.twig` + `cc-fields.html.twig` + `register.js` (`sw64`-Flag) | Kein toter Code, aber die `sw64`-Verzweigung ist ab 6.5 immer `true`. Vereinfachung greift in 3 Dateien inkl. JS-Plugin-Name → separat entscheiden. |
| `tests/Cypress/.../PaymentAction.js` (`openPaymentsModal`/`closePaymentsModal`) | Als `@version < 6.4` markiert, aber die Methoden werden an ~15 Test-Stellen aufgerufen; sauberes Entfernen erfordert alle Aufrufer. Reiner Test-Code, niedrige Priorität. |
