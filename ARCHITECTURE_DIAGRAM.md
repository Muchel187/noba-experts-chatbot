# NOBA Chatbot - Architecture Diagram

## High-Level System Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                     USER INTERFACE (Browser)                     │
├─────────────────────────────────────────────────────────────────┤
│                                                                   │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │  Header: NOBA KI-Berater + Status + Menu Button          │  │
│  └──────────────────────────────────────────────────────────┘  │
│                                                                   │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │  Chat Message List (User/Bot/System Messages)            │  │
│  │  - Timestamps                                            │  │
│  │  - Voice playback buttons (TTS)                          │  │
│  │  - Message metadata                                      │  │
│  └──────────────────────────────────────────────────────────┘  │
│                                                                   │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │  Quick Replies (Dynamic, context-aware buttons)          │  │
│  │  Examples: "👔 Job suchen" | "📄 CV hochladen" | etc.  │  │
│  └──────────────────────────────────────────────────────────┘  │
│                                                                   │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │  Message Input Area                                      │  │
│  │  ├─ Upload Button (📎)                                  │  │
│  │  ├─ Textarea (auto-resize, auto-save)                   │  │
│  │  ├─ Character counter (circular)                        │  │
│  │  └─ Voice/Send Button (🎤 or ✈️)                        │  │
│  └──────────────────────────────────────────────────────────┘  │
│                                                                   │
└─────────────────────────────────────────────────────────────────┘
                              ↓
                    [Side Menu - Settings Drawer]
                    ├─ 📧 Email Summary
                    ├─ 📅 Schedule Meeting
                    ├─ 🔄 New Chat
                    ├─ 📞 Direct Contact
                    └─ Settings: TTS Toggle, Auto-play
```

## Frontend Component Hierarchy

```
App.tsx (Main)
├── ConsentModal (GDPR)
├── SettingsDrawer (Side menu)
├── DocumentUploadSheet (File upload dialog)
├── EmailSummaryModal (Export conversation)
├── MeetingModal (HubSpot calendar)
├── Header
│   ├── Title + Branding
│   └── Status + Menu Button
├── StatusBanner (Connection status)
├── ChatMessageList
│   └── ChatMessage[] (with TTS controls)
├── QuickReplies (Dynamic buttons)
└── MessageComposer
    ├── Upload Button
    ├── Textarea (with voice input)
    └── Send/Mic Button
```

## Data Flow: User Message Processing

```
┌─────────────────────────────────────────────────────────────────┐
│ 1. USER COMPOSES MESSAGE                                        │
│    - Text input or voice dictation via microphone               │
│    - Message draft stored in component state                    │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│ 2. USER SENDS MESSAGE                                           │
│    - handleUserMessage() called                                 │
│    - Create UserMessage object with unique ID & timestamp       │
│    - Add to conversationRef & chatMessages state                │
│    - Clear draft & quick replies                                │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│ 3. IMMEDIATE LOGGING (Async)                                    │
│    - loggerService.logConversation()                            │
│    - Updates server with conversation                           │
│    - Prevents data loss on page reload                          │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│ 4. SEND TO BACKEND                                              │
│    - chatService.sendMessage() with:                            │
│      • Current message text                                     │
│      • Last 10 messages (history)                               │
│      • Document context (if uploaded)                           │
│      • Session ID                                               │
│      • System prompt                                            │
└─────────────────────────────────────────────────────────────────┘
                              ↓
                    ╔═══════════════════╗
                    ║  BACKEND (PHP)    ║
                    ╚═══════════════════╝
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│ 5. BACKEND PROCESSING (chatbot-api.php)                        │
│                                                                  │
│    ├─ Input Validation & Sanitization                           │
│    │  - Check message length                                    │
│    │  - HTML escape user input                                  │
│    │  - Rate limit check (30/min)                               │
│    │                                                             │
│    ├─ Content Enrichment                                        │
│    │  - Fetch homepage content (cached 1 hour)                 │
│    │  - Fetch current job listings (cached 1 hour)             │
│    │  - Detect context by keywords                              │
│    │  - Build context-specific info strings                     │
│    │                                                             │
│    ├─ AI Processing (Gemini API)                                │
│    │  • Send to Google Gemini with:                             │
│    │    - System prompt (full AI instructions)                  │
│    │    - Full conversation history                             │
│    │    - Current message                                       │
│    │    - Document context (if any)                             │
│    │    - Enriched context info                                 │
│    │  • Receive AI response                                     │
│    │                                                             │
│    └─ Lead Extraction & Quick Replies                           │
│       - Parse response for lead signals                         │
│       - Generate context-appropriate quick replies              │
│       - Calculate lead score                                    │
│       - Extract name, email, phone, tech stack, etc.           │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│ 6. RESPONSE TO FRONTEND                                         │
│    {                                                            │
│      "message": "AI response text",                            │
│      "quick_replies": ["Option 1", "Option 2"],                │
│      "lead_signals": {                                         │
│        "detected_type": "employer|candidate|info",             │
│        "lead_score": 65,                                       │
│        "missing_fields": ["email"],                            │
│        "updates": { "name": "...", "email": "..." }           │
│      }                                                          │
│    }                                                            │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│ 7. FRONTEND RESPONSE HANDLING                                   │
│    handleAssistantResponse() does:                              │
│                                                                  │
│    ├─ Add bot message to chat                                   │
│    ├─ Update quick replies with backend suggestions             │
│    ├─ Update lead profile from signals                          │
│    ├─ Log conversation again to server                          │
│    ├─ Check if should offer meeting (4+ messages + contact)    │
│    ├─ Check if should suggest document upload                  │
│    └─ Auto-play voice if TTS enabled                           │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│ 8. DISPLAY TO USER                                              │
│    - Bot message appears in chat                                │
│    - Quick replies render below                                 │
│    - If TTS enabled: Voice plays automatically                  │
│    - Status changes from "typing" to "idle"                     │
└─────────────────────────────────────────────────────────────────┘
```

## Lead Qualification Flow

```
User Joins
    ↓
