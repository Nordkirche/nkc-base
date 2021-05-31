# TYPO3 Extension nkc_base

Die Extension stellt einige grundlegende Funktionen für die Nordkirche API konsumierenden Extensions (nkc_address, nkc_event) zur Verfügung. Dazu gehören

* einen ApiService für das NDK
* einen Base-Controller für die NAPI Extensions
* ein TCA- bzw. Flexform-Feld für das TYPO3 Backend, um auf NAPI Elemente zuzugreifen
* einen Scheduler Job für die Synchronisierung der Kategorien mit der NAPI
* einen Scheduler Job für den Warm-Up des Karten-Marker Caches
* diverse ViewHelper für die Ausgabe und das Handling von NAPI Inhalten

## Voraussetzungen
Diese Extension benötigt

    nordkirche/NDK ^1.7
    TYPO3 10.4
            
## Installation
Die Installation der Extension erfolgt über composer, da bei dieser Installation auch alle Abhängigkeiten mit installiert werden müssen.

    composer req nordkirche/nkc-base
    

Konfigurieren Sie anschließend den NAPI Zugang im Bereich der Extension Konfiguration. Sie erhalten Ihren NAPI Zugang beim AfÖ der Nordkirche.     

Alternativ können Sie in der AdditionalConfiguration.php die Konfiguration hinterlegen.

    $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nkc_base'] = [
        'NDK_NAPI_PROTOCOL' => 'https',
        'NDK_NAPI_HOST' => 'www.nordkirche.de',        
        'NDK_NAPI_PATH' => 'api/',
        'NDK_NAPI_PORT' => '443',
        'NDK_NAPI_VERSION' => '1',
        'NDK_NAPI_USER_ID' => '*******',        
        'NDK_NAPI_ACCESS_TOKEN' => '*******',
        'NDK_NAPI_TIMEOUT' => 30
    ]; 


## ApiService
Der APIService stellt ausschließlich statische Methoden bereit, um die Initialisierung und Nutzung des NDK zu vereinfachen

### Die Methoden

#### ApiService::get()
Der Aufruf erfolgt ohne Parameter. Die Methode liefert eine NDK Instanz zurück.
Sollte noch keine Instanz im Service registriert sein, wird eine neue erstellt. Dabei greift der Service auf Zugangsdaten / Konfiguration aus der Extension Configuration (ext_conf_template.txt) zurück.

#### ApiService::getRepository($object)
Beim Aufruf wird ein NAPI Objektname erwartet (event, person, institution, ...). Die Methode liefert das Repository für das Objekt zurück, mit dessen Hilfe dann ein Request gegen die NAPI vorgenommen werden kann.

#### ApiService::getQuery($object)
Beim Aufruf wird ein NAPI Objektname erwartet (event, person, institution, ...). Die Methode liefert ein Query für das Objekt zurück, mit dessen Hilfe dann ein Query gebaut werden kann. 

#### ApiService::getAllItems($repository, $query, $includes = [], $pageSize = 50)
Die NAPI liefert aus Gründen der Performance pro Request maximal 99 Objekte zurück. Weitere Objekte lassen sich nur durch Paginierung abrufen. Diese Methode ein Helper, um wirklich alle Objekte für ein Query abzurufen.

    $repository - Repository Instanz
    $query - Query Instanz
    $includes - ein Array mit den Includes (siehe NDK Dokumentation)
    $pageSize - die Anzahl Objekte pro Abruf 

### Code Beispiele

#### Eine Repository Instanz über die API Factory holen 
    $api = \Nordkirche\NkcBase\ApiService::get();
    $eventRepository = $api->factory(\Nordkirche\Ndk\Domain\Repository\EventRepository::class);

#### Eine Repository Instanz über den ApiService holen
    $eventRepository = \Nordkirche\NkcBase\ApiService::getRepository('event');

#### Ein Veranstaltungs-Query bauen und gegen die NAPI anfragen
    $eventRepository = $api->factory(\Nordkirche\Ndk\Domain\Repository\EventRepository::class);
    $eventQuery = \Nordkirche\NkcBase\ApiService::getQuery('event');  
        
    # Alle Veranstaltungen ab heute
    $eventQuery->setTimeFromStart(new \DateTime(date('Y-m-d')));   
        
    $events = $eventRepository->get($eventQuery);
    $allEvents = \Nordkirche\NkcBase\ApiService\ApiService::getAllItems($eventRepository, $eventQuery);
    
