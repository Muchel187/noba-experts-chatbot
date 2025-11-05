# NOBA Chatbot - Quick Reference Guide

## File Locations

### System Prompt & Personality
- **File:** `src/constants/systemPrompt.ts`
- **Edit to change:** AI behavior, tone, instructions, contact info

### Greeting & Welcome Message
- **File:** `src/App.tsx` (lines 213-222)
- **Edit to change:** Welcome message and initial quick replies

### Configuration & Branding
- **File:** `src/constants/config.ts`
- **Edit to change:** Company name, colors, endpoints, languages

### Menu Items
- **File:** `src/components/SettingsDrawer.tsx`
- **Edit to change:** Side menu buttons and options

### Quick Replies
- **File:** `src/components/QuickReplies.tsx`
- **Edit to change:** Button styling, icons, behavior

---

## Common Customizations

### 1. Change Chatbot Name
```typescript
// src/constants/config.ts
export const APP_CONFIG = {
  branding: {
    name: 'YOUR BOT NAME',  // ← Change here
    company: 'YOUR COMPANY',  // ← And here
    primaryColor: '#FF7B29',
  },
  // ...
};
```

### 2. Change Welcome Message
```typescript
// src/App.tsx (around line 213)
const welcome = createBotMessage(
  'YOUR WELCOME TEXT HERE',
);
setQuickReplies([
  'YOUR QUICK REPLY 1',
  'YOUR QUICK REPLY 2',
  'YOUR QUICK REPLY 3'
]);
```

### 3. Change System Prompt (AI Personality)
```typescript
// src/constants/systemPrompt.ts
export const SYSTEM_PROMPT = `
Du bist KI-Assistent für [DEINE FIRMA]

## DEINE MISSION
[Beschreibe die Aufgaben...]

## DEINE TONALITÄT
[Formell, freundlich, etc.]

## KONTAKTINFOS
Tel: [DEINE TELEFON]
Email: [DEINE EMAIL]

Ziel: [WAS SOLL DER BOT TUEN?]
`;
```

### 4. Change Admin Contact Email
```typescript
// src/constants/config.ts
notifications: {
  adminEmail: 'your-email@company.com',  // ← Change here
  meetingUrl: 'your-hubspot-link',
},
```

### 5. Change Backend Endpoint
```typescript
// src/constants/config.ts
endpoints: {
  backendBaseUrl: 'https://your-server.com',  // ← Change here
  chat: '/your-api-endpoint.php',
  // ...
},
```

### 6. Add Menu Item
```typescript
// src/components/SettingsDrawer.tsx
<button
  type="button"
  onClick={yourNewFunction}
  className="flex items-center justify-between rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:border-noba-orange hover:text-noba-orange"
>
  <span>Your Menu Item Label</span>
  <span className="text-base">EMOJI</span>
</button>
```

---

## API Integration Points

### Chat Message Flow
```
User Input
  ↓
POST /chatbot-api.php
  ├─ message: string
  ├─ history: ChatMessage[]
  ├─ session_id: string
  ├─ system_prompt: string
  └─ document_context?: DocumentContext
  ↓
Response
  ├─ message: string
  ├─ quick_replies: string[]
  └─ lead_signals: LeadSignals
```

### Document Upload
```
User selects file
  ↓
POST /upload-document.php (FormData)
  ├─ file: File
  └─ session_id: string
  ↓
Response
  ├─ extracted_text: string
  ├─ document_type: 'cv' | 'job_description' | 'unknown'
  ├─ contact_data: LeadProfile
  └─ word_count: number
```

### Email Summary
```
User requests email
  ↓
POST /send-summary.php
  ├─ recipient_email: string
  ├─ conversation: ChatMessage[]
  ├─ extracted_data: LeadProfile
  └─ document_context?: DocumentContext
  ↓
Response
  ├─ success: boolean
  └─ message: string
```

---

## Key State Variables

