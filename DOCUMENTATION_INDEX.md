# NOBA Chatbot - Documentation Index

## Overview
Complete exploration and documentation of the NOBA KI-Berater chatbot project. This German-language AI chatbot serves as a recruiting assistant for NOBA Experts, specializing in IT and Engineering recruitment.

---

## Core Documentation Files

### 1. PROJECT_EXPLORATION.md
**Comprehensive project overview and analysis**
- Chatbot personality & system prompts
- Menu & navigation structure
- Frontend and backend architecture
- Configuration and constants
- Data flow and conversation lifecycle
- Security & compliance
- TypeScript types and interfaces
- Build & deployment information

**Start here for:** Understanding the complete system architecture

### 2. ARCHITECTURE_DIAGRAM.md
**Visual diagrams and flow charts**
- High-level system architecture (ASCII diagrams)
- Frontend component hierarchy
- Data flow diagrams (message processing)
- Lead qualification flow
- Document upload & processing
- Auto-email trigger logic
- State management visualization
- Component communication patterns
- Services architecture
- External integrations

**Start here for:** Understanding how components interact

### 3. QUICK_REFERENCE.md
**Quick lookup guide for common tasks**
- File locations for quick edits
- Common customizations (5-minute changes)
- API integration points
- Key state variables
- Main event handlers
- Component props
- TypeScript types
- Configuration values
- Debugging tips
- Color variables
- Special features

**Start here for:** Making quick changes or debugging

---

## Project Structure

### Frontend (React/TypeScript)
```
src/
├── App.tsx                  # Main application (840 lines, 30KB)
├── constants/
│   ├── systemPrompt.ts     # AI personality (32 lines)
│   └── config.ts           # Configuration (52 lines)
├── components/             # 9 UI components
│   ├── ChatMessageList.tsx
│   ├── MessageComposer.tsx
│   ├── QuickReplies.tsx
│   ├── SettingsDrawer.tsx
│   ├── ConsentModal.tsx
│   ├── DocumentUploadSheet.tsx
│   ├── EmailSummaryModal.tsx
│   ├── MeetingModal.tsx
│   └── StatusBanner.tsx
├── services/               # 7 service modules
│   ├── chatService.ts
│   ├── apiClient.ts
│   ├── emailService.ts
│   ├── uploadService.ts
│   ├── loggerService.ts
│   ├── session.ts
│   └── leadQualification.ts
├── hooks/                  # 4 custom hooks
│   ├── useAutoResizeTextarea.ts
│   ├── useLocalStorage.ts
│   ├── useSpeechSynthesis.ts
│   └── useSpeechRecognition.ts
└── types/
    └── index.ts           # TypeScript interfaces
```

### Backend (PHP)
```
backend/
├── chatbot-api.php              # Main API with Gemini integration (1000+ lines)
├── chatbot-logger.php           # Conversation logging & extraction
├── chatbot-conversations.json   # Persistent storage
├── upload-document.php          # Document upload & text extraction
├── send-summary.php             # Email summary generation
├── admin-api.php                # Admin dashboard API
├── hubspot-config.php           # HubSpot integration
└── [utility scripts]
```

---

## Key Features

### 1. AI-Powered Chatbot
- **AI Engine:** Google Gemini API (gemini-2.5-flash-lite)
- **Personality:** Defined in system prompt (DE, recruiting-focused)
- **Context Awareness:** Enriched with homepage content and job listings
- **Dynamic Responses:** Backend generates context-appropriate quick replies

### 2. Lead Qualification
- **Automatic Detection:** Identifies employer vs candidate vs info-seeker
- **Profile Extraction:** Captures name, email, phone, company, tech stack
- **Lead Scoring:** Calculates lead quality (0-100)
- **Qualification Logic:** Auto-emails admin when lead score ≥40 or document uploaded

