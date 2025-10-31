import { AuthorRole, ChatMessage, LeadProfile } from '@/types';

const TERMIN_KEYWORDS = [
  'termin',
  'gespräch',
  'telefon',
  'anruf',
  'meeting',
  'kontakt',
  'zurückrufen',
  'rückruf',
  'besprechen',
  'treffen',
  'kennenlernen',
  'interesse',
  'gerne mehr',
  'möchte ich',
  'würde gerne',
  'kann ich',
  'können wir',
];

const EMPLOYER_KEYWORDS = ['mitarbeiter', 'team', 'besetzen', 'stelle besetzen', 'anstellung', 'recruiting', 'personal'];

// Keywords für CV-Optimierung (strukturierte Analyse)
const CV_OPTIMIZATION_KEYWORDS = [
  'cv optimier',
  'lebenslauf optimier',
  'cv verbesser',
  'lebenslauf verbesser',
  'cv feedback',
  'cv analyse',
  'lebenslauf feedback',
  'lebenslauf analyse',
  'bewerbungsunterlagen',
  'cv hilfe',
  'lebenslauf hilfe',
];

// Keywords für Job-Suche (CV für Matching nutzen)
const JOB_SEARCH_KEYWORDS = ['job suchen', 'stelle suchen', 'position suchen', 'karriere', 'job finden'];

export const determineDocumentTypeFromConversation = (messages: ChatMessage[]) => {
  const userMessages = messages
    .filter((message) => message.role === AuthorRole.USER)
    .map((message) => message.text.toLowerCase())
    .join(' ');

  if (!userMessages) return 'unknown' as const;

  if (EMPLOYER_KEYWORDS.some((keyword) => userMessages.includes(keyword))) {
    return 'job_description' as const;
  }

  // Explizite CV-Optimierung → strukturierte Analyse
  if (CV_OPTIMIZATION_KEYWORDS.some((keyword) => userMessages.includes(keyword))) {
    return 'cv' as const;
  }

  // Job-Suche → CV für Matching nutzen
  if (JOB_SEARCH_KEYWORDS.some((keyword) => userMessages.includes(keyword))) {
    return 'cv_matching' as const;
  }

  return 'unknown' as const;
};

export const isQualifiedLead = (leadData: Partial<LeadProfile>, messages: ChatMessage[]) => {
  if (!leadData) return false;

  if (leadData.email || leadData.phone) {
    return true;
  }

  const conversationText = messages.map((message) => message.text.toLowerCase()).join(' ');
  if (TERMIN_KEYWORDS.some((keyword) => conversationText.includes(keyword))) {
    return true;
  }

  const dataPoints = [
    leadData.name,
    leadData.company,
    leadData.position,
    leadData.techStack && leadData.techStack.length > 0,
    leadData.experienceLevel,
    leadData.location,
  ].filter(Boolean).length;

  if (dataPoints >= 3 && (leadData.leadScore ?? 0) >= 40) {
    return true;
  }

  if ((leadData.leadType === 'employer' || leadData.leadType === 'candidate') && dataPoints >= 2) {
    return true;
  }

  return false;
};
