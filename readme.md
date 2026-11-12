# TYPO3 Extension nkc_base

Die Extension stellt grundlegende Funktionen für Extensions zur Verfügung, die die
Nordkirche API (NAPI) konsumieren (nkc_address, nkc_event, ...):

* einen Service für das NDK (`NdkApiFactory`, statische Fassade `ApiService`)
* einen Base-Controller für die NAPI Extensions
* ein FormEngine-Feld für das TYPO3 Backend, um NAPI Elemente auszuwählen
* einen Routing-Aspect für sprechende URLs von NAPI Objekten (`GenericMapper`)
* einen Command für die Synchronisierung der Kategorien mit der NAPI
* einen Cache für Karten-Marker samt Command zum Aufwärmen
* diverse ViewHelper für die Ausgabe und das Handling von NAPI Inhalten

## Voraussetzungen

* TYPO3 13.4 LTS oder 14.3 LTS
* PHP 8.2 bis 8.5
* nordkirche/ndk 3.x

## Installation

Die Installation erfolgt über Composer, da dabei auch das NDK installiert wird:

    composer req nordkirche/nkc-base

Binden Sie anschließend das Site Set **Nordkirche NAPI Client Base Library**
(`nordkirche/nkc-base`) in Ihre Site-Konfiguration ein. Alternativ steht weiterhin
das statische TypoScript-Template zur Verfügung.

Konfigurieren Sie den NAPI Zugang in der Extension-Konfiguration. Den Zugang erhalten
Sie beim AfÖ der Nordkirche. Alternativ können Sie die Konfiguration in der
`config/system/additional.php` hinterlegen:

    $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nkc_base'] = [
        'NDK_NAPI_PROTOCOL' => 'https',
        'NDK_NAPI_HOST' => 'www.nordkirche.de',
        'NDK_NAPI_PATH' => 'api/',
        'NDK_NAPI_PORT' => '443',
        'NDK_NAPI_VERSION' => '1',
        'NDK_NAPI_USER_ID' => '*******',
        'NDK_NAPI_ACCESS_TOKEN' => '*******',
        'NDK_NAPI_TIMEOUT' => 30,
    ];

Optionale Einstellungen:

