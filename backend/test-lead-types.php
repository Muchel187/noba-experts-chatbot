<?php
/**
 * Lead-Type Test Tool
 * Zeigt aktuelle Lead-Verteilung an
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$conversationsFile = __DIR__ . '/chatbot-conversations.json';

if (!file_exists($conversationsFile)) {
    die(json_encode(['error' => 'Keine Konversationen gefunden']));
}

$conversations = json_decode(file_get_contents($conversationsFile), true);

$stats = [
    'total' => count($conversations),
    'by_type' => [
        'employer' => 0,
        'candidate' => 0,
        'null' => 0,
        'unknown' => 0
    ],
    'samples' => []
];

foreach ($conversations as $conv) {
    $leadType = $conv['extracted_data']['lead_type'] ?? null;

    if ($leadType === 'employer') {
        $stats['by_type']['employer']++;
    } elseif ($leadType === 'candidate') {
        $stats['by_type']['candidate']++;
    } elseif ($leadType === null) {
        $stats['by_type']['null']++;
    } else {
        $stats['by_type']['unknown']++;
    }

    // Sample für Debugging
    if (count($stats['samples']) < 10) {
        $stats['samples'][] = [
            'session' => substr($conv['session_id'], 0, 8),
            'lead_type' => $leadType,
            'name' => $conv['extracted_data']['name'] ?? 'N/A',
            'email' => $conv['extracted_data']['email'] ?? 'N/A'
        ];
    }
}

echo json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
