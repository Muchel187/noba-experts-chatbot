<?php
/**
 * SICHERE Backend-API für KI-Chatbot
 * Schützt Ihren API Key vor Diebstahl
 *
 * Datei auf Ihrem Webserver speichern
 */

header('Content-Type: application/json');

// CORS-Header: Dynamisch für Entwicklung und Produktion
$allowed_origins = [
    'https://www.noba-experts.de',
    'https://chatbot.noba-experts.de',
    'http://www.noba-experts.de',
    'http://chatbot.noba-experts.de',
    'http://localhost',
    'http://localhost:3000',
    'http://localhost:5173',  // Vite default port
    'http://localhost:8000',
    'http://localhost:8080',
    'http://127.0.0.1',
    'http://127.0.0.1:3000',
    'http://127.0.0.1:5173',  // Vite default port
    'http://127.0.0.1:8000',
    'http://127.0.0.1:8080'
];

$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';

// Prüfe ob Origin erlaubt ist, oder erlaube alle für lokale Tests
if (in_array($origin, $allowed_origins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} elseif (strpos($origin, 'localhost') !== false || strpos($origin, '127.0.0.1') !== false) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    header('Access-Control-Allow-Origin: https://www.noba-experts.de'); // Fallback für Produktion
}

header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// POST-Requests erlauben (OPTIONS wurde bereits oben behandelt)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode([
        'success' => false,
        'error' => 'Method not allowed. Only POST requests are accepted.',
        'received_method' => $_SERVER['REQUEST_METHOD']
    ]));
}

// ===== KONFIGURATION (NUR HIER ÄNDERN!) =====
$CONFIG = [
    // ⚠️ WICHTIG: Google AI API Key - SICHER auf dem Server!
    // Ersetzen Sie 'IHR_KEY_HIER' mit Ihrem echten Google AI API Key
    // API Key erhalten Sie unter: https://makersuite.google.com/app/apikey
    'GOOGLE_AI_API_KEY' => 'AIzaSyBtwnfTYAJgtJDSU7Lp5C8s5Dnw6PUYP2A', // ← Google Gemini API Key

    // Google Gemini Modell (laut Google Cloud Dokumentation)
    // Verfügbare Modelle: 
    // - 'gemini-2.5-flash' (NEUESTES - GA seit Juni 2025, beste Preis/Leistung)
    // - 'gemini-1.5-pro' (Leistungsstärkstes, komplexe Aufgaben)
    // - 'gemini-1.5-flash' (Bewährt & schnell)
    'GEMINI_MODEL' => 'gemini-2.5-flash-lite', // ← Höhere Quota (4000/Tag)
    
    // Rate Limiting
    'MAX_REQUESTS_PER_MINUTE' => 30,
    'MAX_MESSAGE_LENGTH' => 500000, // 500KB für Document Uploads (10MB komprimiert)

    // HubSpot (optional für direkte Integration)
    'HUBSPOT_PORTAL_ID' => '146015266',
    'HUBSPOT_FORM_ID' => 'ef5093e2-81d2-4860-a537-79cebadf625c'
];

// Erlaube Überschreiben per Umgebungsvariable, ohne Codeänderungen auf dem Server
if (getenv('GOOGLE_AI_API_KEY')) {
    $CONFIG['GOOGLE_AI_API_KEY'] = getenv('GOOGLE_AI_API_KEY');
}


// ===== SICHERHEIT: Rate Limiting =====
session_start();
$session_id = session_id();
$current_time = time();

// Request-Counter
if (!isset($_SESSION['request_count'])) {
    $_SESSION['request_count'] = 0;
    $_SESSION['first_request_time'] = $current_time;
}

// Reset counter nach 1 Minute
if ($current_time - $_SESSION['first_request_time'] > 60) {
    $_SESSION['request_count'] = 0;
    $_SESSION['first_request_time'] = $current_time;
}

// Prüfe Rate Limit
if ($_SESSION['request_count'] >= $CONFIG['MAX_REQUESTS_PER_MINUTE']) {
    http_response_code(429);
    die(json_encode([
        'error' => 'Zu viele Anfragen. Bitte warten Sie eine Minute.',
        'retry_after' => 60 - ($current_time - $_SESSION['first_request_time'])
    ]));
}

$_SESSION['request_count']++;

// ===== INPUT VALIDIERUNG =====
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['message'])) {
    http_response_code(400);
    die(json_encode(['error' => 'Keine Nachricht erhalten']));
}

$user_message = trim($input['message']);
$conversation_history = $input['history'] ?? [];

// Sicherheitschecks
if (strlen($user_message) > $CONFIG['MAX_MESSAGE_LENGTH']) {
    http_response_code(400);
    die(json_encode(['error' => 'Nachricht zu lang']));
}

if (empty($user_message)) {
    http_response_code(400);
    die(json_encode(['error' => 'Nachricht ist leer']));
}

// XSS-Schutz
$user_message = htmlspecialchars($user_message, ENT_QUOTES, 'UTF-8');

// ===== HOMEPAGE CONTENT EXTRAKTION =====
function fetchHomepageContent() {
    static $cache = null;

    // Cache für 1 Stunde
    if ($cache !== null && isset($cache['time']) && (time() - $cache['time']) < 3600) {
        return $cache['content'];
    }

    $url = 'https://www.noba-experts.de';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $html = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200 || !$html) {
        error_log('⚠️ Homepage fetch failed: HTTP ' . $http_code);
        return '';
    }

    // Extrahiere Text-Content (entferne HTML-Tags)
    $text = strip_tags($html);
    // Entferne überflüssige Whitespaces
    $text = preg_replace('/\s+/', ' ', $text);

    $cache = [
        'content' => $text,
        'time' => time()
    ];

    return $text;
}