### In App.tsx
```typescript
// Chat Management
const [chatMessages, setChatMessages] = useLocalStorage<ChatMessage[]>(
  'noba-chat-history', 
  []
);

// User Profile
const [leadProfile, setLeadProfile] = useLocalStorage<Partial<LeadProfile>>(
  'noba-lead-profile', 
  {}
);

// Session
const [sessionId, setSessionId] = useLocalStorage<string>(
  'noba-session-id', 
  ''
);

// GDPR
const [consentGranted, setConsentGranted] = useLocalStorage<boolean>(
  'noba-gdpr-consent', 
  false
);

// Document
const [documentContext, setDocumentContext] = useState<DocumentContext | null>(null);

// UI
const [isTyping, setIsTyping] = useState(false);
const [quickReplies, setQuickReplies] = useState<string[]>([]);
const [messageDraft, setMessageDraft] = useState('');
```

---

## Main Event Handlers

### When User Sends Message
```typescript
handleUserMessage(text: string, quickReplyUsed?: string)
  ├─ Create ChatMessage
  ├─ Add to chatMessages
  ├─ Call loggerService.logConversation()
  ├─ Call chatService.sendMessage()
  ├─ Call handleAssistantResponse()
  └─ Update UI (typing, quick replies)
```

### When Bot Responds
```typescript
handleAssistantResponse(response, metadata?)
  ├─ Add bot message to chat
  ├─ Update quick replies
  ├─ Update lead profile
  ├─ Log to server again
  ├─ Check if offer meeting needed
  ├─ Check if suggest upload needed
  └─ Auto-play voice if enabled
```

### When Document Uploaded
```typescript
handleDocumentUpload(file: File)
  ├─ Call uploadService.uploadDocument()
  ├─ Store document context
  ├─ Update lead profile from contact data
  ├─ Add system message to chat
  ├─ Build document summary prompt
  ├─ Send prompt to chatService.sendMessage()
  └─ Display AI analysis
```

### When User Leaves Page
```typescript
window.beforeunload / visibilitychange events
  ├─ Check if qualified lead OR document uploaded
  ├─ Check if email not already sent
  ├─ Call emailService.sendSummary()
  └─ Auto-send to admin email
```

---

## Component Props

### MessageComposer
```typescript
interface MessageComposerProps {
  value: string;                    // Text input value
  onChange: (value: string) => void;  // Update text
  onSubmit: () => void;             // Send message
  disabled?: boolean;               // Disable input
  maxLength: number;                // Character limit
  onOpenUpload?: () => void;        // Open upload dialog
}
```

### QuickReplies
```typescript
interface QuickRepliesProps {
  options: string[];                // Button labels
  onSelect: (option: string) => void;  // Handle click
}
```

### SettingsDrawer
```typescript
interface SettingsDrawerProps {
  open: boolean;
  onClose: () => void;
  onNewChat: () => void;
  onContact: () => void;
  onEmailSummary: () => void;
  onToggleTts: () => void;
  onToggleAutoPlay: () => void;
  ttsEnabled: boolean;
  ttsAutoPlay: boolean;
  supportsSpeech: boolean;
  onOpenMeeting: () => void;
}
```

---

## TypeScript Types

### ChatMessage
```typescript
interface ChatMessage {
  id: string;
  role: AuthorRole.USER | AuthorRole.BOT | AuthorRole.SYSTEM;
  text: string;
  timestamp: string;  // ISO8601
  metadata?: {
    source?: 'chat' | 'document-summary' | 'system';
    leadQualified?: boolean;
    quickReplies?: string[];
  };
}
```

### LeadProfile
```typescript
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
```

