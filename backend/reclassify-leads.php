<?php
/**
 * NOBA Experts - Lead Re-Klassifizierung
 * Aktualisiert alle bestehenden Konversationen mit neuer Lead-Typ-Logik
 */

echo "🔄 NOBA Lead Re-Klassifizierung\n";
echo "================================\n\n";

// Konversationen laden
$conversationsFile = __DIR__ . '/chatbot-conversations.json';

if (!file_exists($conversationsFile)) {
    die("❌ Fehler: chatbot-conversations.json nicht gefunden!\n");
}

$data = json_decode(file_get_contents($conversationsFile), true);

if (!$data) {
    die("❌ Fehler: Konversationen konnten nicht gelesen werden!\n");
}

echo "📊 Gefundene Konversationen: " . count($data) . "\n\n";

// Neue Lead-Typ-Erkennung (aus chatbot-logger.php)
function detectLeadType($messages) {
    foreach ($messages as $msg) {
        if (($msg['role'] ?? '') !== 'user') continue;

        $text = $msg['text'] ?? '';

        // KUNDE/ARBEITGEBER Keywords
        if (preg_match('/(suche|brauche|benötige|gesucht|hiring|rekrutierung|einstellen).*(mitarbeiter|entwickler|engineer|fachkraft|personal|team|spezialist|experte)/i', $text)) {
            return 'employer';
        }
        // Zusätzliche Kunde-Patterns
        elseif (preg_match('/(projekt|auftrag|team erweitern|verstärkung|vakanz|offene stelle|besetzt werden)/i', $text)) {
            return 'employer';
        }
        // Zusätzlich: Wenn jemand "für mein Unternehmen" oder "für unsere Firma" sagt
        elseif (preg_match('/(für mein|für unser|für die).*(unternehmen|firma|projekt|team)/i', $text)) {
            return 'employer';
        }
        // KANDIDAT Keywords
        elseif (preg_match('/(suche|interesse|bewerbe|interessiere|möchte).*(job|stelle|position|arbeit|anstellung|karriere)/i', $text)) {
            return 'candidate';
        }
        // Zusätzliche Kandidat-Patterns
        elseif (preg_match('/(bin|arbeite als|erfahrung als|skills in|kann ich|mein lebenslauf|meine kenntnisse)/i', $text)) {
            return 'candidate';
        }
    }

    // Default: Wenn kein Typ erkannt wurde, prüfe Kontext
    foreach ($messages as $msg) {
        if (($msg['role'] ?? '') !== 'user') continue;
        $text = $msg['text'] ?? '';

        // Wenn jemand nach Dienstleistungen oder Vermittlung fragt = wahrscheinlich Kunde
        if (preg_match('/(vermittlung|dienstleistung|können sie|bieten sie|zeitarbeit|freelancer finden)/i', $text)) {
            return 'employer';
        }
    }

    return null;
}

// Statistiken
$stats = [
    'total' => count($data),
    'updated' => 0,
    'employer' => 0,
    'candidate' => 0,
    'unknown' => 0,
    'unchanged' => 0
];

// Alle Konversationen durchgehen
foreach ($data as &$conversation) {
    $oldType = $conversation['extracted_data']['lead_type'] ?? null;
    $newType = detectLeadType($conversation['messages'] ?? []);

    // WICHTIG: Setze lead_type IMMER (auch auf null wenn unbekannt)
    $conversation['extracted_data']['lead_type'] = $newType;

    if ($oldType !== $newType) {
        $stats['updated']++;
        $oldTypeName = $oldType === 'employer' ? 'Kunde' : ($oldType === 'candidate' ? 'Kandidat' : 'Unbekannt');
        $newTypeName = $newType === 'employer' ? 'Kunde' : ($newType === 'candidate' ? 'Kandidat' : 'Unbekannt');

        $name = $conversation['extracted_data']['name'] ?? $conversation['session_id'] ?? 'Unbekannt';
        $sessionShort = substr($conversation['session_id'] ?? '', 0, 8);
        echo "✅ {$sessionShort}: {$oldTypeName} → {$newTypeName}\n";
    } else {
        $stats['unchanged']++;
    }

    if ($newType === 'employer') {
        $stats['employer']++;
    } elseif ($newType === 'candidate') {
        $stats['candidate']++;
    } else {
        $stats['unknown']++;
    }
}

// Speichern
file_put_contents($conversationsFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "\n================================\n";
echo "✅ Re-Klassifizierung abgeschlossen!\n\n";
echo "📊 Statistiken:\n";
echo "   Gesamt: {$stats['total']}\n";
echo "   Aktualisiert: {$stats['updated']}\n";
echo "   Unverändert: {$stats['unchanged']}\n";
echo "   Kunden: {$stats['employer']}\n";
echo "   Kandidaten: {$stats['candidate']}\n";
echo "   Unbekannt: {$stats['unknown']}\n\n";

echo "🎉 Fertig! Bitte Admin-Dashboard neu laden.\n";