Das Ergebnis ist ein Array von NAPI Objekten

### Fehlerbehandlung
Es empfiehlt sich, für Requests gegen die NAPI ein Exception-Handling zu nutzen, damit es nicht zu Ooops Meldungen im Frontend kommt, wenn die NAPI einmal nicht schnell genug reagiert oder einen Fehler zurück gibt. Die Standard-Limitierung für Requests gegen die NAPI liegt bei 20 Sekungen und kann über die Extension-Konfiguration angepasst werden.    
   
    try {
        # Veranstaltungen holen
        $events = $eventRepository->get($eventQuery);
    } catch (\Exception $e) {
        # Fehlerbahandlung
        ...
    }
    
Wenn Sie prüfen möchten, welche NAPI Zugriffe von Ihrem System erfolgen, können Sie ein Logfile definieren, in das alle Requests protokolliert werden:

        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nkc_base']['NDK_LOG_FILE'] = '/shared/ndk.log';
    
Die Log Funktion wird nur im Development Context unterstützt.
    
## Base Controller für Extbase Extensions
Der Base-Controller sollte von einer NAPI basierten Extbase Extension beerbt werden:

        class myFancyNapiController extends \Nordkirche\NkcBase\BaseController
        

Der Controller bringt eine initializeAction Methode mit, die bei der Implementierung einer eigenen initializeAction Methode aufgerufen werden sollte.

    class myFancyNapiController extends \Nordkirche\NkcBase\BaseController {
        
        public function initializeAction() 
        {
            parent::initializeAction();            
        }
    }

Die Methode initialisiert 

* $this->api
* $this->napiService 

Außerdem mergt sie Flexform Einstellungen mit TypoScript Konfigurationen.

Settings, die in
 
    plugin.tx_myfancynapi_pi1.settings.flexformDefault {
        foo = bar
    }

werden mit Flexform Feldern gemergt, welche dem folgenden Namensschema folgen

    <settings.flexform.foo></settings.flexform.foo>

Das Ergebnis liegt im Settings Array 
    
    $this->settings['flexform'] 
    
   
## TCA Feld zur Auswahl von NAPI Objekten
Für das Backend bringt die Extension ein neues FormEngine Feld mit, mit dem sich NAPI Elemente auswählen lassen. Dieses Feld kann im TCA oder in PlugIn Flexforms eingesetzt werden.


### Beispiel

    <settings.flexform.eventCollection>
        <TCEforms>
            <label>Diese Veranstaltungen darstellen</label>
            <config>
                <type>user</type>
                <renderType>napiItemSelector</renderType>
                <allowed>event</allowed>
                <minItems>0</minItems>
                <maxItems>99</maxItems>
                <size>10</size>
            </config>            
        </TCEforms>
    </settings.flexform.eventCollection>    
   
## Scheduler Job zur Synchronisierung von Kategorien
Die NAPI verwendet Kategorien, die als Sys-Categories in das eigene TYPO3 System übernommen werden können. Diese Extension stellt dafür einen Scheduler Job zur Verfügung, der z.B. einmal täglich ausgeführt werden kann.

Wichtig: Bitte beachten Sie, dass bei der Verwendung des Jobs selbst angelegte Kategorien überschrieben werden. 
  

## Scheduler Job für den Map Cache Warmup
Wenn Sie Karten mit vielen Markern darstellen möchten, können Sie den Cache für die Marker durch einen Scheduler Job aufwärmen.

Geben Sie in der Task-Konfiguration die betroffenen Content Elemente an (uid), die berücksichtigt werden sollen.  
   
## ViewHelper
Sie finden in den ViewHelpern jeweils ein Code-Beispiel zur Verwendung    

## Fluid Styled Content Layout
Diese Extension bringt ein eigenes Default Layout für Fluid Styled Content mit. Die anderen Nordkirche Extensions, die auf nkc_base basieren, laden Inhalte per AJAX nach und verwendet dafür das TypoScript CONTENT Objekt. Dieses Verfahren sorgt dafür, dass auch bei einer Extbase JSON View ein &lt;div&gt; um den Content gelegt wird. Um das zu vermeiden, gibt es ein leicht angepasstes Layout.

Wir werden dies ggf. in zukünftigen Versionen ändern, so dass kein eigenes Layout mehr notwenig ist.  

## Fehler gefunden?
Bitte melden Sie Fehler via github
https://github.com/Nordkirche/nkc-base