// ===== AKTUELLE STELLENANGEBOTE FETCHEN =====
function fetchCurrentJobs() {
    static $cache = null;
    static $cache_time = 0;

    // Cache für 1 Stunde (3600 Sekunden)
    if ($cache !== null && (time() - $cache_time) < 3600) {
        return $cache;
    }

    try {
        $url = 'https://www.noba-experts.de/#jobs';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Falls SSL-Probleme
        $html = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200 || !$html) {
            error_log('⚠️ Konnte Stellenangebote nicht laden: HTTP ' . $http_code);
            return null;
        }

        // Extrahiere Job-Karten (vereinfachtes Pattern für typische Job-Listings)
        $jobs = [];

        // Pattern 1: Versuche <h3> oder <h2> Tags mit Job-Titeln zu finden
        if (preg_match_all('/<h[23][^>]*class="[^"]*job[^"]*"[^>]*>(.*?)<\/h[23]>/is', $html, $matches)) {
            foreach ($matches[1] as $title) {
                $jobs[] = ['title' => strip_tags(trim($title))];
            }
        }

        // Pattern 2: Falls kein spezifisches Pattern, versuche allgemeine Struktur
        if (empty($jobs)) {
            // Extrahiere alle <article> oder <div class="job"> Elemente
            if (preg_match_all('/<(?:article|div)[^>]*(?:class="[^"]*(?:job|position|vacancy)[^"]*")[^>]*>(.*?)<\/(?:article|div)>/is', $html, $matches)) {
                foreach ($matches[1] as $job_html) {
                    // Extrahiere ersten h2/h3 Tag als Titel
                    if (preg_match('/<h[23][^>]*>(.*?)<\/h[23]>/is', $job_html, $title_match)) {
                        $title = strip_tags(trim($title_match[1]));
                        // Extrahiere zusätzliche Infos (Ort, Typ)
                        $details = '';
                        if (preg_match('/<p[^>]*>(.*?)<\/p>/is', $job_html, $details_match)) {
                            $details = strip_tags(trim($details_match[1]));
                        }

                        $jobs[] = [
                            'title' => $title,
                            'details' => $details
                        ];
                    }
                }
            }
        }

        // Fallback: Wenn keine Jobs gefunden, verwende bekannte Positionen
        if (empty($jobs)) {
            error_log('⚠️ Keine Jobs via HTML-Parsing gefunden, verwende Fallback');
            $jobs = [
                ['title' => 'General Manager Europe', 'details' => 'Remote / Europa, Vollzeit'],
                ['title' => 'Vertriebsmitarbeiter im Außendienst', 'details' => 'Kreis Düsseldorf, Vollzeit'],
                ['title' => 'Software-Ingenieur Embedded Systems', 'details' => 'Neuss, Vollzeit'],
                ['title' => 'Technischer Einkäufer', 'details' => 'Aachen, Vollzeit']
            ];
        }

        // Limitiere auf erste 5 Jobs
        $jobs = array_slice($jobs, 0, 5);

        $cache = $jobs;
        $cache_time = time();

        error_log('✅ Stellenangebote geladen: ' . count($jobs) . ' Jobs');
        return $jobs;

    } catch (Exception $e) {
        error_log('⚠️ Fehler beim Fetchen der Stellenangebote: ' . $e->getMessage());
        // Fallback
        return [
            ['title' => 'General Manager Europe', 'details' => 'Remote / Europa, Vollzeit'],
            ['title' => 'Vertriebsmitarbeiter im Außendienst', 'details' => 'Kreis Düsseldorf, Vollzeit'],
            ['title' => 'Software-Ingenieur Embedded Systems', 'details' => 'Neuss, Vollzeit'],
            ['title' => 'Technischer Einkäufer', 'details' => 'Aachen, Vollzeit']
        ];
    }
}

function getRelevantContext($message) {
    $lower = strtolower($message);

    // Keyword-Mapping für verschiedene Themen
    $keywords = [
        'leistungen|services|angebot|was bietet|was macht' => 'LEISTUNGEN_DETAIL',
        'talent.*intelligence|hub|ki.*match|persönlichkeit.*test' => 'TALENTHUB_DETAIL',
        'executive search|führungskräfte|c-level' => 'EXECUTIVE_DETAIL',
        'team.*building|team.*zusammen' => 'TEAMBUILDING_DETAIL',
        'projekt.*besetz|freelancer|interim' => 'PROJEKTBESETZUNG_DETAIL',
        'kandidat|bewerb|job.*such|karriere' => 'KANDIDATEN_DETAIL',
        'bereiche|branche|it|engineering|automotive' => 'BEREICHE_DETAIL',
        'prozess|ablauf|wie.*läuft|wie.*funktioniert' => 'PROZESS_DETAIL',
        'kontakt|telefon|email|erreichbar|termin' => 'KONTAKT_DETAIL',
        'big five|persönlichkeit.*analyse|ocean|test' => 'BIGFIVE_DETAIL',
        'cv.*optim|lebenslauf.*optim|bewerbung.*optim|cv.*hilfe|lebenslauf.*hilfe' => 'CV_OPTIMIERUNG_DETAIL',
        'bewerbungsunterlagen|unterlagen.*bewerbung|dokumente.*bewerbung' => 'BEWERBUNGSUNTERLAGEN_DETAIL',
        'vorstellungsgespräch|interview|bewerbungsgespräch|gespräch.*vorbereitung' => 'BEWERBUNGSGESPRAECH_DETAIL',
        'bewerbungsfoto|foto.*bewerbung|foto.*cv|foto.*lebenslauf' => 'BEWERBUNGSFOTO_DETAIL',
    ];

    foreach ($keywords as $pattern => $context_type) {
        if (preg_match('/' . $pattern . '/i', $lower)) {
            return $context_type;
        }
    }

    return null;
}

