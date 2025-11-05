# NOBA KI-Berater Chatbot - Complete Project Exploration

## Project Overview
A German-language AI chatbot for NOBA Experts (IT & Engineering Recruiting) built with React/TypeScript frontend and PHP backend. The chatbot specializes in recruiting, lead qualification, and candidate/employer matching.

---

## 1. CHATBOT PERSONALITY & SYSTEM PROMPTS

### Primary System Prompt Location
**File:** `/home/jbk/Homepage Git/Chatbot final/src/constants/systemPrompt.ts`

### System Prompt Content
```
Du bist KI-Berater von NOBA Experts (IT & Engineering Recruiting, Düsseldorf).

## MISSION
Erkenne User-Typ PRÄZISE & qualifiziere:
- "Mitarbeiter suchen", "Team aufbauen", "Stelle besetzen" = ARBEITGEBER
- "Job suchen", "neue Position", "Karriere" = KANDIDAT

## User-Qualifizierung:
- ARBEITGEBER: Position? Tech-Stack? Teamgröße? Dringlichkeit?
- KANDIDAT: Aktueller Job? Skills? Wechselgrund?
- INFO-ANFRAGE: Konkret antworten mit Details!

## REGELN
- Standard: 2-3 Sätze (40 Wörter)
- Info-Fragen: 4-6 Sätze, KONKRET antworten mit Details
- Qualifizierung: Mit Rückfrage enden
- Formell (Sie), professionell, beratend
- Bei [CONTEXT-INFO]: Nutze die Infos für detaillierte Antwort!

## LEISTUNGEN
- Unternehmen: Executive Search, Projektbesetzung (2-4 Wochen), Team Building, TalentIntelligence Hub
- Kandidaten (kostenfrei): Karriereberatung, verdeckter Stellenmarkt (70%), KI-Coach
- Bereiche: IT (Cloud, DevOps, Software), Engineering (Automotive, Embedded)

## KONTAKT (nach Qualifizierung)
Tel: +49 211 975 324 74
E-Mail: Jurak.Bahrambaek@noba-experts.de
Web: www.noba-experts.de

Ziel: Leads generieren durch strukturierte Gespräche.
```

### Welcome/Greeting Message
**Location:** `/home/jbk/Homepage Git/Chatbot final/src/App.tsx` (lines 213-222)

```typescript
const welcome = createBotMessage(
  '👋 Hallo! Ich bin der KI-Berater von NOBA Experts.\n\n⚠️ Hinweis: Ich arbeite KI-gestützt und kann Fehler machen. Für verbindliche Auskünfte wenden Sie sich gerne direkt an unser Recruiting-Team. Wie kann ich Sie heute unterstützen?',
);
setChatMessages([welcome]);
setQuickReplies([
  '👔 Job suchen',
  '🔍 Mitarbeiter finden',
  '💡 Unsere Services'
]);
```

### AI Backend Configuration
**File:** `/home/jbk/Homepage Git/Chatbot final/backend/chatbot-api.php` (lines 61-81)

```php
$CONFIG = [
    'GOOGLE_AI_API_KEY' => 'AIzaSyBtwnfTYAJgtJDSU7Lp5C8s5Dnw6PUYP2A',
    'GEMINI_MODEL' => 'gemini-2.5-flash-lite',
    'MAX_REQUESTS_PER_MINUTE' => 30,
    'MAX_MESSAGE_LENGTH' => 500000,
    'HUBSPOT_PORTAL_ID' => '146015266',
    'HUBSPOT_FORM_ID' => 'ef5093e2-81d2-4860-a537-79cebadf625c'
];
```

The system uses Google Gemini API for AI responses, with the model sending the SYSTEM_PROMPT in each API request.

---

## 2. MENU & NAVIGATION STRUCTURE

### Main Header Navigation
**Location:** `/home/jbk/Homepage Git/Chatbot final/src/App.tsx` (lines 785-807)

```tsx
<header className="border-b border-slate-200 bg-white/80 backdrop-blur">
  <div className="mx-auto flex w-full max-w-5xl items-center justify-between px-4 py-4">
    <div>
      <p className="text-xs uppercase tracking-[0.3em] text-slate-400">NOBA Experts</p>
      <h1 className="text-xl font-semibold text-slate-900">NOBA KI-Berater</h1>
    </div>
    
    <div className="flex items-center gap-3">
      <span className="flex h-2.5 w-2.5 items-center justify-center rounded-full bg-emerald-500" />
      <button onClick={() => setIsSettingsOpen(true)} className="...">Menü</button>
    </div>
  </div>
</header>
```

