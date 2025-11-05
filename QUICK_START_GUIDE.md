# 📖 Schnellanleitung: Vakanzen & Kandidaten Management

## 🎯 Übersicht

Das Admin-Dashboard wurde um zwei neue Funktionen erweitert:
- **💼 Vakanzen-Management** - Stellenangebote verwalten
- **👤 Kandidatenprofile-Management** - CVs verwalten

Beide Systeme sind DSGVO-konform und nutzen KI zur automatischen Anonymisierung.

---

## 🚀 Schnellstart

### 1. Admin-Dashboard öffnen
```
URL: https://chatbot.noba-experts.de/admin/
```

### 2. Navigation
- **📊 Leads** - Bestehende Chatbot-Leads (wie bisher)
- **💼 Vakanzen** - NEU: Stellenangebote verwalten
- **👤 Kandidaten** - NEU: Kandidatenprofile verwalten

---

## 💼 Vakanzen hochladen

### Option 1: Datei hochladen
1. Klicke auf Tab "Vakanzen"
2. Klicke "Neue Vakanz hochladen"
3. Drag & Drop oder Datei auswählen (PDF, DOCX, TXT)
4. Warte auf KI-Verarbeitung (ca. 5-10 Sekunden)
5. ✅ Vakanz ist gespeichert und anonymisiert

### Option 2: Text direkt eingeben
1. Klicke auf Tab "Vakanzen"
2. Klicke "Neue Vakanz hochladen"
3. Wähle "Text eingeben"
4. Füge Stellenbeschreibung ein
5. Klicke "Hochladen"
6. ✅ Vakanz ist gespeichert und anonymisiert

### Was passiert automatisch?
- ❌ Firmennamen werden entfernt
- ❌ Kontaktdaten (E-Mail, Telefon) werden entfernt
- ❌ Spezifische Standorte werden auf Region reduziert
- ✅ KI extrahiert: Titel, Skills, Experience Level, Gehalt, etc.
- ✅ Vakanz wird in Datenbank gespeichert
- ✅ Chatbot kennt die Vakanz sofort

### Beispiel-Stellenbeschreibung:
```
Senior PHP Developer (m/w/d)

TechCorp GmbH, München sucht:
- 5+ Jahre PHP-Erfahrung
- Skills: Laravel, MySQL, Docker, AWS
- Gehalt: 65-85k EUR
- Remote-Hybrid (2 Tage Büro)

Bewerbung an: hr@techcorp.de
```

**Wird automatisch zu:**
```
Position: Senior PHP Developer
Skills: PHP, Laravel, MySQL, Docker, AWS
Level: Senior
Location: Raum München
Salary: 65.000-85.000 EUR
Remote: Hybrid
```

---

## 👤 Kandidaten hochladen

### Option 1: CV hochladen
1. Klicke auf Tab "Kandidaten"
2. Klicke "Neues Kandidatenprofil hochladen"
3. Drag & Drop oder Datei auswählen (PDF, DOCX, TXT)
4. Warte auf KI-Verarbeitung (ca. 5-10 Sekunden)
5. ✅ Kandidat ist gespeichert und anonymisiert

### Option 2: Text direkt eingeben
1. Klicke auf Tab "Kandidaten"
2. Klicke "Neues Kandidatenprofil hochladen"
3. Wähle "Text eingeben"
4. Füge Lebenslauf ein
5. Klicke "Hochladen"
6. ✅ Kandidat ist gespeichert und anonymisiert

### Was passiert automatisch?
- ❌ Name, Geburtsdatum werden entfernt
- ❌ Adresse, E-Mail, Telefon werden entfernt
- ❌ Spezifische Firmennamen werden durch Beschreibungen ersetzt
- ✅ KI extrahiert: Skills, Erfahrungsjahre, Seniority Level, etc.
- ✅ Profil wird in Datenbank gespeichert
- ✅ Chatbot kennt den Kandidaten sofort

