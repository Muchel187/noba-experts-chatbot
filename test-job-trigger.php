<?php
// Debug Script - Prüfe Job-Trigger-Logik

$user_message = "Welche aktuellen Stellen haben Sie offen?";

echo "=== JOB-TRIGGER DEBUG ===\n\n";
echo "Message: $user_message\n\n";

$triggers = [
    'Aktuelle Stellenangebote',
    'Aktuelle Stellen',
    '💼 Aktuelle Stellenangebote',
    '💼 Aktuelle Stellen',
    '💼 Aktuelle Jobs & Projekte',
    'Aktuelle Jobs',
    'offene Stellen',
    'offene Jobs',
    'welche Stellen',
    'welche Jobs',
    'freie Stellen'
];

echo "Einzelne Trigger-Tests:\n";
foreach ($triggers as $trigger) {
    $match = stripos($user_message, $trigger) !== false;
    echo "  - '$trigger': " . ($match ? "✅ MATCH" : "❌ kein Match") . "\n";
}

echo "\nKombinierte Trigger-Tests:\n";
$test1 = (stripos($user_message, 'stellen') !== false && stripos($user_message, 'haben Sie') !== false);
echo "  - stellen + haben Sie: " . ($test1 ? "✅ MATCH" : "❌ kein Match") . "\n";

$test2 = (stripos($user_message, 'stellen') !== false && stripos($user_message, 'gibt es') !== false);
echo "  - stellen + gibt es: " . ($test2 ? "✅ MATCH" : "❌ kein Match") . "\n";

$test3 = (stripos($user_message, 'jobs') !== false && stripos($user_message, 'verfügbar') !== false);
echo "  - jobs + verfügbar: " . ($test3 ? "✅ MATCH" : "❌ kein Match") . "\n";

// Gesamte Bedingung
$condition = (
    stripos($user_message, 'Aktuelle Stellenangebote') !== false ||
    stripos($user_message, 'Aktuelle Stellen') !== false ||
    stripos($user_message, '💼 Aktuelle Stellenangebote') !== false ||
    stripos($user_message, '💼 Aktuelle Stellen') !== false ||
    stripos($user_message, '💼 Aktuelle Jobs & Projekte') !== false ||
    stripos($user_message, 'Aktuelle Jobs') !== false ||
    stripos($user_message, 'offene Stellen') !== false ||
    stripos($user_message, 'offene Jobs') !== false ||
    stripos($user_message, 'welche Stellen') !== false ||
    stripos($user_message, 'welche Jobs') !== false ||
    stripos($user_message, 'freie Stellen') !== false ||
    (stripos($user_message, 'stellen') !== false && stripos($user_message, 'haben Sie') !== false) ||
    (stripos($user_message, 'stellen') !== false && stripos($user_message, 'gibt es') !== false) ||
    (stripos($user_message, 'jobs') !== false && stripos($user_message, 'verfügbar') !== false)
);

echo "\n=== GESAMT-BEDINGUNG: " . ($condition ? "✅ TRUE - Sollte Jobs zeigen!" : "❌ FALSE - Zeigt keine Jobs") . " ===\n";
?>