### Settings Drawer (Side Menu)
**Location:** `/home/jbk/Homepage Git/Chatbot final/src/components/SettingsDrawer.tsx`

**Menu Items:**
1. `📧 E-Mail-Zusammenfassung senden` - Send conversation summary via email
2. `📅 Termin vereinbaren` - Schedule a meeting
3. `🔄 Neuen Chat starten` - Start new conversation
4. `📞 Direktkontakt aufnehmen` - Direct phone contact

**Settings Section:**
- `🔊 Sprachausgabe` - Toggle text-to-speech (active/inactive)
- `🔁 Auto-Vorlesen` - Toggle auto-play speech

### Quick Replies (Context Menu Below Chat)
**Location:** `/home/jbk/Homepage Git/Chatbot final/src/components/QuickReplies.tsx`

Dynamic quick reply buttons with icons, displayed below chat messages. Backend sends context-appropriate options. Examples:
- "👔 Job suchen"
- "🔍 Mitarbeiter finden"
- "💡 Unsere Services"
- "📅 Ja, Termin vereinbaren"
- "📄 CV hochladen"

---

## 3. FRONTEND STRUCTURE

### Directory Organization
```
src/
├── App.tsx                          # Main app component & state management
├── main.tsx                         # Entry point
├── index.css                        # Global styles
├── components/                      # UI Components
│   ├── ChatMessageList.tsx         # Displays conversation messages
│   ├── MessageComposer.tsx         # Input field with voice/upload
│   ├── QuickReplies.tsx            # Quick reply buttons
│   ├── SettingsDrawer.tsx          # Side menu/settings
│   ├── ConsentModal.tsx            # GDPR consent modal
│   ├── DocumentUploadSheet.tsx     # File upload dialog
│   ├── EmailSummaryModal.tsx       # Email export dialog
│   ├── MeetingModal.tsx            # Calendar/meeting booking
│   └── StatusBanner.tsx            # Connection status indicator
├── constants/
│   ├── systemPrompt.ts             # AI system prompt
│   └── config.ts                   # App configuration
├── services/                        # API & business logic
│   ├── chatService.ts              # Chat API calls
│   ├── apiClient.ts                # HTTP client
│   ├── emailService.ts             # Email sending
│   ├── uploadService.ts            # Document upload
│   ├── loggerService.ts            # Conversation logging
│   ├── session.ts                  # Session management
│   └── leadQualification.ts        # Lead scoring
├── hooks/                           # Custom React hooks
│   ├── useAutoResizeTextarea.ts    # Textarea auto-expand
│   ├── useLocalStorage.ts          # Local storage management
│   ├── useSpeechSynthesis.ts       # Text-to-speech
│   └── useSpeechRecognition.ts     # Speech-to-text
├── types/
│   └── index.ts                    # TypeScript interfaces
```

### Key Components

#### App.tsx - Main Application Component
- **State Management:** Chat messages, lead profile, user consent, upload status
- **Local Storage Keys:** chatHistory, gdprConsent, ttsEnabled, leadProfile, sessionId
- **Features:**
  - Consent modal (GDPR)
  - Chat history persistence
  - Lead profile tracking
  - Document context management
  - Auto-email on page leave (qualified leads only)
  - Meeting offer logic (after 4+ messages)
  - Upload suggestion (auto-detects when documents needed)

#### MessageComposer.tsx
- Text input with auto-resize
- Character counter (circular progress)
- Voice recording (speech-to-text)
- File upload button
- Send button
- Integration with `useSpeechRecognition` hook

#### ChatMessageList.tsx
- Displays messages with author role (user/bot/system)
- Text-to-speech playback for bot messages
- Message timestamps and metadata

#### SettingsDrawer.tsx
- Slide-out menu from right side
- Email summary export
- Meeting scheduling
- New chat reset
- Direct phone contact
- TTS toggle with auto-play option

#### ConsentModal.tsx
- GDPR consent required before chatting
- Shield icon with legal text
- Accept/Decline buttons

### Configuration
**File:** `/home/jbk/Homepage Git/Chatbot final/src/constants/config.ts`