### Beispiel-CV:
```
Max Mustermann
max.mustermann@example.com
+49 170 1234567

Berufserfahrung:
Senior Software Engineer | StartupXYZ GmbH, Berlin | 2020-2023
- Python, Django, AWS, Docker
- 3 Jahre Team Lead

Skills: Python, Django, React, AWS, Docker
```

**Wird automatisch zu:**
```
Kandidat #123 (Senior)
Erfahrung: 8 Jahre
Skills: Python, Django, React, AWS, Docker
Location: Berlin
Verfügbarkeit: Vollzeit

Profil: Erfahrener Software Engineer mit 8 Jahren 
Berufserfahrung. Arbeitete bei großem Tech-Startup 
im Raum Berlin. Expertise in Cloud-Technologien und 
Full-Stack-Entwicklung. Führungserfahrung als Team Lead.
```

---

## 🤖 Chatbot nutzt automatisch die Daten

### Für Kandidaten:
**User fragt:** "Ich suche einen Job als PHP Developer"  
**Chatbot antwortet:** "Ich habe 3 passende Stellenangebote für Sie:
- Senior PHP Developer (Raum München, Remote-Hybrid)
- PHP Backend Engineer (Berlin, Vollzeit)
- ..."

### Für Kunden:
**User fragt:** "Ich suche einen Python-Entwickler mit AWS-Erfahrung"  
**Chatbot antwortet:** "Ich habe 2 passende Kandidatenprofile:
- Kandidat #123 (Senior, 8 Jahre, Skills: Python, AWS, ...)
- Kandidat #456 (Mid, 5 Jahre, Skills: Python, AWS, ...)
  
⚠️ Alle Profile sind DSGVO-konform anonymisiert. Bei Interesse 
erhalten Sie vollständige Unterlagen nach NDA."

---

## ✏️ Vakanzen/Kandidaten bearbeiten

1. Klicke auf Tab "Vakanzen" oder "Kandidaten"
2. Klicke auf eine Zeile in der Tabelle
3. Detailansicht öffnet sich
4. Bearbeite Felder direkt
5. Klicke "Änderungen speichern"
6. ✅ Aktualisiert

**Bearbeitbare Felder:**
- Status (aktiv/inaktiv/besetzt bzw. verfügbar/vermittelt/inaktiv)
- Skills (hinzufügen/entfernen)
- Alle anderen Textfelder

---

## 🗑️ Vakanzen/Kandidaten löschen

1. Klicke auf Tab "Vakanzen" oder "Kandidaten"
2. Klicke auf eine Zeile in der Tabelle
3. Detailansicht öffnet sich
4. Klicke "Löschen" (unten)
5. Bestätige Löschung
6. ✅ Gelöscht

---

## 🔍 Suchen & Filtern

### Suchfunktion:
- Suchfeld oben rechts
- Sucht in allen Feldern (Titel, Skills, Location, etc.)
- Live-Filterung

### Status-Filter:
**Vakanzen:**
- Alle
- Aktiv
- Inaktiv
- Besetzt

**Kandidaten:**
- Alle
- Verfügbar
- Vermittelt
- Inaktiv

---

## 🛡️ DSGVO & Datenschutz

### Anonymisierung Stellenbeschreibungen:
- ❌ Firmennamen → Entfernt
- ❌ Kontaktdaten → Entfernt
- ❌ Spezifische Adressen → Auf Region reduziert

### Anonymisierung CVs:
- ❌ Namen, Geburtsdaten → Entfernt
- ❌ Kontaktdaten → Entfernt
- ❌ Firmennamen → Durch Beschreibungen ersetzt

**Wichtig:** 
- Chatbot zeigt nur anonymisierte Profile
- Bei Kundeninteresse: Vollständige Unterlagen nach NDA-Unterzeichnung
- Alle Daten werden lokal gespeichert (nicht in Cloud)

---

## 💡 Best Practices

### Vakanzen:
1. **Regelmäßig aktualisieren** - Status auf "besetzt" setzen wenn Position gefüllt
2. **Skills präzise angeben** - Je genauer, desto besser das Matching
3. **Alte Vakanzen archivieren** - Status auf "inaktiv" setzen

