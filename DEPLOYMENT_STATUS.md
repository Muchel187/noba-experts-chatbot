# NOBA Chatbot - Deployment Status & Next Steps

**Datum:** 01.11.2025 23:40 Uhr
**Problem:** Neue Konversationen werden nicht im Admin-Dashboard angezeigt

---

## 🔴 AKTUELLES PROBLEM

**Symptom:**
- Chatbot versendet E-Mails erfolgreich ✅
- Neue Konversationen erscheinen NICHT im Admin-Dashboard ❌
- Backend hat nur 92 Konversationen statt 400+

**Root Cause:**
Das Frontend-Build enthält die falsche Backend-URL (`http://localhost:8080` statt `https://chatbot.noba-experts.de`), wodurch Logger-Requests ins Leere gehen.

---

## ✅ BEREITS BEHOBEN (deployed auf Server)

### Backend-Fixes
1. **chatbot-logger.php**
   - Memory-Limit erhöht auf 256M (Zeile 9)
   - Error-Logging für json_decode Fehler hinzugefügt (Zeile 59-70)
   - Emergency-Backup bei Decode-Fehlern implementiert
   - ✅ Deployed: `/var/www/chatbot-noba/backend/chatbot-logger.php`
   - ✅ Deployed: `/var/www/chatbot-noba/chatbot-logger.php`

2. **Konversationsdaten**
   - 92 unique Sessions wiederhergestellt aus Backup
   - Duplikate entfernt (von 428 → 92 unique)
   - ✅ Datei: `/var/www/chatbot-noba/chatbot-conversations.json`

3. **admin-api.php**
   - HubSpot Integration funktioniert
   - Batch Operations funktionieren
   - ✅ Deployed: `/var/www/chatbot-noba/backend/admin-api.php`

### Frontend-Fixes (Code korrigiert, aber NOCH NICHT deployed)
1. **src/constants/config.ts**
   - Zeile 12: `backendBaseUrl: 'https://chatbot.noba-experts.de'`
   - ✅ Lokal korrigiert, ❌ NICHT deployed

2. **.env Files**
   - `.env` → `VITE_BACKEND_BASE_URL=https://chatbot.noba-experts.de`
   - `.env.local` → `VITE_BACKEND_BASE_URL=https://chatbot.noba-experts.de`
   - ✅ Lokal korrigiert

---

## 🔧 WAS NOCH ZU TUN IST

### 1. Frontend neu builden
```bash
cd "/home/jbk/Homepage Git/Chatbot final"

# Clean build
rm -rf dist
rm -rf node_modules/.vite

# Build
npm run build
```

### 2. URL im Bundle verifizieren
```bash
cd "/home/jbk/Homepage Git/Chatbot final"

# Check ob richtige URL im Bundle ist
grep -o "https://chatbot.noba-experts.de" dist/assets/*.js | head -1
# ERWARTE: dist/assets/index-XXXXX.js:https://chatbot.noba-experts.de

# Check ob falsche URL noch drin ist
grep -o "http://localhost:8080" dist/assets/*.js
# ERWARTE: Keine Ausgabe (exit code 1)
```

**WICHTIG:** Falls `localhost:8080` immer noch im Bundle ist:
```bash
# Hardcoded URL ist bereits in src/constants/config.ts:12 gesetzt
# Falls Vite immer noch die alte URL nimmt, prüfe:
cat src/constants/config.ts | grep backendBaseUrl
# MUSS zeigen: backendBaseUrl: 'https://chatbot.noba-experts.de',
```

### 3. Deploy auf Server
```bash
cd "/home/jbk/Homepage Git/Chatbot final"

# Deploy Frontend
scp -r dist/* root@91.98.123.193:/var/www/chatbot-noba/

# Verify auf Server
ssh root@91.98.123.193 "ls -la /var/www/chatbot-noba/assets/ | head -5"
```

### 4. Tests durchführen

#### Test 1: Browser Cache leeren
```bash
# Im Browser:
# 1. Öffne https://chatbot.noba-experts.de
# 2. Hard Refresh: Ctrl + Shift + R (Linux/Windows) oder Cmd + Shift + R (Mac)
# 3. DevTools öffnen (F12) → Network Tab
```

#### Test 2: Logger-Request prüfen
```javascript
// Im Browser Console:
console.log(window.location.origin);
// ERWARTE: https://chatbot.noba-experts.de

// Starte eine Test-Konversation und prüfe Network Tab
// ERWARTE: POST https://chatbot.noba-experts.de/chatbot-logger.php
// NICHT: POST http://localhost:8080/chatbot-logger.php
```

#### Test 3: Konversation erstellen
1. Öffne https://chatbot.noba-experts.de
2. Starte Chatbot
3. Sende Nachricht: "Ich suche einen Job als Entwickler"
4. Gib Email an: "test@example.com"
5. Schließe Chat

#### Test 4: Admin Dashboard prüfen
1. Öffne https://chatbot.noba-experts.de/admin/
2. Login mit Credentials
3. **ERWARTE:** Neue Test-Konversation erscheint in der Liste
4. Prüfe Session-ID und Timestamp

---

## 📂 WICHTIGE DATEIPFADE