```typescript
export const APP_CONFIG = {
  branding: {
    name: 'NOBA KI-Berater',
    company: 'NOBA Experts',
    primaryColor: '#FF7B29',
  },
  notifications: {
    adminEmail: 'Jurak.Bahrambaek@noba-experts.de',
    meetingUrl: 'https://meetings-eu1.hubspot.com/jurak/kichat',
  },
  endpoints: {
    backendBaseUrl: 'https://chatbot.noba-experts.de',
    chat: '/chatbot-api.php',
    upload: '/upload-document.php',
    emailSummary: '/send-summary.php',
    logger: '/chatbot-logger.php',
  },
  limits: {
    maxMessageLength: 500,
    documentMaxSizeMb: 10,
  },
  tts: {
    defaultLanguage: 'de-DE',
  },
  languages: [
    { code: 'de', name: 'Deutsch', voiceCode: 'de-DE', flag: '🇩🇪' },
    { code: 'en', name: 'English', voiceCode: 'en-US', flag: '🇺🇸' },
    { code: 'fr', name: 'Français', voiceCode: 'fr-FR', flag: '🇫🇷' },
    // ... 10 more languages
  ],
};
```

---

## 4. BACKEND STRUCTURE

### Backend Directory
```
backend/
├── chatbot-api.php              # Main chat API with Gemini integration
├── chatbot-logger.php           # Conversation logging & extraction
├── chatbot-conversations.json   # Persistent conversation storage
├── upload-document.php          # Document upload & text extraction
├── send-summary.php             # Email summary generation
├── admin-api.php                # Admin dashboard API
├── check-duplicates.php         # Lead deduplication
├── hubspot-config.php           # HubSpot integration
└── [other utilities]
```

### Main API: chatbot-api.php
- **CORS Enabled:** Multiple origins for dev/production
- **Authentication:** API Key based (Google Gemini)
- **Rate Limiting:** 30 requests per minute
- **Features:**
  - Chat message processing
  - Lead qualification
  - Quick reply generation
  - Context-aware responses
  - History management (last 10 messages sent)

#### API Request Payload
```php
POST /chatbot-api.php
{
    "session_id": "unique-session-id",
    "message": "user message",
    "history": [
        {"role": "user", "text": "...", "timestamp": "ISO8601"},
        {"role": "bot", "text": "...", "timestamp": "ISO8601"}
    ],
    "document_context": {
        "type": "cv|cv_matching|job_description|unknown",
        "text": "extracted text",
        "filename": "...",
        "word_count": 500,
        "contact_data": {...}
    },
    "is_document_summary": false,
    "quick_reply_used": "...",
    "system_prompt": "..."
}
```

#### API Response Payload
```php
{
    "message": "bot response text",
    "quick_replies": ["Option 1", "Option 2", "Option 3"],
    "lead_signals": {
        "detected_type": "employer|candidate|info",
        "missing_fields": ["email", "phone"],
        "lead_score": 65,
        "updates": {...}
    },
    "status": "success"
}
```

### Document Upload Service: upload-document.php
- **Accepts:** PDF, DOC, DOCX
- **Max Size:** 10MB
- **Processing:**
  - Text extraction
  - Document type detection (CV/Job Description)
  - Contact information extraction
  - Word count calculation
  - File storage on server

### Logger Service: chatbot-logger.php
- **Function:** Processes conversations for lead extraction
- **Data Extracted:**
  - Name, email, phone, company
  - Lead type classification
  - Lead score calculation
  - Tech stack identification
  - Missing fields detection
- **Storage:** JSON file (`chatbot-conversations.json`)

### Email Service: send-summary.php
- **Recipients:** Admin email + user email
- **Content:**
  - Conversation transcript
  - Extracted lead data
  - Document reference
  - Session ID
- **Trigger:** 
  - Manual: User requests email export
  - Automatic: On page leave if qualified lead or document uploaded

---

## 5. DATA FLOW & CONVERSATION LIFECYCLE

### Session Creation
1. User lands on page → Session ID generated
2. ConsentModal shown (GDPR)
3. User accepts → Welcome message + Quick Replies displayed