function buildContextInfo($context_type) {
    // Strukturierte Infos basierend auf dem erkannten Thema
    $contexts = [
        'LEISTUNGEN_DETAIL' => "📋 **DETAILLIERTE LEISTUNGEN:**

**🏢 FÜR UNTERNEHMEN:**
• Executive Search: Diskrete Direktansprache von Führungskräften, über 10 Jahre Erfahrung
• Projektbesetzung: Schnelle Vermittlung (2-4 Wochen) von Freelancern und Interim-Managern
• Team Building: Zusammenstellung optimal aufeinander abgestimmter Teams
• TalentIntelligence Hub: KI-gestützte HR-Plattform mit Big Five-Modell
  - Signifikant weniger Fehlbesetzungen
  - Deutlich schnellere Besetzungsprozesse
  - Bessere Teampassung durch wissenschaftliche Analyse

**👤 FÜR KANDIDATEN (kostenfrei):**
• Karriereberatung mit wissenschaftlich fundiertem Test
• KI Karrierecoach mit Persönlichkeitsanalyse
• Kostenloser Persönlichkeitstest (10-15 Min, Big Five)
• Zugang zum verdeckten Stellenmarkt
• CV-Optimierung & Interview-Coaching

**🎯 SPEZIALISIERUNG:**
• IT: Cloud-Architekten, DevOps, Cybersecurity, Software-Entwicklung (Java, Python, .NET), Data Science
• Engineering: Maschinenbau, Automotive, E-Mobilität, Embedded Systems, Produktentwicklung",

        'TALENTHUB_DETAIL' => "🚀 TALENTINTELLIGENCE HUB:

Was ist das?
KI-gestützte HR-Plattform auf Basis des wissenschaftlich validierten Big Five-Modells (OCEAN)

Kernfunktionen:
• Persönlichkeits- und Kompetenzanalyse
• Datenbasierte Talentidentifikation
• Team-Optimierung und Zusammenstellung
• Entwicklungspotenziale erkennen
• Nachfolgeplanung und High-Potential-Identifikation
• HR-Analytics und Reporting

Messbare Erfolge:
• Signifikant weniger Fehlbesetzungen durch präzise Analyse
• Deutlich schnellere Besetzungsprozesse
• Bessere Teampassung durch Dynamik-Vorhersage

Test unter: test.noba-experts.de (10-15 Minuten, kostenfrei)",

        'EXECUTIVE_DETAIL' => "💼 EXECUTIVE SEARCH:

Spezialisierung:
• C-Level Positionen (CTO, CIO, CEO, CFO)
• Bereichsleiter IT & Engineering
• Interim Management für kritische Projekte
• Change Management Leadership

Prozess:
1. Diskrete Bedarfsanalyse und Kulturverständnis
2. Zugang zu exklusivem Netzwerk (über 10 Jahre aufgebaut)
3. Direktansprache passiver Kandidaten
4. Strukturierte Interviews mit Big Five-Analyse
5. Detaillierte Kandidatenpräsentation
6. Onboarding-Begleitung

Erfolgsgarantie: Langfristige Besetzungen, nicht Quick Wins",

        'TEAMBUILDING_DETAIL' => "👥 TEAM BUILDING:

Ansatz:
Zusammenstellung optimal aufeinander abgestimmter Teams mit KI-Unterstützung

Vorteile:
• Signifikant bessere Teampassung durch Persönlichkeitsanalyse
• Vorhersage von Team-Dynamiken
• Ergänzende Skill-Sets und Arbeitsstile
• Reduzierung von Konflikten
• Höhere Produktivität

Prozess:
1. Analyse bestehender Team-Mitglieder (Big Five)
2. Identifikation fehlender Profile
3. Gezielte Suche nach komplementären Persönlichkeiten
4. Integration und Onboarding-Begleitung",

        'PROJEKTBESETZUNG_DETAIL' => "⚡ PROJEKTBESETZUNG:

Schnelle Vermittlung in 2-4 Wochen!

Zielgruppen:
• Freelancer für zeitkritische Projekte
• Interim-Manager für Überbrückungen
• Projekt-Teams für definierte Laufzeiten

Bereiche:
• IT-Projekte (Cloud-Migration, Software-Entwicklung)
• Engineering-Projekte (Produktentwicklung, Automotive)
• Change Management & Transformation

Prozess: KI-gestütztes Active Sourcing + etabliertes Netzwerk = Schnelle Ergebnisse",

        'KANDIDATEN_DETAIL' => "🎯 FÜR KANDIDATEN (KOSTENFREI):

Services:
• Karriereberatung mit wissenschaftlich fundiertem Test
• KI Karrierecoach: Persönlichkeitsanalyse + passende Karrierewege
• Kostenloser Persönlichkeitstest (10-15 Min, Big Five-Modell)
• Zugang zum verdeckten Stellenmarkt (viele Top-Positionen sind nicht öffentlich ausgeschrieben)
• CV-Optimierung & Interview-Coaching

Prozess:
1. Test starten auf test.noba-experts.de
2. Big Five-Fragen beantworten (10-15 Min)
3. Detaillierte Persönlichkeitsanalyse erhalten
4. Maßgeschneiderte Karriereempfehlungen
5. Zugang zu exklusiven Positionen

Vorteil: Viele Top-Positionen werden NUR über NOBA besetzt (nicht öffentlich ausgeschrieben)",

        'BEREICHE_DETAIL' => "🔧 SPEZIALISIERUNGSBEREICHE:

IT:
• Cloud-Architekten (AWS, Azure, GCP)
• DevOps-Engineers (CI/CD, Kubernetes)
• Cybersecurity-Spezialisten
• Software-Entwicklung (Java, Python, JavaScript, .NET, React)
• Data Science & ML Engineering
• Frontend/Backend/Full-Stack Entwickler

ENGINEERING:
• Maschinenbau & Elektrotechnik
• Automotive & E-Mobilität
• Embedded Systems & Firmware
• Produktentwicklung & Design
• Manufacturing & Lean Production
• Anlagenbau & Automatisierung

MANAGEMENT:
• C-Level (CTO, CIO, CEO, CFO)
• Interim Management
• Projektmanagement (Agile, Scrum, PMP)
• Change Management

Standort: Düsseldorf + bundesweit + Remote-Positionen",

        'PROZESS_DETAIL' => "📊 UNSER PROZESS:

1. BEDARFSANALYSE
   • Detailgespräch zu Anforderungen
   • Kulturverständnis des Unternehmens
   • Tech-Stack und Team-Konstellation

2. ACTIVE SOURCING
   • KI-gestütztes Sourcing über 20+ Plattformen
   • Zugang zu exklusivem Netzwerk (10 Jahre aufgebaut)
   • Direktansprache passiver Kandidaten

3. SCREENING & ANALYSE
   • Strukturierte Interviews
   • Big Five Persönlichkeitsanalyse (OCEAN)
   • Skill-Assessment und technische Tests
   • Referenzen

4. PRÄSENTATION
   • Detaillierte Kandidatenprofile
   • Persönlichkeits-Match-Report
   • Video-Interviews verfügbar
   • Nur 2-3 Top-Kandidaten

5. ONBOARDING
   • Begleitung der ersten 90 Tage
   • Feedback-Schleifen
   • Nachbesetzungsgarantie

Zeitrahmen:
• Projektbesetzung: 2-4 Wochen
• Executive Search: 4-8 Wochen
• Team Building: 4-12 Wochen",

        'KONTAKT_DETAIL' => "📞 KONTAKT NOBA EXPERTS:

Hauptansprechpartner:
Jurak Bahrambäk (Gründer & Geschäftsführer)

Kontaktdaten:
• Telefon: +49 211 975 324 74
• E-Mail: Jurak.Bahrambaek@noba-experts.de
• Website: www.noba-experts.de

Standort:
Neckarstraße 9
40219 Düsseldorf

Geschäftszeiten:
Mo-Fr 09:00-18:00 Uhr

Social Media:
• LinkedIn: NOBA Experts GmbH
• XING, Instagram, Twitter: @NOBA_Experts

Für schnelle Anfragen: KI Karrieretest unter test.noba-experts.de",

        'BIGFIVE_DETAIL' => "🧠 BIG FIVE PERSÖNLICHKEITSMODELL (OCEAN):

Was ist das?
Wissenschaftlich validiertes Modell zur Persönlichkeitsanalyse, Basis des TalentIntelligence Hub

5 Dimensionen:
• Openness (Offenheit): Kreativität, Neugier
• Conscientiousness (Gewissenhaftigkeit): Organisation, Zuverlässigkeit
• Extraversion: Geselligkeit, Durchsetzungskraft
• Agreeableness (Verträglichkeit): Teamfähigkeit, Empathie
• Neuroticism (Neurotizismus): Emotionale Stabilität

Anwendung bei NOBA:
• Kandidaten-Assessment (10-15 Min Test)
• Team-Kompatibilitäts-Analyse
• Führungskräfte-Profiling
• Entwicklungspotenzial-Erkennung

Test starten: test.noba-experts.de (kostenfrei, 10-15 Minuten)

Wissenschaftlich validiert, in der Psychologie seit Jahrzehnten etabliert",

        'CV_OPTIMIERUNG_DETAIL' => "📄 CV-OPTIMIERUNG - KONKRETE TIPPS:

**Struktur & Inhalt:**
• Professionelles Foto (IT: optional, Engineering: empfohlen)
• Kurzes Profil (3-4 Sätze): Wer bin ich? Was kann ich? Was suche ich?
• Umgekehrte Chronologie: Aktuellste Position zuerst
• Messbare Erfolge statt Aufgaben ('Reduktion der Deployment-Zeit um 40%' statt 'CI/CD implementiert')

**Technical Skills:**
• Kategorisieren: Programming Languages / Frameworks / Tools / Cloud
• Skill-Level angeben: Expert / Advanced / Intermediate
• Keine veralteten Technologien (außer relevant)

**Berufserfahrung:**
• Projektkontext + Tech-Stack pro Position
• Team-Größe, Rolle, Verantwortung
• Konkrete Achievements mit Zahlen

**Was NICHT rein:**
• Zu viele Hobbys (max 2-3 relevante)
• Geburtsdatum, Familienstand (DSGVO)
• Schlechte Fotos oder unprofessionelle E-Mail-Adressen

**Länge:**
• 1-2 Seiten für < 10 Jahre Erfahrung
• Max 3 Seiten für Senior/Lead

💡 **TIPP**: Gerne CV hochladen für persönliches Feedback!",

        'BEWERBUNGSUNTERLAGEN_DETAIL' => "📋 **BEWERBUNGSUNTERLAGEN - CHECKLISTE:**

**💾 OPTIMALE DATEIGRÖSSE:**
• Gesamt max. 2-3 MB (als PDF komprimieren)
• Professionelle PDF-Software nutzen (nicht Smartphone-Scan)

**📄 LEBENSLAUF (CV):**
• Anti-chronologische Sortierung (neuste Position zuerst)
• Persönliche Daten: Name, Adresse, Telefon, E-Mail
• Werdegang mit konkreten Achievements und Verantwortungen
• Ausbildung & Zertifikate (relevante zuerst)
• Technical Skills nach Kategorien mit Proficiency-Level
• Sprachkenntnisse (Europäischer Referenzrahmen: A1-C2)
• Hobbys nur wenn relevant für Position
• Verweise auf Anhänge (Zeugnisse, Zertifikate)

**✉️ ANSCHREIBEN:**
• Max. 1 Seite, präzise und persönlich
• Bezug zur ausgeschriebenen Stelle
• Motivation & Mehrwert für Arbeitgeber
• Konkrete Beispiele für Qualifikationen

**📎 ANHÄNGE:**
• Arbeitszeugnisse (letzten 2-3 Positionen)
• Relevante Zertifikate
• Referenzen falls vorhanden

💡 **WICHTIG**: CV hochladen für individuelle Analyse!",

        'BEWERBUNGSGESPRAECH_DETAIL' => "🎯 **VORSTELLUNGSGESPRÄCH - VORBEREITUNG:**

**📚 VOR DEM GESPRÄCH:**
• Unternehmens-Website gründlich lesen
• Pressemitteilungen & News recherchieren
• Social Media Profile checken (LinkedIn, Kununu)
• Stellenbeschreibung auswendig kennen

**💭 ANTWORTEN VORBEREITEN:**
• Stärken & Schwächen konkret benennen
• Motivation für Jobwechsel klar formulieren
• Gehaltsvorstellung realistisch recherchieren
• 'Wo sehen Sie sich in 5 Jahren?' vorbereiten
• Eigene Fragen ans Unternehmen (Team, Projekte, Tech-Stack)

**📞 TELEFON-INTERVIEW:**
• Ruhigen Raum wählen
• Professionelle Mailbox einrichten
• CV & Stellenbeschreibung vor sich legen
• Notizen bereithalten
• Lächeln (hört man am Telefon!)

**🤝 PERSÖNLICHES INTERVIEW:**
• Alle Dokumente ausgedruckt mitnehmen
• 10-15 Min früher erscheinen (nicht zu früh!)
• Angemessene Business-Kleidung
• Augenkontakt & Händedruck
• Handy ausschalten
• Nachfragen stellen (zeigt Interesse)

**✉️ NACH DEM GESPRÄCH:**
• Dankeschön-E-Mail innerhalb 24h
• Offene Punkte klären
• Geduldig auf Rückmeldung warten

💡 **TIPP**: Mock-Interview mit uns üben!",

        'BEWERBUNGSFOTO_DETAIL' => "📸 **BEWERBUNGSFOTO - DOS & DON'TS:**

**✅ QUALITÄTSMERKMALE:**
• Professioneller Fotograf (kein Selfie!)
• Heller, neutraler Hintergrund
• Hochauflösend (mind. 300 dpi)
• Format: 4-5 x 5-7 cm
• Nicht älter als 2 Jahre

**👔 ERSCHEINUNGSBILD:**
• Gepflegtes Äußeres
• Business-Kleidung (Branch-abhängig)
• Natürlicher Gesichtsausdruck
• Freundlich & professionell
• Direkter Blick in Kamera

**❌ WAS VERMEIDEN:**
• Selfies oder Automaten-Fotos
• Passbilder (zu steif)
• Urlaubsfotos zugeschnitten
• Zu dunkle oder unscharfe Bilder
• Abgelaufene Fotos (> 2 Jahre alt)
• Zu private Kleidung (Freizeitlook)
• Starke Filter oder Retusche

**🎯 BRANCHENSPEZIFISCH:**
• IT/Software: Foto optional, Smart-Casual okay
• Engineering/Automotive: Foto empfohlen, Business
• Management/Führung: Foto Pflicht, formell

💡 **HINWEIS**: In IT oft nicht zwingend erforderlich!"
    ];

    return $contexts[$context_type] ?? '';
}

