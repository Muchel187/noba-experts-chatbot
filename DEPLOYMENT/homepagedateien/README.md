# 🚀 NOBA KI-Berater - Homepage Integration

## ✅ Deployment Status

**Backend & Frontend**: ✅ Erfolgreich deployed auf https://chatbot.noba-experts.de/

**Was funktioniert:**
- ✅ Chat-Interface
- ✅ Google Gemini API
- ✅ Quick Replies
- ✅ Document Upload (bis 10MB)
- ✅ Automatische E-Mail-Zusammenfassung
- ✅ Lead-Scoring & Datenextraktion
- ✅ PWA-Features (Installierbar)

---

## 📋 Homepage-Einbettung (www.noba-experts.de)

### Option 1: Vollständiger Embedding-Code (Empfohlen)

Kopiere den kompletten Code aus `embed-code.html` und füge ihn **vor dem schließenden `</body>` Tag** deiner Homepage ein.

**Vorteile:**
- Chat-Button unten rechts
- Vollbild-Modal beim Klick
- ESC-Taste zum Schließen
- Mobile-optimiert

### Option 2: Separate HTML-Datei

1. Kopiere `chatbot-embed.html` auf deinen Homepage-Webspace
2. Füge auf deiner Homepage ein:

```html
<iframe
    src="/chatbot-embed.html"
    style="position: fixed; bottom: 0; right: 0; width: 100px; height: 100px; border: none; z-index: 9999;"
></iframe>
```

### Option 3: Direkter iframe-Link

```html
<iframe
    src="https://chatbot.noba-experts.de/"
    style="width: 100%; height: 600px; border: none;"
    title="NOBA KI-Berater"
></iframe>
```

---

## 🔧 Test-Checkliste

Öffne https://chatbot.noba-experts.de/ im Browser und teste:

### 1. Grundfunktionen
- [ ] Chat öffnet sich
- [ ] GDPR-Modal erscheint
- [ ] Nachricht senden funktioniert
- [ ] Bot antwortet
- [ ] **Quick Replies erscheinen** ⭐

### 2. Quick Replies
- [ ] Initial: "Job suchen", "Mitarbeiter finden", "CV optimieren", "Unsere Services"
- [ ] Nach "Job suchen": Bereichs-Buttons (IT/Software, Engineering, etc.)
- [ ] Nach 2+ Nachrichten: "Kostenloses Beratungsgespräch" erscheint

### 3. Document Upload
- [ ] "CV hochladen" sagen → Upload Widget erscheint
- [ ] PDF/DOC hochladen (max 10MB)
- [ ] Bot analysiert Dokument und fragt weiter

### 4. E-Mail-Funktionalität
- [ ] Chat verlassen/Tab wechseln
- [ ] E-Mail sollte an Jurak.Bahrambaek@noba-experts.de gesendet werden
- [ ] E-Mail enthält:
  - Lead-Score
  - Extrahierte Kontaktdaten
  - Vollständige Konversation
  - Document Context (falls vorhanden)

### 5. Meeting-Integration
- [ ] Menü öffnen (⋮ Button)
- [ ] "Termin vereinbaren" → HubSpot Calendly öffnet sich

---

## 📧 E-Mail-Konfiguration

Die automatischen E-Mails werden gesendet an:
```
Jurak.Bahrambaek@noba-experts.de
```

**Wann wird gesendet?**
- Bei jedem Konversation mit mindestens 2 Nachrichten
- Beim Verlassen der Seite (`beforeunload`)
- Beim Tab-Wechsel (`visibilitychange`)

**E-Mail enthält:**
- 📊 Lead-Score (0-100)
- 📧 Extrahierte Kontaktdaten (Name, E-Mail, Telefon, Firma)
- 💼 Lead-Typ (Arbeitgeber/Kandidat)
- 💻 Position & Tech-Stack
- 💬 Vollständige Konversation
- 📎 Hochgeladene Dokumente (falls vorhanden)

---

## 🔄 Automatische Datei-Löschung (DSGVO)

### Hochgeladene CVs werden automatisch gelöscht:
1. **Nach E-Mail-Versand** - Sofort nach erfolgreicher E-Mail
2. **Nach 24 Stunden** - Cleanup-Script löscht alte Dateien

### Cleanup-Script einrichten (Cronjob):

```bash
# SSH auf Server
ssh root@91.98.123.193

# Cronjob erstellen
crontab -e

# Diese Zeile hinzufügen (läuft jede Stunde):
0 * * * * php /var/www/chatbot-noba/cleanup-uploads.php >> /var/log/chatbot-cleanup.log 2>&1
```

---

## 📱 PWA Installation

Nutzer können den Chatbot als App installieren:

### Desktop:
1. Auf https://chatbot.noba-experts.de/ gehen
2. Chrome zeigt "Installieren" Button in Adressleiste
3. Klicken → App wird als eigenständige Anwendung installiert