### 3. Document Processing
- **Formats:** PDF, DOC, DOCX
- **Auto-Detection:** CV, Resume matching, Job description, Unknown
- **Text Extraction:** Full document text with contact info parsing
- **AI Analysis:** Automatic summarization and feedback

### 4. Voice Features
- **Text-to-Speech:** 14 languages, auto-play option
- **Speech Recognition:** German (de-DE), continuous listening
- **Microphone Input:** Hands-free message composition

### 5. User Interface
- **Responsive Design:** Mobile, tablet, desktop
- **Real-time Status:** Connection status indicator
- **Animations:** Framer Motion for smooth transitions
- **Accessibility:** GDPR consent required, local storage for preferences

### 6. Session Management
- **Unique Sessions:** Per-user session IDs
- **Persistence:** Chat history and lead profile saved locally
- **Auto-save:** Server-side logging after every message
- **Duplicate Prevention:** Email sent only once per session

---

## Configuration Points

### To Change Chatbot Personality
Edit `/src/constants/systemPrompt.ts`
- Mission & objectives
- User type detection keywords
- Tone & language rules
- Services descriptions
- Contact information

### To Change Branding
Edit `/src/constants/config.ts`
- Company name
- Primary color (#FF7B29)
- Admin email address
- Meeting URL (HubSpot)
- API endpoints

### To Change Welcome Message
Edit `/src/App.tsx` (lines 213-222)
- Initial greeting text
- Quick reply buttons

### To Modify Menu
Edit `/src/components/SettingsDrawer.tsx`
- Add/remove menu items
- Change button labels
- Add new features

---

## Technology Stack

### Frontend
- React 18+ (TypeScript)
- Vite (build tool)
- Tailwind CSS (styling)
- Framer Motion (animations)
- Lucide React (icons)
- Web APIs: Speech Recognition, Speech Synthesis, localStorage

### Backend
- PHP 7.4+
- Google Gemini API
- HubSpot (optional CRM)
- cURL for external requests
- JSON for data storage

### External Services
- Google Gemini API: AI responses
- HubSpot: Meeting scheduling
- SMTP/Email: Summary delivery

---

## Data Flow Summary

### User Message → AI Response
```
1. User types/speaks message
2. Message added to local state
3. Immediately logged to server
4. Sent to backend with:
   - Last 10 messages (history)
   - Document context (if any)
   - System prompt (AI instructions)
   - Session ID
5. Backend:
   - Enriches with homepage/job content
   - Calls Google Gemini API
   - Extracts lead signals
   - Generates quick replies
6. Response returned to frontend
7. Message added to chat
8. Quick replies updated
9. Lead profile updated
10. Chat logged again to server
11. Optional: Voice played, meeting offered, upload suggested
```

### Document Upload Flow
```
1. User selects file
2. File validated (type, size)
3. Sent to backend
4. Backend extracts text
5. Contact info parsed
6. Document type detected
7. Response with extracted content
8. Frontend stores document context
9. Document summary prompt created
10. AI analyzes document
11. Analysis displayed in chat
```

### Auto-Email Flow
```
1. User leaves page (beforeunload)
2. Check: Not already sent?
3. Check: Qualified lead or document?
4. Mark as sent (prevent duplicates)
5. Email sent with:
   - Full conversation
   - Extracted lead data
   - Document reference
   - Session ID
6. Only sent once per session
```

---

## Important Thresholds & Limits

| Setting | Value | Purpose |
|---------|-------|---------|
| Lead Score Threshold | 40 | Minimum for auto-email |
| Message History Sent | 10 | Last N messages to backend |
| Max Message Length | 500 chars | Frontend input limit |
| Max Document Size | 10 MB | File upload limit |
| Rate Limit | 30/min | Backend requests per minute |
| TTS Pause Threshold | 2s | Silence detection |
| Meeting Offer Trigger | 4 messages | After conversation starts |
| Context Cache | 1 hour | Homepage & job listings |
| Email Flag Duration | Session | Single window/tab lifespan |

---

## Security & Privacy

### GDPR Compliance
- Explicit consent modal required
- Separate consent tracking in localStorage
- Session-based data handling
- User can clear all data anytime

### Security Measures
- Input validation & sanitization
- HTML escaping of user messages
- Rate limiting (30 requests/minute)
- CORS origin whitelist
- API keys server-side only
- HTTPS production only

### Data Storage
- Client: localStorage (chat, profile, consent)
- Server: JSON file (conversations)
- Server: Filesystem (documents)
- Temporary: sessionStorage (email flag)

---

## Debugging Guide

### Check Stored Data
```javascript
// Browser console
localStorage.getItem('noba-chat-history')  // Messages
localStorage.getItem('noba-lead-profile')  // Lead data
localStorage.getItem('noba-session-id')    // Session
sessionStorage.getItem('email_sent_*')     // Email flag
```

### Test API Endpoint
```javascript
fetch('https://chatbot.noba-experts.de/chatbot-api.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    session_id: 'test',
    message: 'Test',
    history: [],
    system_prompt: 'Test'
  })
})
```

### Clear All Data
```javascript
localStorage.clear()
sessionStorage.clear()
// Refresh page
```

---

## Maintenance Tasks

### Regular Checks
- Monitor API rate limits
- Check error logs (browser console, server logs)
- Verify email delivery (check admin inbox)
- Test document upload (PDF/DOC/DOCX)
- Check lead extraction accuracy

### Content Updates
- Update system prompt as needed
- Refresh homepage content cache
- Review job listings accuracy
- Update contact information

### Performance Optimization
- Monitor message history length
- Check document storage usage
- Review API response times
- Optimize images/assets

---

## Common Issues & Solutions

### No AI Response
1. Check API key in backend
2. Verify internet connection
3. Check rate limit (30/min)
4. Review error logs

### Messages Not Saved
1. Check localStorage enabled
2. Verify sufficient storage space
3. Check no private browsing mode
4. Review server logging

### Email Not Sent
1. Verify lead score ≥40 or document uploaded
2. Check admin email configuration
3. Review email logs
4. Test with manual send

### Voice Not Working
1. Check microphone permissions
2. Verify browser support (Chrome, Edge, Safari)
3. Check language set to de-DE
4. Test with another browser

---

## Quick Links

### Files to Edit (Common Tasks)
- System Prompt: `src/constants/systemPrompt.ts`
- Branding: `src/constants/config.ts`
- Welcome Message: `src/App.tsx` (lines 213-222)
- Menu Items: `src/components/SettingsDrawer.tsx`
- Quick Replies: `src/components/QuickReplies.tsx`

### API Endpoints
- Chat: `https://chatbot.noba-experts.de/chatbot-api.php`
- Upload: `https://chatbot.noba-experts.de/upload-document.php`
- Email: `https://chatbot.noba-experts.de/send-summary.php`
- Logger: `https://chatbot.noba-experts.de/chatbot-logger.php`

### External Services
- Google Gemini: https://ai.google.dev
- HubSpot: https://www.hubspot.com
- NOBA Website: https://www.noba-experts.de

---

## Next Steps

1. **Review PROJECT_EXPLORATION.md** for complete system understanding
2. **Check ARCHITECTURE_DIAGRAM.md** for visual flows
3. **Use QUICK_REFERENCE.md** for quick edits
4. **Test the chatbot** with various user types
5. **Monitor lead quality** and extracted data
6. **Iterate on system prompt** based on results

---

## Document Version
Created: November 2, 2025
Last Updated: November 2, 2025
Project: NOBA KI-Berater Chatbot (Final)

---

## Additional Resources

- Main Repo: `/home/jbk/Homepage Git/Chatbot final/`
- Frontend Src: `src/`
- Backend: `backend/`
- Config: `src/constants/`
- Components: `src/components/`
- Services: `src/services/`