// ===== HELPER: Optionen aus Frage extrahieren =====
function extractOptionsFromQuestion($question) {
    $options = [];

    // Versuche "A oder B" Muster zu finden
    if (preg_match('/([A-ZÄÖÜ][a-zäöüß\-]+(?:\s+[A-ZÄÖÜ][a-zäöüß\-]+)?)\s+oder\s+([A-ZÄÖÜ][a-zäöüß\-]+(?:\s+[A-ZÄÖÜ][a-zäöüß\-]+)?)/u', $question, $matches)) {
        $options[] = '✅ ' . trim($matches[1]);
        $options[] = '✅ ' . trim($matches[2]);
        $options[] = '🔄 Beides';
        $options[] = 'ℹ️ Mehr Infos';
        return $options;
    }

    return [];
}

// ===== QUICK REPLIES GENERATOR =====
function generateQuickReplies($bot_response, $user_message, $history = []) {
    $bot_response_lower = mb_strtolower($bot_response);
    $user_message_lower = mb_strtolower($user_message);

    // Zähle Nachrichten
    $message_count = count($history);

    // Initial Quick Replies (erste Nachricht oder Begrüßung)
    if ($message_count == 0 ||
        strpos($bot_response_lower, 'willkommen') !== false ||
        strpos($bot_response_lower, 'hallo') !== false ||
        strpos($bot_response_lower, 'guten tag') !== false) {
        return [
            '👔 Job suchen',
            '🔍 Mitarbeiter finden',
            '💡 Unsere Services'
        ];
    }

    // ===== INTELLIGENTE FRAGE-ERKENNUNG =====
    // Erkenne spezifische Fragen und generiere passende Quick Replies

    // IT vs Engineering Frage
    if ((strpos($bot_response_lower, 'it-bereich') !== false || strpos($bot_response_lower, 'it bereich') !== false) &&
        (strpos($bot_response_lower, 'engineering') !== false)) {
        return [
            '💻 IT-Bereich',
            '⚙️ Engineering-Bereich',
            '🔄 Beide Bereiche',
            'ℹ️ Mehr Infos zu beiden'
        ];
    }

    // Ja/Nein Frage (z.B. "Interessiert Sie...", "Möchten Sie...")
    if (preg_match('/(interessiert|möchten|wollen|brauchen|benötigen).*\?/i', $bot_response)) {
        return [
            '✅ Ja, gerne',
            '❌ Nein, danke',
            '🤔 Mehr Infos bitte',
            '💬 Weiter chatten'
        ];
    }

    // Multiple Choice Frage (erkennt "oder" in Fragesätzen)
    if (strpos($bot_response_lower, '?') !== false && strpos($bot_response_lower, ' oder ') !== false) {
        // Versuche Optionen aus der Frage zu extrahieren
        $extracted = extractOptionsFromQuestion($bot_response);
        if (!empty($extracted)) {
            return $extracted;
        }
    }

    // Job-Suche Kontext (Kandidat erkannt)
    if (strpos($user_message_lower, 'job') !== false ||
        strpos($user_message_lower, 'stelle') !== false ||
        strpos($user_message_lower, 'karriere') !== false ||
        strpos($bot_response_lower, 'position') !== false ||
        strpos($bot_response_lower, 'jobsuche') !== false) {

        // Nach zweiter Interaktion -> Erweiterte Optionen mit CV-Upload
        if ($message_count >= 2) {
            return [
                '📎 CV hochladen',
                '📅 Kostenloses Beratungsgespräch',
                '💼 Aktuelle Stellenangebote',
                '🤖 KI-Persönlichkeitstest'
            ];
        }

        // Erste Interaktion: Bereich wählen + Option CV hochzuladen
        return [
            '💻 IT/Software',
            '⚙️ Engineering',
            '☁️ Cloud/DevOps',
            '📎 CV hochladen'
        ];
    }

    // Mitarbeiter-Suche Kontext
    if (strpos($user_message_lower, 'mitarbeiter') !== false ||
        strpos($user_message_lower, 'team') !== false ||
        strpos($user_message_lower, 'entwickler') !== false ||
        strpos($bot_response_lower, 'besetzung') !== false) {
        return [
            '⚡ Dringend (ASAP)',
            '📅 In 1-3 Monaten',
            '🎯 Executive Search',
            '👥 Team-Aufbau'
        ];
    }

    // Skills/Tech-Stack Kontext
    if (strpos($bot_response_lower, 'technologie') !== false ||
        strpos($bot_response_lower, 'skills') !== false ||
        strpos($bot_response_lower, 'erfahrung') !== false) {
        return [
            '🔧 Backend (Java, .NET, Python)',
            '🎨 Frontend (React, Angular, Vue)',
            '☁️ Cloud (AWS, Azure, GCP)',
            '🔄 DevOps/CI-CD'
        ];
    }

    // Kontakt/Termin Kontext
    if (strpos($bot_response_lower, 'kontakt') !== false ||
        strpos($bot_response_lower, 'termin') !== false ||
        strpos($bot_response_lower, 'telefon') !== false ||
        strpos($bot_response_lower, 'erreichen') !== false) {
        return [
            '📅 Termin vereinbaren',
            '📞 Rückruf anfordern',
            '📧 E-Mail senden',
            '💬 Weiter chatten'
        ];
    }

    // CV/Dokument Kontext
    if (strpos($bot_response_lower, 'lebenslauf') !== false ||
        strpos($bot_response_lower, 'cv') !== false ||
        strpos($bot_response_lower, 'bewerbung') !== false ||
        strpos($bot_response_lower, 'dokument') !== false) {
        return [
            '📎 CV hochladen',
            '✏️ CV-Tipps erhalten',
            '🎯 Direkt bewerben',
            '💼 Offene Stellen'
        ];
    }

    // Services/Info Kontext
    if (strpos($bot_response_lower, 'leistung') !== false ||
        strpos($bot_response_lower, 'service') !== false ||
        strpos($bot_response_lower, 'angebot') !== false ||
        strpos($user_message_lower, 'was bietet') !== false) {
        return [
            '🎯 Executive Search',
            '🤖 KI-Matching (TalentHub)',
            '💰 Gehaltsberatung',
            '📊 Team-Analyse'
        ];
    }

    // Standort/Remote Kontext
    if (strpos($bot_response_lower, 'standort') !== false ||
        strpos($bot_response_lower, 'remote') !== false ||
        strpos($bot_response_lower, 'homeoffice') !== false ||
        strpos($bot_response_lower, 'düsseldorf') !== false) {
        return [
            '🏢 Vor Ort (Düsseldorf)',
            '🏠 100% Remote',
            '🔄 Hybrid',
            '🌍 Deutschlandweit'
        ];
    }

    // ===== FALLBACK: Bot stellt eine Frage =====
    // Wenn Bot ein Fragezeichen verwendet, IMMER Quick Replies anzeigen
    if (strpos($bot_response, '?') !== false) {
        // Nach längerer Konversation
        if ($message_count > 5) {
            return [
                '✅ Ja',
                '❌ Nein',
                '📅 Termin vereinbaren',
                'ℹ️ Mehr Details bitte'
            ];
        }

        // Standard Antwort-Optionen für Fragen
        return [
            '✅ Ja, gerne',
            '❌ Nein, danke',
            '🤔 Erzählen Sie mehr',
            '📞 Lieber Rückruf'
        ];
    }

    // Default Quick Replies (wenn nichts spezifisches passt UND keine Frage)
    if ($message_count > 5) {
        // Nach längerer Konversation
        return [
            '📅 Termin vereinbaren',
            '📧 Zusammenfassung senden',
            '🆕 Neues Thema',
            '👋 Gespräch beenden'
        ];
    }

    // Standard Quick Replies (nur wenn Bot KEINE Frage gestellt hat)
    return [
        '📞 Kontakt aufnehmen',
        '💼 Aktuelle Stellen',
        '🤖 KI-Test starten',
        'ℹ️ Mehr erfahren'
    ];
}

