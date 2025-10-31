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
    backendBaseUrl: (import.meta.env.VITE_BACKEND_BASE_URL as string | undefined) ?? '',
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
};

export const STORAGE_KEYS = {
  chatHistory: 'noba-chat-history',
  gdprConsent: 'noba-gdpr-consent',
  ttsEnabled: 'noba-tts-enabled',
  ttsAutoPlay: 'noba-tts-autoplay',
  leadProfile: 'noba-lead-profile',
  sessionId: 'noba-session-id',
};