[Session Created] → Generate unique session ID
    ↓
[Consent Modal] → GDPR acceptance required
    ↓
[Welcome Message + Quick Replies] → "Job suchen?" "Mitarbeiter finden?" etc.
    ↓
User messages monitored for:
├─ Keywords → Lead type detection (employer vs candidate)
├─ Contact info → Extract email, phone, company
├─ Skills → Tech stack identification
└─ Urgency → Rate of messages, engagement level
    ↓
[Lead Qualification Metrics Updated]
    ├─ Lead Score (calculated by backend logger)
    ├─ Missing Fields (tracks gaps in profile)
    ├─ Lead Type (employer / candidate / info-seeker)
    └─ Tech Stack (extracted keywords)
    ↓
[Conditional Actions Based on Lead Quality]
    ├─ If 4+ messages + has contact → Offer meeting
    ├─ If conversation indicates need → Suggest document upload
    └─ If qualified lead (score ≥40) OR document uploaded
        → Auto-send email to admin on page leave
            (with full conversation & extracted data)
```

## Document Upload & Processing

```
User clicks Upload Button (📎)
    ↓
DocumentUploadSheet Modal Opens
    ├─ Accept: PDF, DOC, DOCX
    └─ Max: 10MB
    ↓
User selects file
    ↓
uploadService.uploadDocument() → POST to /upload-document.php
    ↓
Backend Processing:
├─ Validate file (type, size, virus scan)
├─ Extract text from document
├─ Auto-detect document type:
│  ├─ CV → Has skills, experience, education
│  ├─ CV Matching → CV uploaded, looking for jobs
│  ├─ Job Description → Has requirements, responsibilities
│  └─ Unknown → Generic document
├─ Extract contact info (name, email, phone)
├─ Calculate word count
└─ Store on server
    ↓
Frontend receives response:
├─ Document context stored in state
├─ System message added to chat
├─ Lead profile updated with contact data
├─ Create document summary prompt (customized by type)
    ↓
Auto-send document + prompt to AI:
├─ CV Analysis: Structure, strengths, improvements
├─ CV Matching: Profile overview, matching positions
├─ Job Description: Requirements analysis, qualification questions
    ↓
AI generates detailed analysis
    ↓
Analysis appears in chat as bot message
    ↓
User can follow up with document-related questions
```

## Auto-Email Trigger Logic

```
Page Events Monitored:
├─ window.beforeunload (user closing page)
├─ document.visibilitychange (tab hidden)
└─ Regular timer (optional)
    ↓
Checks (in order):
┌─────────────────────────────────────────┐
│ 1. Has email already been sent? (Ref)  │ → YES: ABORT
│ 2. Is marked in sessionStorage?        │ → YES: ABORT
│ 3. Meaningful conversation? (≥2 msgs)  │ → NO: ABORT
│ 4. Qualified lead OR document?         │ → NO: ABORT
│    (lead_score ≥40 OR has_document)    │
└─────────────────────────────────────────┘
    ↓
[ALL CHECKS PASS]
    ↓
Immediately Mark as Sent:
├─ Set autoEmailSentRef.current = true
└─ Set sessionStorage key = 'true'
    ↓
Send Email via emailService.sendSummary():
├─ Recipient: Admin email
├─ Content:
│  ├─ Full conversation transcript
│  ├─ Extracted lead data
│  ├─ Document reference (if uploaded)
│  ├─ Session ID
│  └─ Timestamp
└─ Keepalive: true (complete even if user leaves)
    ↓