// ===== GOOGLE GEMINI AI AUFRUF =====
function callGeminiAI($message, $history, $api_key, $model) {
    // Prüfe ob API Key gesetzt wurde
    if ($api_key === 'IHR_KEY_HIER' || empty($api_key)) {
        error_log('⚠️ FEHLER: Google AI API Key nicht gesetzt! Bitte in chatbot-api.php konfigurieren.');
        return null;
    }
    
    // Verwende das konfigurierte Modell (z.B. gemini-1.5-flash)
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $api_key;

    // KOMPAKTER System-Prompt - Optimiert für Token-Limit
    $system_prompt = "Du bist KI-Berater von NOBA Experts (IT & Engineering Recruiting, Düsseldorf).

## MISSION
Erkenne User-Typ PRÄZISE & qualifiziere:

**KRITISCH - User-Typ erkennen:**
- \"Mitarbeiter suchen\", \"Team aufbauen\", \"Stelle besetzen\" = ARBEITGEBER
- \"Job suchen\", \"neue Position\", \"Karriere\" = KANDIDAT

## User-Qualifizierung:
- ARBEITGEBER: Position? Tech-Stack? Teamgröße? Dringlichkeit?
  → **WICHTIG**: Nach 2-3 Nachrichten höflich nach Name & E-Mail fragen!
  → Formulierung: 'Damit ich Sie optimal beraten kann, dürfte ich Ihren Namen und E-Mail erfahren?'
- KANDIDAT: Aktueller Job? Skills? Wechselgrund?
  → Nach 3-4 Nachrichten optional nach Kontaktdaten fragen
- INFO-ANFRAGE: Konkret antworten mit Details!

## KONTAKTDATEN-ABFRAGE (WICHTIG!)
**Bei ARBEITGEBERN** (nach 2-3 Nachrichten):
- 'Damit ich Sie optimal beraten kann, dürfte ich Ihren Namen und E-Mail erfahren?'
- 'Gerne sende ich Ihnen weitere Infos zu. Wie darf ich Sie erreichen?'
- Natürlich in Gesprächsfluss einbauen, NICHT aggressiv!

**NACH Erhalt der Kontaktdaten - WICHTIGER ABSCHLUSS:**
- **NIEMALS** sagen: 'Wir werden nun mit der Suche beginnen' oder 'Wir starten jetzt'
- **STATTDESSEN** kommunizieren: 'Vielen Dank! Ich habe alle wichtigen Informationen notiert. Unser Team wird sich in Kürze persönlich telefonisch bei Ihnen melden, um die nächsten Schritte zu besprechen und den Suchprozess gemeinsam zu planen.'
- Betone: **Persönlicher Kontakt VOR Suchstart**

**Bei KANDIDATEN** (nach 3-4 Nachrichten, optional):
- 'Für eine persönliche Karriereberatung benötige ich Ihre E-Mail. Einverstanden?'

## CV-ANALYSE (wenn Dokument hochgeladen)
Wenn User CV/Lebenslauf hochlädt, gib STRUKTURIERTES Feedback:

**📋 STRUKTUR:**
Bewerte Aufbau & Chronologie in 2-3 Sätzen

**✅ STÄRKEN (3-4 Punkte):**
• Punkt 1 mit konkretem Beispiel
• Punkt 2 mit konkretem Beispiel
• Punkt 3 mit konkretem Beispiel

**💡 VERBESSERUNGSPOTENZIAL (3-5 Punkte):**
• Konkrete Verbesserung 1
• Konkrete Verbesserung 2
• Konkrete Verbesserung 3

**🔧 TECHNICAL SKILLS:**
Kommentar zu Kategorisierung & Level-Angaben

**🎯 ACHIEVEMENTS:**
Sind messbare Erfolge genannt oder nur Aufgaben?

**⭐ GESAMTBEWERTUNG:**
X/10 Punkte - Begründung in 1-2 Sätzen

WICHTIG: Nutze genau diese Struktur mit Emojis und Bulletpoints!

## REGELN
- Standard: 2-3 Sätze (40 Wörter)
- Info-Fragen: 4-6 Sätze, KONKRET antworten mit Details
- CV-Analyse: 8-12 Sätze, strukturiert und detailliert
- Qualifizierung: Mit Rückfrage enden
- Formell (Sie), professionell, beratend
- Bei [CONTEXT-INFO]: Nutze die Infos für detaillierte Antwort!
- **WICHTIG: NIEMALS konkrete Prozentzahlen oder Statistiken nennen** (z.B. NICHT '70% des Stellenmarkts', '90% Erfolgsquote', etc.)
- Stattdessen nutze **vage, professionelle Formulierungen**: 'viele', 'die meisten', 'ein Großteil', 'erheblich', 'signifikant', 'deutlich'
- **Vermeide übertriebene Claims** - bleibe seriös und zurückhaltend

## LEISTUNGEN
**Unternehmen:** Executive Search, Projektbesetzung (2-4 Wochen), Team Building, TalentIntelligence Hub (KI-Matching, hohe Kulturpassung)
**Kandidaten (kostenfrei):** Karriereberatung, Zugang zum verdeckten Stellenmarkt (viele Top-Positionen nicht öffentlich), KI-Coach (test.noba-experts.de)
**Bereiche:** IT (Cloud, DevOps, Software), Engineering (Automotive, Embedded)

## KONTAKT (nach Qualifizierung)
Tel: +49 211 975 324 74
E-Mail: Jurak.Bahrambaek@noba-experts.de
Web: www.noba-experts.de

## BEISPIELE - ARBEITGEBER
User: \"Ich suche einen Mitarbeiter für mein Team\"
Bot: \"Gerne unterstütze ich Sie! Für welche Position suchen Sie und welche Technologien sind wichtig?\"

User: \"DevOps Engineer gesucht\"
Bot: \"Welche Cloud-Plattform nutzen Sie und wie groß ist Ihr Team?\"

User: \"Frau Huiso, dasoldal@exacde.de\"
Bot: \"Vielen Dank, Frau Huiso! Ich habe alle wichtigen Informationen notiert. Unser Team wird sich in Kürze persönlich telefonisch bei Ihnen melden, um die nächsten Schritte zu besprechen und den Suchprozess gemeinsam mit Ihnen zu planen. Haben Sie in der Zwischenzeit noch Fragen?\"

## BEISPIELE - KANDIDAT
User: \"Ich suche einen Job\"
Bot: \"Gerne helfe ich Ihnen! In welchem Bereich suchen Sie (IT oder Engineering) und welche Rolle interessiert Sie?\"

## BEISPIELE - INFO
User: \"Welche Leistungen?\"
Bot: \"Wir bieten: Executive Search, Projektbesetzung (2-4 Wochen), Team Building und TalentIntelligence Hub mit hoher Kulturpassung. Für Kandidaten kostenfrei: Karriereberatung, Zugang zum verdeckten Stellenmarkt, KI-Coach. Interessiert Sie ein Bereich?\"

Ziel: Leads generieren durch strukturierte Gespräche.";

    // Konversationskontext aufbauen mit Rollentrennung
    $contents = [];
    
    // Füge Gesprächsverlauf hinzu
    foreach ($history as $h) {
        $role = ($h['role'] === 'user') ? 'user' : 'model';
        $contents[] = [
            'role' => $role,
            'parts' => [['text' => $h['text']]]
        ];
    }
    
    // Aktuelle Nachricht hinzufügen
    $contents[] = [
        'role' => 'user',
        'parts' => [['text' => $message]]
    ];

    $request_body = [
        'system_instruction' => [
            'parts' => [
                ['text' => $system_prompt]
            ]
        ],
        'contents' => $contents,
        'generationConfig' => [
            'temperature' => 0.7, // Ausgewogen: natürlich aber konsistent
            'topP' => 0.9,
            'topK' => 40,
            'maxOutputTokens' => 800, // Erhöht für CV-Analysen (vorher 300)
            'candidateCount' => 1
        ],
        'safetySettings' => [
            [
                'category' => 'HARM_CATEGORY_HARASSMENT',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
            ],
            [
                'category' => 'HARM_CATEGORY_HATE_SPEECH',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
            ],
            [
                'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
            ],
            [
                'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
            ]
        ]
    ];

    // cURL Request
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_body));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Erhöht auf 30 Sekunden

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    // Detailliertes Error-Logging für Debugging
    if ($http_code !== 200) {
        error_log('❌ Gemini API Error - HTTP Code: ' . $http_code);
        error_log('❌ Gemini API Response: ' . $response);
        error_log('❌ cURL Error: ' . $curl_error);
        error_log('❌ Model used: ' . $model);
        error_log('❌ API URL: ' . $url);
        return null;
    }

    $data = json_decode($response, true);

    if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
        error_log('✅ Gemini API Success - Model: ' . $model);
        return $data['candidates'][0]['content']['parts'][0]['text'];
    }

    error_log('❌ Gemini API: Unexpected response format');
    error_log('❌ Response data: ' . json_encode($data));
    return null;
}

