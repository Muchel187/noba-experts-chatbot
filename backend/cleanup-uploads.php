<?php
/**
 * CLEANUP SCRIPT für hochgeladene Dateien
 *
 * Löscht automatisch alte Uploads die nicht mehr benötigt werden.
 * DSGVO-konform: Dateien werden nach 24 Stunden automatisch gelöscht.
 *
 * Kann als Cronjob ausgeführt werden:
 * 0 * * * * php /pfad/zu/cleanup-uploads.php
 */

// Uploads-Verzeichnis
$uploadsDir = __DIR__ . '/uploads/';

// Zeitlimit: 24 Stunden (in Sekunden)
$maxAge = 24 * 60 * 60;

// Prüfe ob Verzeichnis existiert
if (!is_dir($uploadsDir)) {
    echo "Uploads-Verzeichnis existiert nicht: $uploadsDir\n";
    exit(1);
}

// Log-Start
$now = date('Y-m-d H:i:s');
echo "🧹 CLEANUP START: $now\n";

$deletedCount = 0;
$totalSize = 0;
$errors = 0;

// Durchsuche Verzeichnis
$files = glob($uploadsDir . '*');

foreach ($files as $file) {
    // Überspringe Verzeichnisse und versteckte Dateien
    if (!is_file($file) || basename($file)[0] === '.') {
        continue;
    }

    $fileAge = time() - filemtime($file);
    $fileName = basename($file);
    $fileSize = filesize($file);

    // Prüfe Alter
    if ($fileAge > $maxAge) {
        $ageHours = round($fileAge / 3600, 1);

        if (unlink($file)) {
            $deletedCount++;
            $totalSize += $fileSize;
            echo "✅ Gelöscht: $fileName (Alter: {$ageHours}h, Größe: " . formatBytes($fileSize) . ")\n";
            error_log("🗑️ CLEANUP: Datei gelöscht: $fileName (Alter: {$ageHours}h)");
        } else {
            $errors++;
            echo "❌ FEHLER: Konnte nicht löschen: $fileName\n";
            error_log("⚠️ CLEANUP ERROR: Konnte Datei nicht löschen: $fileName");
        }
    } else {
        $remainingHours = round(($maxAge - $fileAge) / 3600, 1);
        echo "⏳ Behalten: $fileName (Noch {$remainingHours}h bis Löschung)\n";
    }
}

// Zusammenfassung
echo "\n📊 CLEANUP ZUSAMMENFASSUNG:\n";
echo "   Gelöschte Dateien: $deletedCount\n";
echo "   Freigegebener Speicher: " . formatBytes($totalSize) . "\n";
echo "   Fehler: $errors\n";
echo "   Fertig: " . date('Y-m-d H:i:s') . "\n";

// Log für Monitoring
error_log("🧹 CLEANUP: $deletedCount Dateien gelöscht (" . formatBytes($totalSize) . "), $errors Fehler");

/**
 * Formatiere Bytes in lesbare Größe
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

?>