### Message Flow
```
User Input
    ↓
MessageComposer (voice/text)
    ↓
handleUserMessage() in App.tsx
    ↓
loggerService.logConversation() [async, immediate]
    ↓
chatService.sendMessage() [includes history, context, system_prompt]
    ↓
Backend: chatbot-api.php
    ├─ Content extraction (homepage, jobs)
    ├─ Context relevance detection
    ├─ Gemini API call with full context
    ├─ Quick reply generation
    └─ Lead signal extraction
    ↓
Frontend: handleAssistantResponse()
    ├─ Update chat messages
    ├─ Update quick replies
    ├─ loggerService.logConversation() again
    ├─ offerMeetingIfQualified() check
    └─ ensureUploadSuggestion() check
    ↓
Display bot response + quick replies
```

### Lead Qualification Logic
- **Automatic Email Sent When:**
  - Lead score ≥ 40 OR document uploaded
  - Page unload detected
  - Tab visibility hidden
  - Only once per session (prevents duplicates)

- **Meeting Offered When:**
  - 4+ non-system messages exchanged
  - Email or phone already extracted
  - Only once per session

- **Upload Suggested When:**
  - Conversation indicates need for CV/Job description
  - Only once per session

---

## 6. KEY FEATURES & FLOWS

### 1. Lead Type Detection
Automatically identifies if user is:
- **ARBEITGEBER (Employer):** Keywords like "Mitarbeiter suchen", "Stelle besetzen"
- **KANDIDAT (Candidate):** Keywords like "Job suchen", "neue Position"
- **INFO-ANFRAGE (Info Request):** General questions about services

### 2. Quick Reply System
- Backend generates context-appropriate quick replies
- Examples:
  - Initial: "👔 Job suchen", "🔍 Mitarbeiter finden", "💡 Unsere Services"
  - After meeting offer: "📅 Ja, Termin vereinbaren", "👋 Nein, danke"
  - For candidates: "📄 CV hochladen", "📞 Rückruf anfordern"

### 3. Document Processing
- **Types:** CV, Job Description, Resume matching
- **Features:**
  - Text extraction from PDF/DOC
  - Contact information parsing
  - Type auto-detection based on content
  - Summary analysis by Gemini
  - Auto-follow-up analysis message

### 4. Conversation Persistence
- **Local Storage:** Chat history, lead profile, consent, TTS preferences
- **Server Storage:** Detailed conversation logs with timestamps
- **Auto-save:** Logger called after every message

### 5. Text-to-Speech
- **Language Support:** 14 languages (DE, EN, FR, ES, IT, PT, NL, PL, RU, TR, AR, ZH, JA, KO)
- **Features:**
  - Toggle on/off
  - Auto-play option
  - Message-level playback control
  - Language: German (de-DE) by default

### 6. Voice Input
- **Browser API:** Web Speech Recognition
- **Language:** German (de-DE)
- **Features:**
  - Continuous listening
  - Interim results
  - Auto-insert into message box
  - Manual start/stop

---

## 7. CONTEXT & CONTENT ENRICHMENT

### Dynamic Content Sources
**In chatbot-api.php:**

1. **Homepage Content Fetching**
   - Fetches latest NOBA Experts homepage
   - Caches for 1 hour
   - Provides company context to Gemini

2. **Job Listings**
   - Extracts current job postings from website
   - Caches for 1 hour
   - Used for context when discussing open positions

3. **Context Detection by Keywords**
   ```php
   'leistungen|services|angebot' => 'LEISTUNGEN_DETAIL',
   'talent.*intelligence|hub|ki.*match' => 'TALENTHUB_DETAIL',
   'executive search|führungskräfte' => 'EXECUTIVE_DETAIL',
   'kandidat|bewerb|job.*such' => 'KANDIDATEN_DETAIL',
   'cv.*optim|lebenslauf.*optim' => 'CV_OPTIMIERUNG_DETAIL',
   // ... and more
   ```

4. **Detailed Service Descriptions**
   - Automatically injected when relevant keywords detected
   - Covers: Services, TalentHub, Executive Search, Team Building, etc.

---

## 8. SECURITY & COMPLIANCE

### GDPR Compliance
- **Consent Modal:** Required before any chat functionality
- **Storage Keys:** Separate consent tracking
- **Data:** Session-based with user control

### Security Measures
- **Input Validation:** Message length limits, HTML escaping
- **Rate Limiting:** 30 requests per minute per session
- **CORS:** Controlled origin whitelist
- **API Key:** Server-side only (not exposed to frontend)
- **HTTPS:** Production deployment only