### Lokal
- **Frontend Source:** `/home/jbk/Homepage Git/Chatbot final/`
- **Config:** `/home/jbk/Homepage Git/Chatbot final/src/constants/config.ts`
- **Build Output:** `/home/jbk/Homepage Git/Chatbot final/dist/`
- **Env Files:**
  - `/home/jbk/Homepage Git/Chatbot final/.env`
  - `/home/jbk/Homepage Git/Chatbot final/.env.local`

### Server
- **Frontend:** `/var/www/chatbot-noba/`
- **Backend:** `/var/www/chatbot-noba/backend/`
- **Conversations:** `/var/www/chatbot-noba/chatbot-conversations.json`
- **Logger:**
  - `/var/www/chatbot-noba/chatbot-logger.php` (primary)
  - `/var/www/chatbot-noba/backend/chatbot-logger.php` (secondary)

---

## 🐛 DEBUGGING

### Falls Konversationen immer noch nicht erscheinen:

1. **Prüfe Browser Network Tab**
   ```
   Filter: chatbot-logger
   ERWARTE: Status 200
   NICHT: Status 0 (blocked) oder 404
   ```

2. **Prüfe Server Logs**
   ```bash
   ssh root@91.98.123.193 "tail -50 /var/log/php8.3-fpm.log | grep -E '(Loaded|JSON|ERROR)'"
   ```

3. **Prüfe ob Session gespeichert wurde**
   ```bash
   ssh root@91.98.123.193 "cd /var/www/chatbot-noba && php -r 'echo count(json_decode(file_get_contents(\"chatbot-conversations.json\"), true)) . \" sessions\n\";'"
   # ERWARTE: 93+ sessions (92 alte + neue Test-Session)
   ```

4. **Prüfe Session direkt**
   ```bash
   ssh root@91.98.123.193 "cd /var/www/chatbot-noba && tail -100 chatbot-conversations.json | grep -A5 -B5 'test@example.com'"
   # ERWARTE: Die Test-Konversation mit E-Mail
   ```

### Falls Frontend URL immer noch falsch ist:

**Option A: Build Cache Problem**
```bash
cd "/home/jbk/Homepage Git/Chatbot final"
rm -rf dist node_modules/.vite .vite
npm cache clean --force
npm install
npm run build
```

**Option B: Environment Variable Problem**
```bash
# Prüfe alle .env Files
cat .env
cat .env.local
cat .env.production 2>/dev/null || echo "Keine .env.production"

# Alle sollten zeigen:
# VITE_BACKEND_BASE_URL=https://chatbot.noba-experts.de
```

**Option C: Hardcoded URL wird ignoriert**
```bash
# Prüfe ob config.ts korrekt ist
cat src/constants/config.ts | grep -A2 "endpoints:"
# MUSS zeigen:
# endpoints: {
#   backendBaseUrl: 'https://chatbot.noba-experts.de',
```

---

## 📊 EXPECTED STATE NACH FIX

### Backend Server (`/var/www/chatbot-noba/`)
```
✅ chatbot-conversations.json: 92+ Sessions
✅ chatbot-logger.php: Memory 256M, Error-Logging aktiv
✅ admin-api.php: HubSpot + Batch Operations funktionieren
✅ Assets: Neues Frontend mit richtiger URL
```

### Browser
```
✅ Frontend lädt von: https://chatbot.noba-experts.de
✅ Logger-Requests gehen an: https://chatbot.noba-experts.de/chatbot-logger.php
✅ Neue Konversationen erscheinen im Admin-Dashboard
✅ Emails werden weiterhin versendet
```

---

## 🆘 FALLBACK

Falls alles fehlschlägt und du schnell eine funktionierende Version brauchst:

```bash
# 1. Letzter funktionierender Commit
cd "/home/jbk/Homepage Git/Chatbot final"
git log --oneline --all -10
# Identifiziere letzten Commit wo es funktionierte

# 2. Checkout zu diesem Commit
git checkout <commit-hash>

# 3. Build und Deploy
npm run build
scp -r dist/* root@91.98.123.193:/var/www/chatbot-noba/

# 4. Zurück zu master
git checkout master
```

---

## ✅ SUCCESS CRITERIA

Das Problem ist behoben wenn:
1. ✅ Neue Test-Konversation erscheint im Admin-Dashboard
2. ✅ Session-Count auf Server steigt (93+)
3. ✅ Network Tab zeigt: `POST https://chatbot.noba-experts.de/chatbot-logger.php` → Status 200
4. ✅ Emails werden weiterhin versendet
5. ✅ HubSpot Sync funktioniert

---

## 📝 NOTES

- **Bash hatte Fehler** in der letzten Session → Daher keine automatisierten Tests möglich
- **Backend funktioniert** → Emails werden versendet
- **Nur Frontend-Build und Deploy fehlt** → config.ts ist bereits korrigiert
- **Cache-Problem** → Vite cached aggressiv, daher `rm -rf dist node_modules/.vite` vor Build

---

**Erstellt:** 2025-11-01 23:40
**Status:** ⏳ Warten auf Frontend-Build und Deploy
**Next:** Build → Verify → Deploy → Test