### Mobile (Android):
1. Auf https://chatbot.noba-experts.de/ gehen
2. Chrome Menü → "Zum Startbildschirm hinzufügen"
3. App läuft wie native Android-App

### Mobile (iOS):
1. In Safari öffnen
2. "Teilen" → "Zum Home-Bildschirm"
3. App läuft wie native iOS-App

---

## 🛠️ Konfiguration anpassen

### Backend-Einstellungen (auf Server):

```bash
ssh root@91.98.123.193
nano /var/www/chatbot-noba/chatbot-api.php
```

**Änderbar:**
- API Key (Zeile 65): `'GOOGLE_AI_API_KEY' => '...'`
- Model (Zeile 72): `'GEMINI_MODEL' => 'gemini-2.5-flash-lite'`
- Max Message Length (Zeile 76): `'MAX_MESSAGE_LENGTH' => 500000`

### E-Mail-Empfänger ändern:

```bash
nano /var/www/chatbot-noba/send-summary.php
# Zeile 355: $adminEmail = 'DEINE@EMAIL.de';
```

### Frontend-Konfiguration:

Auf deinem lokalen Rechner:
```bash
cd "/home/jbk/Homepage Git/Chatbot final"
nano src/constants/config.ts
```

Dann neu builden und hochladen:
```bash
npm run build
scp -r dist/* root@91.98.123.193:/var/www/chatbot-noba/
```

---

## 🎨 Anpassungen

### Farben ändern (Brand Colors):
- Orange: `#FF7B29`
- Orange Dark: `#e66b24`

### Texte anpassen:
- System-Prompt: `src/constants/systemPrompt.ts`
- Welcome-Message: `src/App.tsx` (Zeile 195)
- Button-Texte: Direkt in Components

---

## 🐛 Troubleshooting

### Quick Replies erscheinen nicht?
```bash
# Browser-Console öffnen (F12)
# Suche nach "🎯 Quick replies state changed"
# Sollte zeigen: Array(4)
```

### E-Mails kommen nicht an?
```bash
# Server-Logs prüfen:
ssh root@91.98.123.193
tail -f /var/log/nginx/chatbot.noba-experts.de.error.log
```

### Uploads funktionieren nicht?
```bash
# Berechtigungen prüfen:
ssh root@91.98.123.193
ls -la /var/www/chatbot-noba/uploads/
# Sollte sein: drwxr-xr-x www-data www-data
```

### API antwortet nicht?
```bash
# API direkt testen:
curl -X POST https://chatbot.noba-experts.de/chatbot-api.php \
  -H "Content-Type: application/json" \
  -d '{"message":"Test","history":[],"session_id":"test"}'
```

---

## 📊 Monitoring

### Server-Logs:

```bash
# Nginx Access Log (alle Requests):
tail -f /var/log/nginx/chatbot.noba-experts.de.access.log

# Nginx Error Log (nur Fehler):
tail -f /var/log/nginx/chatbot.noba-experts.de.error.log

# PHP Errors:
tail -f /var/log/php8.3-fpm.log
```

### Konversationen anzeigen:

```bash
ssh root@91.98.123.193
cat /var/www/chatbot-noba/chatbot-conversations.json | jq '.[-1]' # Letzte Konversation
```

---

## 🔐 Security

### Bereits implementiert:
- ✅ HTTPS (Let's Encrypt SSL)
- ✅ CORS auf spezifische Domains beschränkt
- ✅ IP-Anonymisierung (DSGVO)
- ✅ Input-Validation (XSS-Schutz)
- ✅ File-Upload nur PDF/DOC/DOCX
- ✅ Max 10MB Upload-Limit
- ✅ Rate-Limiting (30 Requests/Minute)
- ✅ Automatische Datei-Löschung
- ✅ Session-basierte Auth

### Empfohlene Zusatzmaßnahmen:
- [ ] WAF (Web Application Firewall)
- [ ] Fail2Ban für Brute-Force-Schutz
- [ ] Backup-Strategie für chatbot-conversations.json
- [ ] Monitoring mit Prometheus/Grafana

---

## 📞 Support

**Bei Problemen:**
1. Browser-Console öffnen (F12) → Nach Fehlern suchen
2. Server-Logs prüfen (siehe Monitoring)
3. API direkt testen (siehe Troubleshooting)

**Kontakt:**
- E-Mail: Jurak.Bahrambaek@noba-experts.de
- Telefon: +49 211 975 324 74

---

## 🎉 Deployment erfolgreich!

**Live-URL:** https://chatbot.noba-experts.de/

**Nächste Schritte:**
1. [ ] Embedding-Code auf www.noba-experts.de einfügen
2. [ ] Cronjob für Cleanup-Script einrichten
3. [ ] E-Mail-Empfang testen
4. [ ] Quick Replies testen
5. [ ] Document Upload testen
6. [ ] Mit Team testen

---

**Version:** 2.0.0 Final
**Deployment-Datum:** 31.10.2025
**Server:** Hetzner (91.98.123.193)
**Domain:** chatbot.noba-experts.de