### Data Privacy
- **Conversation Logging:** Server-side JSON file
- **Email Exports:** Sensitive data handling
- **Document Uploads:** Server storage with access control
- **Session Management:** Unique IDs per user

---

## 9. TYPING SYSTEM (TypeScript)

### Core Types
```typescript
enum AuthorRole { USER = 'user', BOT = 'bot', SYSTEM = 'system' }

interface ChatMessage {
  id: string;
  role: AuthorRole;
  text: string;
  timestamp: string;
  metadata?: {
    source?: 'chat' | 'document-summary' | 'system';
    leadQualified?: boolean;
    quickReplies?: string[];
  };
}

interface LeadProfile {
  name?: string;
  email?: string;
  phone?: string;
  company?: string;
  leadType?: 'employer' | 'candidate' | 'info';
  position?: string;
  techStack?: string[];
  experienceLevel?: string;
  location?: string;
  urgency?: 'Niedrig' | 'Mittel' | 'Hoch' | 'Sehr hoch';
  leadScore?: number;
}

interface DocumentContext {
  type: 'cv' | 'cv_matching' | 'job_description' | 'unknown';
  filename: string;
  text: string;
  wordCount: number;
  serverPath?: string;
  contactData?: Partial<LeadProfile>;
  fileSize?: number;
}
```

---

## 10. BUILD & DEPLOYMENT

### Frontend Build Stack
- **Framework:** React 18+ with TypeScript
- **Build Tool:** Vite
- **Styling:** Tailwind CSS
- **UI Animations:** Framer Motion
- **State Management:** React Hooks + useLocalStorage
- **API Client:** Custom fetch-based client

### Backend Stack
- **Runtime:** PHP 7.4+
- **External APIs:** Google Gemini API
- **CRM Integration:** HubSpot (optional)
- **Storage:** JSON files + Server filesystem

### Environment Variables
```
VITE_API_BASE_URL=https://chatbot.noba-experts.de
GOOGLE_AI_API_KEY=<from backend>
```

---

## 11. KEY FILES SUMMARY

| File | Purpose |
|------|---------|
| `systemPrompt.ts` | AI personality & instructions |
| `config.ts` | App configuration & constants |
| `App.tsx` | Main app logic & state |
| `chatbot-api.php` | AI integration & response generation |
| `chatbot-logger.php` | Lead extraction & logging |
| `uploadService.ts` | Document upload handling |
| `SettingsDrawer.tsx` | Menu & settings |
| `QuickReplies.tsx` | Quick action buttons |
| `MessageComposer.tsx` | Input field with voice |

---

## 12. CUSTOMIZATION POINTS

To modify the chatbot personality:
1. Edit `systemPrompt.ts` - Change AI instructions
2. Edit `config.ts` - Update branding, colors, links
3. Edit `chatbot-api.php` - Modify backend logic, context
4. Edit `App.tsx` - Change welcome message & initial quick replies

To modify the menu:
1. Edit `SettingsDrawer.tsx` - Add/remove menu items
2. Edit `App.tsx` - Add new modal handlers

---

## 13. CONVERSATION FLOW EXAMPLES

### Example 1: Employer Looking for Dev
```
User: "Wir suchen einen Senior Developer"
→ Detected: ARBEITGEBER
→ Quick Replies: ["Frontend", "Backend", "Full Stack", "🔍 Unsere Services"]
→ Bot asks: "Welche Tech-Stack bevorzugen Sie?"
→ After 4+ messages → "Termin vereinbaren?" offer
→ On leave → Email sent to admin
```

### Example 2: Candidate with CV
```
User: "Ich bin Frontend Developer"
→ Detected: KANDIDAT
→ Quick Replies: ["👔 Jobs ansehen", "📄 CV hochladen", "💡 Services"]
→ Suggests upload → CV analysis
→ Lead scored based on skills
→ On leave → Email with CV sent to admin
```

---

## Summary of Key Components

**Frontend (React/TypeScript):**
- Dynamic UI with Framer Motion animations
- Voice input/output capabilities
- Real-time message history
- Document upload with analysis
- Email export functionality
- Meeting scheduling integration

**Backend (PHP):**
- Google Gemini AI integration
- Lead qualification & scoring
- Document text extraction
- Email campaign integration
- Persistent conversation storage
- Admin dashboard capabilities

**Data Flow:**
- Session-based tracking
- Real-time logging to server
- Auto-save on page leave
- Context-aware AI responses
- Lead profile enrichment