// ===== FALLBACK ANTWORTEN =====
function getFallbackResponse($message) {
    $lower = strtolower($message);

    // PRIORISIERUNG: Arbeitgeber-Keywords ZUERST prüfen
    $employer_keywords = ['mitarbeiter', 'personal', 'team aufbauen', 'stelle besetzen', 'suche fachkraft'];
    foreach ($employer_keywords as $keyword) {
        if (strpos($lower, $keyword) !== false) {
            return 'Perfekt! Wir unterstützen Sie bei der Suche nach qualifizierten Fachkräften. Welche Position möchten Sie besetzen?';
        }
    }

    // Kandidaten-Keywords
    $candidate_keywords = ['job such', 'stelle such', 'karriere', 'bewerbung', 'arbeitsplatz'];
    foreach ($candidate_keywords as $keyword) {
        if (strpos($lower, $keyword) !== false) {
            return 'Ich helfe Ihnen gerne bei der Jobsuche! In welchem Bereich suchen Sie eine Position? Wir haben viele Stellen in IT, SAP und Engineering.';
        }
    }

    // Weitere spezifische Keywords
    if (strpos($lower, 'sap') !== false) {
        return 'SAP-Experten sind sehr gefragt! Ob S/4HANA, BTP oder ABAP - wir haben die richtigen Kandidaten.';
    }
    if (strpos($lower, 'entwickler') !== false || strpos($lower, 'developer') !== false) {
        return 'Entwickler sind unsere Stärke! Frontend, Backend oder Full-Stack - welche Expertise suchen Sie?';
    }
    if (strpos($lower, 'kontakt') !== false) {
        return 'Gerne! Sie erreichen uns unter info@noba-experts.de oder Tel: +49 211 123456';
    }

    return 'Interessant! Können Sie mir mehr Details geben, damit ich Ihnen besser helfen kann?';
}

