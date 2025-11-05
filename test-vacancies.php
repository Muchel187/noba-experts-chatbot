<?php
// Test-Script um Vakanzen zu prüfen
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== VAKANZEN TEST ===\n\n";

// 1. Prüfe ob JSON-Datei existiert
$file = __DIR__ . '/vacancies.json';
echo "1. Datei-Check: $file\n";
if (file_exists($file)) {
    echo "   ✅ Datei existiert\n";
    echo "   Größe: " . filesize($file) . " bytes\n";
} else {
    echo "   ❌ Datei existiert NICHT\n";
    exit(1);
}

// 2. Lade JSON
echo "\n2. JSON laden...\n";
$content = file_get_contents($file);
echo "   Content-Länge: " . strlen($content) . " chars\n";

$data = json_decode($content, true);
if ($data === null) {
    echo "   ❌ JSON-Fehler: " . json_last_error_msg() . "\n";
    exit(1);
}

echo "   ✅ JSON erfolgreich geladen\n";
echo "   Anzahl Vakanzen: " . count($data) . "\n";

// 3. Zeige alle Vakanzen
echo "\n3. Vakanzen-Liste:\n";
foreach ($data as $idx => $vac) {
    echo "\n   Vakanz #" . ($idx + 1) . ":\n";
    echo "   - ID: " . ($vac['id'] ?? 'N/A') . "\n";
    echo "   - Titel: " . ($vac['title'] ?? 'N/A') . "\n";
    echo "   - Status: " . ($vac['status'] ?? 'N/A') . "\n";
    echo "   - Skills: " . implode(', ', array_slice($vac['required_skills'] ?? [], 0, 3)) . "\n";
}

// 4. Filtere aktive Vakanzen
$active = array_filter($data, fn($v) => ($v['status'] ?? 'active') === 'active');
echo "\n4. Aktive Vakanzen: " . count($active) . "\n";

echo "\n✅ TEST ERFOLGREICH\n";
?>
