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
    chat: '/backend/chatbot-api.php',
    upload: '/backend/upload-document.php',
    emailSummary: '/backend/send-summary.php',
    logger: '/backend/chatbot-logger.php',
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
  ],
};

export const STORAGE_KEYS = {
  chatHistory: 'noba-chat-history',
  gdprConsent: 'noba-gdpr-consent',
  ttsEnabled: 'noba-tts-enabled',
  ttsAutoPlay: 'noba-tts-autoplay',
  leadProfile: 'noba-lead-profile',
  sessionId: 'noba-session-id',
  preferredLanguage: 'noba-preferred-language',
};
