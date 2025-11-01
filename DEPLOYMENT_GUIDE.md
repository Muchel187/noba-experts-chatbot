# NOBA Chatbot - Deployment Guide

## ✅ Erfolgreich deployed!

**Git-Commit:** `9359592`
**Datum:** 2025-11-01
**Branch:** master

---

## 🚀 Was wurde deployed:

### 1. Admin Dashboard API (`backend/admin-api.php`)
- Vollständige HubSpot-Integration
- KI-Analyse automatisch zu HubSpot
- Task/Reminder-Erstellung
- E-Mail-Benachrichtigungen

### 2. Lead-Klassifizierung (`backend/chatbot-logger.php`)
- Erweiterte Kunde/Kandidat-Erkennung
- 30+ neue Keywords
- Kontext-basierte Analyse

### 3. E-Mail-Templates (`backend/send-summary.php`)
- Verbesserte Lead-Typ-Anzeige
- "Kunde (sucht Mitarbeiter)" vs "Kandidat (sucht Job)"

### 4. HubSpot-Konfiguration
- Token sicher in `backend/hubspot-config.php` (NICHT im Git!)
- Automatische Fallback auf Umgebungsvariablen

---

## 📋 Manuelle Schritte nach Deployment:

### 1. HubSpot-Token auf Server konfigurieren

**Datei erstellen:** `/backend/hubspot-config.php`

```php
<?php
define('HUBSPOT_ACCESS_TOKEN', 'IHR_HUBSPOT_TOKEN_HIER');
define('HUBSPOT_PORTAL_ID', '146015266');
```

**Token ersetzen mit:** `pat-eu1-920cc08e-...` (Token wurde Ihnen separat mitgeteilt)

**WICHTIG:** Diese Datei ist in `.gitignore` und wird NICHT ins Git committed!

### 2. Berechtigungen prüfen

```bash
chmod 644 /backend/hubspot-config.php
chmod 755 /backend/admin-api.php
```

### 3. Test durchführen

1. Admin-Dashboard öffnen: `https://chatbot.noba-experts.de/admin`
2. Login mit Admin-Credentials
3. KI-Analyse für einen Lead ausführen
4. Prüfen:
   - ✅ Kontakt in HubSpot erstellt
   - ✅ KI-Analyse als Notiz vorhanden
   - ✅ Task/Reminder erstellt
   - ✅ E-Mail-Benachrichtigung erhalten

---

## 🔧 Troubleshooting

### Problem: "HubSpot API nicht konfiguriert"
**Lösung:** `hubspot-config.php` fehlt auf dem Server
```bash
# Datei erstellen und Token eintragen
nano /backend/hubspot-config.php
```

### Problem: "Missing session_id"
**Lösung:** API unterstützt jetzt GET & POST - Browser-Cache leeren

### Problem: Alle Leads als "Kandidat"
**Lösung:** Bestehende Konversationen müssen neu analysiert werden
- Backend löscht alte Daten nicht automatisch
- Neue Konversationen verwenden neue Klassifizierung

---

## 📊 Verifizierung

Nach dem Deployment prüfen:

```bash
# PHP-Syntax prüfen
php -l /backend/admin-api.php
php -l /backend/chatbot-logger.php

# Logs prüfen
tail -f /var/log/php-errors.log
```

---

## 🔐 Sicherheit

✅ HubSpot-Token NICHT im Git
✅ Token in separater Config-Datei
✅ .gitignore enthält hubspot-config.php
✅ Fallback auf Umgebungsvariablen

---

## 📝 Nächste Schritte

1. ✅ Server-Deployment durchführen
2. ⏳ hubspot-config.php auf Server erstellen
3. ⏳ Tests im Admin-Dashboard durchführen
4. ⏳ Erste KI-Analyse mit HubSpot-Sync testen
5. ⏳ E-Mail-Benachrichtigung verifizieren

---

## 🆘 Support

Bei Problemen:
- Logs prüfen: `/var/log/php-errors.log`
- Test-Report: `backend/TEST_RESULTS.md`
- Git-Commit: `9359592`

**Status: DEPLOYED ✅**