### DocumentContext
```typescript
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

## Configuration Values

### Language Support
```typescript
// In config.ts
languages: [
  { code: 'de', name: 'Deutsch', voiceCode: 'de-DE', flag: '🇩🇪' },
  { code: 'en', name: 'English', voiceCode: 'en-US', flag: '🇺🇸' },
  { code: 'fr', name: 'Français', voiceCode: 'fr-FR', flag: '🇫🇷' },
  { code: 'es', name: 'Español', voiceCode: 'es-ES', flag: '🇪🇸' },
  { code: 'it', name: 'Italiano', voiceCode: 'it-IT', flag: '🇮🇹' },
  { code: 'pt', name: 'Português', voiceCode: 'pt-PT', flag: '🇵🇹' },
  { code: 'nl', name: 'Nederlands', voiceCode: 'nl-NL', flag: '🇳🇱' },
  { code: 'pl', name: 'Polski', voiceCode: 'pl-PL', flag: '🇵🇱' },
  { code: 'ru', name: 'Русский', voiceCode: 'ru-RU', flag: '🇷🇺' },
  { code: 'tr', name: 'Türkçe', voiceCode: 'tr-TR', flag: '🇹🇷' },
  { code: 'ar', name: 'العربية', voiceCode: 'ar-SA', flag: '🇸🇦' },
  { code: 'zh', name: '中文', voiceCode: 'zh-CN', flag: '🇨🇳' },
  { code: 'ja', name: '日本語', voiceCode: 'ja-JP', flag: '🇯🇵' },
  { code: 'ko', name: '한국어', voiceCode: 'ko-KR', flag: '🇰🇷' },
]
```

### Limits
```typescript
limits: {
  maxMessageLength: 500,        // Characters per message
  documentMaxSizeMb: 10,        // Max file size
}
```

### Default TTS Language
```typescript
tts: {
  defaultLanguage: 'de-DE',  // German by default
}
```

---

## Common Debugging

### Check Chat History
```typescript
// In browser console
localStorage.getItem('noba-chat-history') // See all messages
JSON.parse(localStorage.getItem('noba-chat-history')) // Pretty print
```

### Check Lead Profile
```typescript
JSON.parse(localStorage.getItem('noba-lead-profile'))
```

### Check Session ID
```typescript
localStorage.getItem('noba-session-id')
```

### Clear All Data
```typescript
localStorage.clear()
sessionStorage.clear()
// Then refresh page
```

### Test API Endpoint
```javascript
fetch('https://chatbot.noba-experts.de/chatbot-api.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    session_id: 'test-123',
    message: 'Test message',
    history: [],
    system_prompt: 'You are helpful.'
  })
}).then(r => r.json()).then(console.log)
```

---

## Color Variables (Tailwind)

### NOBA Orange (Primary)
```
bg-noba-500 / text-noba-500 / border-noba-500
bg-noba-600 (darker)
bg-noba-50 (light background)
```

### Slate (Neutral)
```
text-slate-900 (dark text)
text-slate-700 (medium text)
text-slate-400 (light text)
bg-slate-100 (light bg)
border-slate-200 (borders)
```

---

## Special Features

### Auto-Email Logic
Emails sent automatically when:
1. User has lead score ≥ 40 OR uploaded document
2. Page is being left (beforeunload or visibilitychange)
3. Email hasn't already been sent this session

### Meeting Offer Logic
Meeting button shown when:
1. 4+ messages exchanged
2. Email or phone number extracted
3. Not already offered this session

### Upload Suggestion
Auto-suggest file upload when:
1. Conversation indicates need for CV/resume
2. Or need for job description details
3. Not already suggested this session

### Quick Replies
Generated by backend based on:
1. User lead type (employer vs candidate)
2. Current conversation context
3. Lead qualification status
4. Available actions relevant to conversation

---

## Development Notes

### Build & Run
```bash
npm install          # Install dependencies
npm run dev          # Dev server (Vite)
npm run build        # Build for production
npm run preview      # Preview production build
```

### Environment
```
VITE_API_BASE_URL=https://chatbot.noba-experts.de
```

### Browser Support
- Modern browsers (ES2020+)
- Requires: localStorage, WebSocket, Web Speech API (optional)

### Mobile Responsive
- Designed for all screen sizes
- Textarea auto-resizes
- Touch-friendly buttons
- Voice input on mobile supported