### Kandidaten:
1. **Skills vollständig** - Je mehr Skills, desto besser das Matching
2. **Status aktualisieren** - "vermittelt" setzen wenn platziert
3. **Regelmäßig prüfen** - Verfügbarkeit aktuell halten

---

## 📊 Übersicht

### Vakanzen-Ansicht:
```
┌─────────────────────────────────────────────────────────┐
│ 💼 Vakanzen                    [Neue Vakanz hochladen]  │
├─────────────────────────────────────────────────────────┤
│ Suche: [_______]  Status: [Alle ▼]                      │
├─────────────────────────────────────────────────────────┤
│ Titel                    | Skills         | Status      │
├─────────────────────────────────────────────────────────┤
│ Senior PHP Developer     | PHP, MySQL,... | ● Aktiv    │
│ Python Backend Engineer  | Python, AWS... | ● Aktiv    │
│ DevOps Engineer          | Docker, K8s... | ○ Besetzt  │
└─────────────────────────────────────────────────────────┘
```

### Kandidaten-Ansicht:
```
┌─────────────────────────────────────────────────────────┐
│ 👤 Kandidaten               [Neues Profil hochladen]    │
├─────────────────────────────────────────────────────────┤
│ Suche: [_______]  Status: [Alle ▼]                      │
├─────────────────────────────────────────────────────────┤
│ ID      | Skills              | Level  | Status         │
├─────────────────────────────────────────────────────────┤
│ #123    | Python, AWS, React  | Senior | ● Verfügbar   │
│ #456    | PHP, Docker, MySQL  | Mid    | ● Verfügbar   │
│ #789    | Java, Spring, K8s   | Lead   | ○ Vermittelt  │
└─────────────────────────────────────────────────────────┘
```

---

## ❓ FAQ

**Q: Werden meine Original-Dateien gespeichert?**  
A: Nein, nur der extrahierte und anonymisierte Text wird gespeichert.

**Q: Kann ich eine Vakanz/Kandidat nach dem Upload noch bearbeiten?**  
A: Ja, alle Felder können nachträglich bearbeitet werden.

**Q: Wie lange dauert die KI-Verarbeitung?**  
A: Ca. 5-10 Sekunden pro Dokument.

**Q: Welche Dateiformate werden unterstützt?**  
A: PDF, DOCX, TXT oder direkter Text-Input.

**Q: Wie genau ist die Anonymisierung?**  
A: Die Gemini-KI entfernt zuverlässig alle personenbezogenen Daten. 
   Zusätzlich kannst du das Ergebnis nach Upload noch manuell prüfen.

**Q: Kann der Chatbot auch nach mehreren Skills gleichzeitig suchen?**  
A: Ja, z.B. "Suche Job mit PHP und Docker" zeigt alle Vakanzen mit beiden Skills.

---

## 🆘 Probleme?

### Upload funktioniert nicht:
- Prüfe Dateiformat (PDF, DOCX, TXT)
- Prüfe Dateigröße (max. 10 MB)
- Prüfe Internet-Verbindung

### KI-Verarbeitung schlägt fehl:
- Dokument könnte zu kurz sein (min. 50 Wörter)
- Dokument könnte nicht lesbar sein (z.B. gescannte PDF ohne OCR)
- → Versuche Text direkt einzugeben

### Keine Ergebnisse in Chatbot:
- Prüfe Status der Vakanz/Kandidat (muss "aktiv"/"verfügbar" sein)
- Prüfe Skills (müssen korrekt geschrieben sein)
- Chatbot braucht präzise Anfragen (z.B. "PHP" statt "Programmierer")

---

## 📞 Support

Bei weiteren Fragen:
- Admin-Dashboard: https://chatbot.noba-experts.de/admin/
- Deployment-Details: siehe `DEPLOYMENT_STATUS.md`

---

*Stand: 05.11.2025*