// ===== HAUPTLOGIK =====
try {
    // Prüfe ob zusätzlicher Context benötigt wird
    $context_type = getRelevantContext($user_message);
    $enriched_message = $user_message;

    // SPEZIALBEHANDLUNG: Aktuelle Stellenangebote
    if (stripos($user_message, 'Aktuelle Stellenangebote') !== false ||
        stripos($user_message, 'Aktuelle Stellen') !== false ||
        stripos($user_message, '💼 Aktuelle Stellenangebote') !== false ||
        stripos($user_message, '💼 Aktuelle Stellen') !== false) {

        $jobs = fetchCurrentJobs();
        if ($jobs && count($jobs) > 0) {
            $jobs_text = "AKTUELLE STELLENANGEBOTE (Auszug):\n\n";
            foreach ($jobs as $idx => $job) {
                $jobs_text .= "🔹 " . $job['title'];
                if (!empty($job['details'])) {
                    $jobs_text .= "\n   📍 " . $job['details'];
                }
                $jobs_text .= "\n\n";
            }
            $jobs_text .= "⚠️ WICHTIG: Dies ist nur ein Auszug unserer aktuellen Vakanzen. Wir haben viele weitere Positionen, die nicht öffentlich ausgeschrieben sind.";

            // Injiziere Jobs als Context
            $enriched_message = "[CONTEXT-INFO: Der User möchte aktuelle Stellenangebote sehen. Präsentiere folgende Jobs freundlich und professionell:\n\n" . $jobs_text . "\n\nERWARTET: Präsentiere die Jobs übersichtlich, betone dass dies nur ein Auszug ist, und frage welche Position interessiert oder ob der User mehr erfahren möchte.]\n\nUser-Frage: " . $user_message;
            error_log('✨ Stellenangebote injiziert: ' . count($jobs) . ' Jobs');
        }
    }
    // Normale Context-Injektion
    elseif ($context_type) {
        $context_info = buildContextInfo($context_type);
        if ($context_info) {
            // Injiziere Context VOR die User-Nachricht
            $enriched_message = "[CONTEXT-INFO für deine Antwort:\n" . $context_info . "\n]\n\nUser-Frage: " . $user_message;
            error_log('✨ Context injiziert: ' . $context_type);
        }
    }

    // Versuche KI-Antwort zu bekommen (mit Gemini Flash Modell)
    $ai_response = callGeminiAI(
        $enriched_message,
        $conversation_history,
        $CONFIG['GOOGLE_AI_API_KEY'],
        $CONFIG['GEMINI_MODEL']
    );

    if ($ai_response) {
        // Erfolgreiche KI-Antwort
        $response = [
            'success' => true,
            'message' => $ai_response,
            'source' => 'ai',
            'model' => $CONFIG['GEMINI_MODEL'],
            'quick_replies' => generateQuickReplies($ai_response, $user_message, $conversation_history)
        ];
    } else {
        // Fallback zu vordefinierten Antworten
        $response = [
            'success' => true,
            'message' => getFallbackResponse($user_message),
            'source' => 'fallback',
            'info' => 'API Key möglicherweise nicht gesetzt. Siehe chatbot-api.php Zeile 14',
            'quick_replies' => generateQuickReplies(getFallbackResponse($user_message), $user_message, $conversation_history)
        ];
    }

    // Optional: Log für Analyse (anonymisiert)
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'message_length' => strlen($user_message),
        'response_source' => $response['source'],
        'session_id' => substr(md5($session_id), 0, 8) // Anonymisiert
    ];
    error_log('Chatbot Log: ' . json_encode($log_entry));

    // Antwort senden
    echo json_encode($response);

} catch (Exception $e) {
    error_log('Chatbot Error: ' . $e->getMessage());

    // Sicherer Fallback
    echo json_encode([
        'success' => true,
        'message' => getFallbackResponse($user_message),
        'source' => 'fallback'
    ]);
}

// ===== OPTIONAL: HUBSPOT INTEGRATION =====
// Kann hier auch direkt Leads speichern statt über JavaScript
function saveToHubSpot($data) {
    global $CONFIG;

    $url = 'https://api-eu1.hsforms.com/submissions/v3/integration/submit/'
         . $CONFIG['HUBSPOT_PORTAL_ID'] . '/' . $CONFIG['HUBSPOT_FORM_ID'];

    // HubSpot API Call...
}
?>