Log completion (console)
```

## State Management (React Hooks)

```
App.tsx State Variables:
├─ chatMessages (ChatMessage[]) → All messages in conversation
├─ sessionId (string) → Unique session identifier
├─ consentGranted (boolean) → GDPR acceptance
├─ leadProfile (Partial<LeadProfile>) → Extracted lead data
│  ├─ name, email, phone, company
│  ├─ leadType, position, techStack
│  └─ experienceLevel, urgency, leadScore
├─ documentContext (DocumentContext | null) → Uploaded file data
│  ├─ type (cv / cv_matching / job_description)
│  ├─ text (extracted content)
│  ├─ filename, wordCount, serverPath
│  └─ contactData (extracted from doc)
├─ messageDraft (string) → Text being typed
├─ quickReplies (string[]) → Available quick reply options
├─ isTyping (boolean) → Loading state
├─ ttsEnabled (boolean) → Text-to-speech active?
├─ ttsAutoPlay (boolean) → Auto-play speech?
├─ uploadStatus → File upload progress
├─ speakingMessageId (string | null) → Which message is playing
└─ isOffline (boolean) → Network status

Refs (for avoiding race conditions):
├─ conversationRef → Current conversation (always fresh)
├─ leadProfileRef → Current lead profile (always fresh)
├─ documentContextRef → Current document (always fresh)
├─ autoEmailSentRef → Email sent this session?
├─ meetingOfferedRef → Meeting offered this session?
├─ uploadSuggestedRef → Upload suggested this session?
└─ lastSpokenMessageRef → Last message that was spoken

Local Storage Keys:
├─ 'noba-chat-history' → Persistent message list
├─ 'noba-gdpr-consent' → Consent status
├─ 'noba-lead-profile' → Extracted lead data
├─ 'noba-session-id' → Current session
├─ 'noba-tts-enabled' → Speech synthesis preference
└─ 'noba-tts-autoplay' → Auto-play preference

Session Storage Keys:
└─ `email_sent_${sessionId}` → Email sent flag (prevents duplicates)
```

## Component Communication Pattern

```
App.tsx (State Container)
    │
    ├─→ SettingsDrawer
    │    └─ onNewChat() → Reset all state & local storage
    │    └─ onEmailSummary() → handleEmailSummary()
    │    └─ onToggleTts() → setTtsEnabled()
    │
    ├─→ ChatMessageList
    │    ├─ messages (prop) → Display chat
    │    ├─ onSpeak() → handleSpeak()
    │    ├─ onStopSpeaking() → handleStopSpeaking()
    │    └─ speakingMessageId (prop) → Highlight playing
    │
    ├─→ MessageComposer
    │    ├─ value (prop) → Text input value
    │    ├─ onChange() → setMessageDraft()
    │    ├─ onSubmit() → handleUserMessage()
    │    └─ onOpenUpload() → setIsUploadOpen()
    │
    ├─→ QuickReplies
    │    ├─ options (prop) → Display buttons
    │    └─ onSelect() → handleQuickReply()
    │
    ├─→ DocumentUploadSheet
    │    ├─ open (prop)
    │    ├─ onClose() → setIsUploadOpen(false)
    │    ├─ onUpload() → handleDocumentUpload()
    │    └─ status (prop) → Display progress
    │
    └─→ EmailSummaryModal
         ├─ open (prop)
         ├─ onClose() → setIsEmailModalOpen(false)
         └─ onSubmit() → handleEmailSummary()
```

## Services Architecture

```
src/services/

apiClient.ts
    └─ Wrapper around fetch()
       ├─ Error handling
       ├─ JSON serialization
       └─ Custom headers (CORS, Content-Type)

chatService.ts
    ├─ mapMessagesToHistory() → Format for backend
    ├─ formatDocumentContext() → Prepare doc data
    └─ sendMessage() → POST to /chatbot-api.php
       Returns: ChatResponsePayload

loggerService.ts
    └─ logConversation() → POST to /chatbot-logger.php
       ├─ Logs messages to server
       ├─ Extracts lead signals
       └─ Returns: LoggerResponsePayload

uploadService.ts
    └─ uploadDocument() → POST to /upload-document.php
       ├─ FormData multipart upload
       ├─ Progress tracking
       └─ Returns: UploadResponse

emailService.ts
    └─ sendSummary() → POST to /send-summary.php
       ├─ Recipient email
       ├─ Conversation transcript
       └─ Returns: EmailSummaryServiceResponse

session.ts
    └─ generateSessionId() → Create UUID
       └─ Unique identifier per user

leadQualification.ts
    ├─ determineDocumentTypeFromConversation()
    │  └─ Auto-detect if CV/Job needed
    └─ isQualifiedLead()
       └─ Check if lead score sufficient
```

## External Integrations

```
┌──────────────────────────────────────┐
│   Google Gemini API (AI Engine)      │
│   chatbot-api.php → gemini-2.5-flash│
│   (Generates responses, quick replies)
└──────────────────────────────────────┘
         ↑
         │ (API Key in PHP config)
         │
┌──────────────────────────────────────┐
│   HubSpot Calendar Integration       │
│   meetingUrl in config.ts             │
│   MeetingModal opens URL              │
└──────────────────────────────────────┘
         ↑
         │ (HubSpot meeting link)
         │
┌──────────────────────────────────────┐
│   Email Service (send-summary.php)   │
│   Sends via PHP mail() or SMTP        │
└──────────────────────────────────────┘
```