| Einstellung | Bedeutung |
| --- | --- |
| `NDK_HTTP_AUTH_USERNAME`, `NDK_HTTP_AUTH_PASSWORD` | HTTP-Basic-Auth vor der NAPI (z. B. für Testsysteme) |
| `NDK_LOG_FILE` | alle NAPI-Requests in diese Datei schreiben (leer = aus) |
| `NDK_NAPI_TIMEOUT` | Zeitlimit für Requests in Sekunden (Standard 20) |
| `NDK_HEALTH_*` | Status-Prüfung und Alarm, siehe [NAPI-Status](#napi-status) |

## Zugriff auf das NDK

In eigenem Code wird die `NdkApiFactory` per Dependency Injection genutzt. Sie liefert
die eine NDK-Instanz der Installation:

    public function __construct(private readonly NdkApiFactory $ndkApiFactory) {}

    $api = $this->ndkApiFactory->getApi();
    $eventRepository = $api->factory(\Nordkirche\Ndk\Domain\Repository\EventRepository::class);

Für bestehenden Code gibt es weiterhin die statische Fassade `ApiService`:

* `ApiService::get()` liefert die NDK-Instanz.
* `ApiService::getRepository($object)` liefert das Repository für ein NAPI Objekt
  (`event`, `person`, `institution`, ...).
* `ApiService::getQuery($object)` liefert ein neues Query für ein NAPI Objekt.
* `ApiService::getAllItems($repository, $query, $includes = [], $pageSize = 50)` holt
  alle Seiten eines Queries und liefert ein Array von NAPI Objekten.
* `ApiService::search($object, $search, $pageSize = 20)` führt eine Volltextsuche aus.

Objektnamen dürfen nur aus Buchstaben bestehen; unbekannte Namen führen zu einer
`ApiException`.

### Beispiel

    $eventRepository = ApiService::getRepository('event');
    $eventQuery = ApiService::getQuery('event');

    // Alle Veranstaltungen ab heute
    $eventQuery->setTimeFromStart(new \DateTime('today'));

    $events = $eventRepository->get($eventQuery);
    $allEvents = ApiService::getAllItems($eventRepository, $eventQuery);

### Fehlerbehandlung

Für Requests gegen die NAPI sollte ein Exception-Handling genutzt werden, damit eine
langsame oder fehlerhafte NAPI nicht zu Fehlern im Frontend führt. Das Zeitlimit für
Requests liegt bei 20 Sekunden und kann über `NDK_NAPI_TIMEOUT` angepasst werden.

    try {
        $events = $eventRepository->get($eventQuery);
    } catch (\Exception $e) {
        // Fehlerbehandlung
    }

Fehler des NDK landen im TYPO3 Log (Kanal `Nordkirche.Ndk`). Um alle NAPI Requests
zu protokollieren, kann im Development Context ein Logfile angegeben werden:

    $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nkc_base']['NDK_LOG_FILE'] = '/shared/ndk.log';

## Base Controller für Extbase Extensions

    class MyNapiController extends \Nordkirche\NkcBase\Controller\BaseController
    {
        public function initializeAction(): void
        {
            parent::initializeAction();
        }
    }

`initializeAction()` initialisiert `$this->api` und `$this->napiService` und mergt
Flexform-Einstellungen mit der TypoScript-Konfiguration: Settings unter

    plugin.tx_mynapi_pi1.settings.flexformDefault {
        foo = bar
    }

werden mit Flexform-Feldern nach dem Schema `settings.flexform.foo` gemergt. Das
Ergebnis liegt in `$this->settings['flexform']`.

Für die Pagination stehen `setPagination()`, `getPaginationValues()`, `getItemsPerPage()`
und `getPagination()` zur Verfügung. Die aktuelle Seite wird immer als Parameter
übergeben. Die Vorgaben kommen aus den Site Settings `nkcBase.paginate.itemsPerPage`
(Standard 20) und `nkcBase.paginate.maximumNumberOfLinks` (Standard 3). Beim statischen
Template sind es die gleichnamigen TypoScript-Konstanten.

`fetchList($repository, $query)` holt eine Liste. Lehnt die NAPI die Abfrage ab (HTTP 4xx,
z. B. ein Filter, den der Endpunkt nicht kennt), gibt es ein leeres Ergebnis und einen
Log-Eintrag statt einer Fehlerseite. Die Seite wird dann nicht gecacht, damit eine kurze
Störung der NAPI nicht als leere Liste im Cache bleibt. Server-Fehler werden weitergereicht.

### Layouts der Listen

`getTemplateLayout()` liefert das Layout des Plugins (`settings.flexform.templateLayout`),
sonst `settings.templateLayout` oder `Default`; `getLayoutPartial('Event/Teaser')` das passende
Partial `Event/Teaser/<Layout>`, wenn es existiert. Die Auswahl im Flexform ergänzt
`Nordkirche\NkcBase\Backend\TemplateLayouts->addItems` um Einträge aus dem Page TSconfig:

    <itemsProcFunc>Nordkirche\NkcBase\Backend\TemplateLayouts->addItems</itemsProcFunc>
    <templateLayouts>tx_nkcevent.templateLayouts</templateLayouts>

### Institution der Site

`QueryFilter::siteInstitutions($settings['siteInstitutions'])` (bzw. `QueryFilter::ids()`) macht
aus „2658, napi://resource/institutions/7“ die IDs; nicht aufgelöste Platzhalter wie
`{rootline:…}` ergeben keine ID.

### Vordefinierte Suchwerte (searchFilter.js)

`Resources/Public/JavaScript/searchFilter.js` macht Listen mit vordefinierten Werten in den
Suchformularen klickbar (Orte, Ortsteile, Rubriken von nkc_address und nkc_event). Die Liste
nennt das Feld `…[searchRequest][<feld>]` desselben Formulars:

    <ul data-search-field="city" data-search-reset="location">
        <li data-value="">Orte</li>                       <!-- leert das Feld -->
        <li data-value="Kiel"><a href="#" role="button">Kiel</a>
            <ul data-search-field="location" data-search-parent-field="city">
                <li data-value="Dom">Dom</li>             <!-- setzt location und city -->

Ein Klick setzt das Feld und schickt das Formular ab. Theme-Skripte können das übernehmen,
indem ihr Klick-Handler vorher `event.preventDefault()` aufruft. Eingebunden wird das Skript von
den Templates per `<f:asset.script identifier="nkcBaseSearchFilter" …>`.

## TCA Feld zur Auswahl von NAPI Objekten

Das FormEngine-Feld `napiItemSelector` kann im TCA oder in Flexforms eingesetzt werden.
Es bietet eine Suggest-Suche und einen Such-Wizard.

    <settings.flexform.eventCollection>
        <label>Diese Veranstaltungen darstellen</label>
        <config>
            <type>user</type>
            <renderType>napiItemSelector</renderType>
            <allowed>event</allowed>
            <maxitems>99</maxitems>
            <size>10</size>
        </config>
    </settings.flexform.eventCollection>

## Sprechende URLs (GenericMapper)

Der Routing-Aspect `GenericMapper` macht aus NAPI IDs lesbare URL-Segmente:

    aspects:
      event:
        type: GenericMapper
        mapper: nkc_base
        object: event              # person, institution, event, ...
        routeFieldResult: '{title}' # Getter des NDK-Objekts, auch verschachtelt: '{name.first}-{name.last}'

Die Slugs werden in der Tabelle `tx_slug_mapping` gespeichert und nur einmal pro Objekt
bei der NAPI angefragt; gleiche Slugs bekommen `-1`, `-2` usw. Optionen:
`prependSlashInSlug`, `fallbackCharacter`. Ändert sich ein Titel in der NAPI, bleibt der
alte Slug gültig, bis der Eintrag in `tx_slug_mapping` gelöscht wird.

### Routen aus den Site Sets

nkc_address und nkc_event bringen ihre Route Enhancer in ihrem Site Set mit
(`Configuration/Sets/<Set>/route-enhancers.yaml`). Wer das Set einbindet, bekommt die
sprechenden URLs ohne Eintrag in der Site-Konfiguration.

* **TYPO3 14** lädt die Dateien selbst.
* **TYPO3 13** kann das noch nicht; nkc_base mischt sie dort ein (`Routing\SetRouteEnhancers`).
  Das gilt für jedes Set mit einer `route-enhancers.yaml`, nach denselben Regeln wie in v14.

Die Routen nutzen den Enhancer-Typ `NkcExtbase` von nkc_base. Er hat dieselben Optionen wie
`Extbase`, bietet die Routen aber nur auf Seiten an, auf denen das Plugin liegt (CType im
`tt_content` der Seite). Sonst würden sich alle Listen `/seite/{currentPage}` teilen und die
zuerst definierte gewinnen. Wird ein Plugin auf einer anderen Seite eingebunden (z. B. per
„Datensatz einfügen“), dort `onlyOnPluginPages: false` oder `limitToPages` setzen.

Die Site-Konfiguration hat immer Vorrang. Einzelne Werte überschreibt man unter demselben
Schlüssel, z. B. nur den Slug der Personen:

    routeEnhancers:
      NkcAddressPerson:
        aspects:
          person:
            routeFieldResult: '{name.last}-{name.first}'

Beim Speichern im Backend-Modul „Sites“ entfernt nkc_base die unveränderten Routen der
Sets wieder, damit sie nicht in die `config.yaml` kopiert werden. Das betrifft auch TYPO3 14.
Nach Änderungen an einer `route-enhancers.yaml` den Cache leeren.

## Synchronisierung der Kategorien

    vendor/bin/typo3 nkc_base:napi_sync <pid>

Übernimmt die NAPI Kategorien als `sys_category` (UID = NAPI ID). Der Command kann
z. B. täglich über den Scheduler ausgeführt werden.

**Wichtig:** Kategorien mit gleicher UID werden überschrieben.

## Karten-Cache

Die Marker von Karten-Inhaltselementen werden im Cache `nkcbase_mapmarkers`
gespeichert. Die Einträge sind mit `tt_content_<uid>` und `pageId_<pid>` getaggt und
werden automatisch geleert, wenn ein Redakteur das Inhaltselement bearbeitet.

### Provider

Extensions mit Karten liefern die Marker über einen Provider. Jede Klasse, die
`MapMarkerProviderInterface` implementiert, wird automatisch registriert:

    final class MapMarkerProvider implements MapMarkerProviderInterface
    {
        public function getSupportedPluginTypes(): array
        {
            return ['nkcaddress_map'];
        }

        public function buildMarkers(array $contentRow, array $settings, Site $site): array
        {
            // Werte, die nicht in der Flexform stehen, kommen aus den Site Settings
            $iconName = $site->getSettings()->get('nkcAddress.map.institutionIconName');
            ...
            return $markers;
        }
    }

Der Provider darf keinen Frontend-Request voraussetzen, da er auch auf der
Kommandozeile läuft.

Im Controller:

    $entry = $this->mapMarkerCache->get(
        $this->request->getAttribute('currentContentObject')->data,
        $this->settings,
        $this->request->getAttribute('site')
    );
    return $this->jsonResponse(json_encode($entry));

### Cache aufwärmen

    vendor/bin/typo3 nkc_base:map_cache

Ohne Optionen ermittelt der Command alle sichtbaren Inhaltselemente, für die ein
Provider registriert ist, und baut Einträge neu auf, die älter als eine Stunde sind.

| Option | Bedeutung |
| --- | --- |
| `--content=1,2` | nur diese Inhaltselemente |
| `--site=<identifier>` | nur Inhaltselemente einer Site |
| `--max-age=<sekunden>` | Alter, ab dem ein Eintrag neu aufgebaut wird (Standard 3600) |
| `--force` | alle Einträge neu aufbauen |
| `--dry-run` | gefundene Inhaltselemente nur auflisten |

Die früheren Argumente (`baseUrl`, `typeNum`, `httpUser`, ...) werden nicht mehr
benötigt und ignoriert. Es werden keine HTTP-Requests mehr ausgeführt.

Kann ein Provider die Marker nur mit dem TypoScript der Seite aufbauen (z. B. Werte aus der
Rootline), wirft er `MapCache\Exception\FrontendRequiredException`. `nkc_base:map_cache`
überspringt das Inhaltselement dann ohne Fehler, der erste Seitenaufruf baut die Marker auf.

### Info-Fenster der Marker

Die Info-Fenster der Marker lädt das Karten-JavaScript erst beim Klick nach:

    GET {Site-Base}marker?items=i:12,p:5,e:7

Die Frontend-Middleware `nordkirche/nkc-base/marker-info` beantwortet diese Anfrage, bevor
die Seite aufgelöst wird. Das Kürzel vor dem Doppelpunkt ist der Objekttyp, danach folgt die
NAPI-ID. Pro Anfrage werden höchstens 100 Einträge verarbeitet, die Antwort ist HTML und wird
10 Minuten gecacht (`Cache-Control: public, max-age=600`).

Extensions liefern das HTML über einen Provider. Jede Klasse, die
`MarkerInfoProviderInterface` implementiert, wird automatisch registriert:

    final class InstitutionInfoProvider implements MarkerInfoProviderInterface
    {
        public function getObjectType(): string
        {
            return 'i';
        }

        public function renderInfo(array $ids, ServerRequestInterface $request, Site $site): string
        {
            // NAPI-Objekte zu $ids laden und als HTML rendern
        }
    }

Da die Middleware vor dem Auflösen der Seite läuft, gibt es kein TypoScript. Einstellungen
kommen aus den Site Settings. Fehler eines Providers werden geloggt, die übrigen Provider
werden trotzdem ausgeführt.

## NAPI-Status

Das Backend-Modul **System > NAPI-Status** (nur für Admins) zeigt:

* **Live-Check:** Pro Endpunkt wird ein Datensatz direkt bei der NAPI abgefragt, am
  HTTP-Cache des NDK vorbei. Angezeigt werden Status, HTTP-Code, Gesamtzeit, die
  Rechenzeit der NAPI (Header `x-runtime`), die Zeitanteile DNS / Verbindung / TLS /
  Server / Übertragung und die Anzahl der Datensätze.
* **Echter Traffic:** Anzahl, Fehlerquote, Cache-Quote sowie ⌀ und maximale Dauer aller
  NAPI-Requests aus Frontend, Backend und CLI der letzten Stunde und der letzten 24
  Stunden, je Endpunkt. Voraussetzung ist ein NDK mit `Configuration::addMiddleware()`
  (NDK 3.0). Die Werte liegen in der Registry (`sys_registry`, 48 Stunden) und bleiben beim Leeren der Caches erhalten.
* **Automatische Prüfung:** das Ergebnis des letzten Laufs von `nkc_base:napi_check`.

Status-Stufen: `ok`, `slow` (langsamer als `NDK_HEALTH_SLOW_MS`), `auth` (Zugang
abgelehnt), `down` (nicht erreichbar, Timeout, 5xx), `config` (Konfiguration
unvollständig).

### Automatische Prüfung und Alarm

    vendor/bin/typo3 nkc_base:napi_check

Den Command im Scheduler (Task „Execute console commands“, Command `nkc_base:napi_check`)
z. B. alle 5 Minuten ausführen. Exit-Code 1 bei `down`,
`auth` oder `config`, so kann er auch von externem Monitoring aufgerufen werden
(`--no-alert` verschickt keine Mails und speichert nichts).

Ist `NDK_HEALTH_ALERT_RECIPIENTS` gesetzt, geht nach `NDK_HEALTH_ALERT_AFTER`
Fehlschlägen in Folge (Standard 3) genau eine Alarm-Mail raus, und eine Entwarnung,
sobald die NAPI wieder normal antwortet.

| Einstellung | Bedeutung | Standard |
| --- | --- | --- |
| `NDK_HEALTH_SLOW_MS` | ab dieser Antwortzeit gilt die NAPI als langsam | 1500 |
| `NDK_HEALTH_ENDPOINTS` | geprüfte Endpunkte | institutions,people,events,categories |
| `NDK_HEALTH_ALERT_RECIPIENTS` | Empfänger der Alarm-Mails (kommagetrennt) | leer = kein Alarm |
| `NDK_HEALTH_ALERT_AFTER` | Fehlschläge in Folge bis zum Alarm | 3 |

Die Werte werden in der Extension-Konfiguration (Reiter „status“) gepflegt oder in der
`config/system/additional.php`:

    $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nkc_base']['NDK_HEALTH_ALERT_RECIPIENTS'] = 'admin@example.org,technik@example.org';
    $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nkc_base']['NDK_HEALTH_SLOW_MS'] = 2000;

Die Mails werden über die TYPO3-Mail-API verschickt. Der Mail-Transport muss also
eingerichtet sein. Als Absender dient `$GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress']`
(bzw. `defaultMailFromName`).

### Dashboard-Widget

Ist EXT:dashboard installiert, gibt es in der Gruppe „System Information“ das Widget
**NAPI-Status** mit dem Ergebnis der letzten automatischen Prüfung und dem Traffic
der letzten Stunde. Redakteure sehen es, wenn ihre Benutzergruppe das Widget erlaubt
(„Erlaubte Dashboard-Widgets“); den Link zum Modul bekommen nur Benutzer mit Zugriff
auf das Modul (Admins).

## ViewHelper

Namespace: `Nordkirche\NkcBase\ViewHelpers`. Die ViewHelper enthalten jeweils ein
Beispiel. `link.page` erzeugt Links über den Router der Ziel-Site und funktioniert
auch ohne Frontend-Request, z. B. beim Aufwärmen des Karten-Caches.

## Fluid Styled Content Layout

Die Extension bringt ein eigenes Default Layout für Fluid Styled Content mit. Die
Nordkirche Extensions laden Inhalte per AJAX nach und verwenden dafür das TypoScript
CONTENT Objekt. Mit `settings.additionalConfig.plainResult` wird der Inhalt ohne
umschließendes `<div>` ausgegeben.

## Upgrade auf Version 13

* TYPO3 13.4 / 14.3, PHP 8.2 oder neuer
* `BaseController::getWidgetValues()` wurde entfernt. Die aktuelle Seite wird als
  Parameter übergeben.
* `BaseController::initializeAction()` hat den Rückgabetyp `void`. Abgeleitete
  Controller müssen die Signatur anpassen.
* Der Karten-Cache läuft über `MapMarkerProviderInterface` und `MapMarkerCache`.
  Die Controller-Methoden `buildCache()` und der Parameter `forceReload` werden nicht
  mehr aufgerufen.
* Die Backend-JavaScripts sind ES-Module (`@nordkirche/nkc-base/...`), jQuery und
  RequireJS werden nicht mehr verwendet.
* Neue Indizes auf `tx_slug_mapping`: bitte den Database Analyzer ausführen.

## Fehler gefunden?

Bitte melden Sie Fehler via GitHub: https://github.com/Nordkirche/nkc-base

## Tests

    composer install
    composer test:unit
    composer test:functional    # SQLite, keine Datenbank nötig

Die Functional Tests laufen gegen TYPO3 13.4 und 14.3 (testing-framework ^9.2).
