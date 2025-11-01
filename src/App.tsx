import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { APP_CONFIG, STORAGE_KEYS } from '@/constants/config';
import { useLocalStorage } from '@/hooks/useLocalStorage';
import { useSpeechSynthesis } from '@/hooks/useSpeechSynthesis';
import { chatService, mapMessagesToHistory } from '@/services/chatService';
import { emailService } from '@/services/emailService';
import { determineDocumentTypeFromConversation, isQualifiedLead } from '@/services/leadQualification';
import { loggerService } from '@/services/loggerService';
import { generateSessionId } from '@/services/session';
import { uploadService } from '@/services/uploadService';
import {
  AuthorRole,
  ChatMessage,
  ChatResponsePayload,
  DocumentContext,
  LeadProfile,
} from '@/types';
import { ChatMessageList } from '@/components/ChatMessageList';
import { ConsentModal } from '@/components/ConsentModal';
import { DocumentUploadSheet } from '@/components/DocumentUploadSheet';
import { EmailSummaryModal } from '@/components/EmailSummaryModal';
import { MeetingModal } from '@/components/MeetingModal';
import { MessageComposer } from '@/components/MessageComposer';
import { QuickReplies } from '@/components/QuickReplies';
import { SettingsDrawer } from '@/components/SettingsDrawer';
import { StatusBanner } from '@/components/StatusBanner';

const buildHistory = (messages: ChatMessage[]) =>
  mapMessagesToHistory(messages.filter((message) => message.role !== AuthorRole.SYSTEM));

const createBotMessage = (text: string, metadata?: ChatMessage['metadata']): ChatMessage => ({
  id: `bot-${crypto.randomUUID?.() ?? Date.now().toString(36)}`,
  role: AuthorRole.BOT,
  text,
  timestamp: new Date().toISOString(),
  metadata,
});

const createSystemMessage = (text: string, metadata?: ChatMessage['metadata']): ChatMessage => ({
  id: `system-${crypto.randomUUID?.() ?? Date.now().toString(36)}`,
  role: AuthorRole.SYSTEM,
  text,
  timestamp: new Date().toISOString(),
  metadata,
});

const trimText = (text: string, limit = 1500) => (text.length > limit ? `${text.slice(0, limit)}…` : text);

const buildDocumentSummaryPrompt = (context: DocumentContext) => {
  const preview = trimText(context.text, 1500);
  const contactInfo = context.contactData
    ? [
        context.contactData.name ? `- Name: ${context.contactData.name}` : null,
        context.contactData.email ? `- E-Mail: ${context.contactData.email}` : null,
        context.contactData.phone ? `- Telefon: ${context.contactData.phone}` : null,
      ]
        .filter(Boolean)
        .join('\n')
    : '';

  if (context.type === 'cv') {
    return `Ich habe meinen Lebenslauf hochgeladen. Bitte analysiere ihn professionell und gib mir detailliertes Feedback:\n\n---\n${preview}\n---\n\n${
      contactInfo ? `Kontaktdaten aus dem Dokument:\n${contactInfo}\n\n` : ''
    }AUFGABE - PROFESSIONELLE CV-ANALYSE:\n\n` +
      `1. **STRUKTUR** (2-3 Sätze): Wie ist der CV aufgebaut? Ist die Chronologie klar?\n\n` +
      `2. **STÄRKEN** (3-4 Punkte): Was ist besonders gut gelungen?\n\n` +
      `3. **VERBESSERUNGSPOTENZIAL** (3-5 konkrete Punkte): Was könnte optimiert werden?\n\n` +
      `4. **TECHNISCHE SKILLS**: Sind sie klar kategorisiert und mit Level-Angaben?\n\n` +
      `5. **ACHIEVEMENTS**: Sind messbare Erfolge benannt oder nur Aufgaben?\n\n` +
      `6. **GESAMTBEWERTUNG** (1-10 Punkte mit Begründung)\n\n` +
      `Bitte antworte prägnant und konstruktiv mit konkreten Beispielen aus meinem CV!`;
  }

  if (context.type === 'cv_matching') {
    return `Ich habe meinen Lebenslauf hochgeladen, um die richtige Position zu finden:\n\n---\n${preview}\n---\n\n${
      contactInfo ? `Kontaktdaten aus dem Dokument:\n${contactInfo}\n\n` : ''
    }AUFGABE - PROFIL-ANALYSE FÜR JOBMATCHING:\n\n` +
      `1. **PROFIL-ÜBERBLICK** (3-4 Sätze):\n   - Hauptkompetenzen & Technologien\n   - Erfahrungslevel\n   - Besondere Stärken\n\n` +
      `2. **PASSENDE POSITIONEN** (2-3 konkrete Vorschläge):\n   Basierend auf dem CV, welche Rollen passen?\n\n` +
      `3. **QUALIFIZIERENDE FRAGEN** (2-3 Fragen):\n   - Bevorzugter Bereich (Frontend/Backend/DevOps/etc.)?\n   - Standort-Präferenzen?\n   - Gehaltsvorstellungen?\n   - Weitere Wünsche?\n\n` +
      `Sei konversationell und hilf mir die perfekte Position zu finden!`;
  }

  return `Hier ist unsere Stellenbeschreibung:\n\n---\n${preview}\n---\n\n${
    contactInfo ? `Kontaktdaten aus dem Dokument:\n${contactInfo}\n\n` : ''
  }AUFGABE: Analysiere die Stellenbeschreibung im Detail und führe danach eine professionelle Bedarfsanalyse durch.\n\n` +
    `1. ZUSAMMENFASSUNG (3-4 Sätze):\n   - Position/Rolle\n   - Erforderliche Skills & Technologien\n   - Standort & ggf. Gehalt\n   - Besonderheiten der Stelle\n\n` +
    `2. DANN STELLE GEZIELT 2-3 QUALIFIZIERENDE FRAGEN:\n   - Gewünschtes Erfahrungslevel (Junior/Senior)?\n   - Start-Zeitpunkt / Dringlichkeit?\n   - Homeoffice-Möglichkeit?\n   - Weitere wichtige Anforderungen?\n\n` +
    `Beginne mit: "Vielen Dank für die Stellenbeschreibung! Ich habe sie analysiert:"`;
};

const normalizeLeadProfile = (value?: Partial<LeadProfile> & Record<string, unknown>): Partial<LeadProfile> => {
  if (!value) return {};

  return {
    ...value,
    leadType: (value.leadType ?? value.lead_type) as LeadProfile['leadType'],
    leadScore: (value.leadScore ?? value.lead_score) as number | undefined,
    techStack: (value.techStack ?? value.tech_stack) as string[] | undefined,
  };
};

const normalizeContactData = (value?: Partial<LeadProfile>): Partial<LeadProfile> | undefined => {
  if (!value) return undefined;
  return {
    name: value.name,
    email: value.email,
    phone: value.phone,
    company: value.company,
  };
};

const hasMeaningfulConversation = (messages: ChatMessage[]) =>
  messages.filter((message) => message.role !== AuthorRole.SYSTEM).length >= 2;

export const App = () => {
  const {
    value: consentGranted,
    setValue: setConsentGranted,
  } = useLocalStorage<boolean>(STORAGE_KEYS.gdprConsent, false);

  const {
    value: sessionId,
    setValue: setSessionId,
  } = useLocalStorage<string>(STORAGE_KEYS.sessionId, '');

  const {
    value: chatMessages,
    setValue: setChatMessages,
    removeValue: clearChatMessages,
  } = useLocalStorage<ChatMessage[]>(STORAGE_KEYS.chatHistory, []);

  const {
    value: leadProfile,
    setValue: setLeadProfile,
    removeValue: clearLeadProfile,
  } = useLocalStorage<Partial<LeadProfile>>(STORAGE_KEYS.leadProfile, {});

  const {
    value: ttsEnabled,
    setValue: setTtsEnabled,
  } = useLocalStorage<boolean>(STORAGE_KEYS.ttsEnabled, true);

  const {
    value: ttsAutoPlay,
    setValue: setTtsAutoPlay,
  } = useLocalStorage<boolean>(STORAGE_KEYS.ttsAutoPlay, false);

  const [messageDraft, setMessageDraft] = useState('');
  const [quickReplies, setQuickReplies] = useState<string[]>([]);
  const [isTyping, setIsTyping] = useState(false);
  const [isSettingsOpen, setIsSettingsOpen] = useState(false);
  const [isUploadOpen, setIsUploadOpen] = useState(false);
  const [uploadStatus, setUploadStatus] = useState<{ variant: 'idle' | 'loading' | 'success' | 'error'; message?: string }>({
    variant: 'idle',
  });
  const [isUploading, setIsUploading] = useState(false);
  const [uploadDocumentType, setUploadDocumentType] = useState<DocumentContext['type']>('unknown');
  const [documentContext, setDocumentContext] = useState<DocumentContext | null>(null);
  const [isEmailModalOpen, setIsEmailModalOpen] = useState(false);
  const [isEmailSubmitting, setIsEmailSubmitting] = useState(false);
  const [isMeetingModalOpen, setIsMeetingModalOpen] = useState(false);
  const [isOffline, setIsOffline] = useState(typeof navigator !== 'undefined' ? !navigator.onLine : false);

  const autoEmailSentRef = useRef(false);
  const meetingOfferedRef = useRef(false);
  const uploadSuggestedRef = useRef(false);
  const lastSpokenMessageRef = useRef<string | null>(null);
  const conversationRef = useRef(chatMessages);
  const leadProfileRef = useRef(leadProfile);
  const documentContextRef = useRef(documentContext);

  const [speakingMessageId, setSpeakingMessageId] = useState<string | null>(null);

  const { supportsSpeech, speak, stop, isSpeaking } = useSpeechSynthesis(ttsEnabled, {
    lang: APP_CONFIG.tts.defaultLanguage,
  });

  useEffect(() => {
    conversationRef.current = chatMessages;
  }, [chatMessages]);

  useEffect(() => {
    leadProfileRef.current = leadProfile;
  }, [leadProfile]);

  useEffect(() => {
    documentContextRef.current = documentContext;
  }, [documentContext]);

  useEffect(() => {
    if (!sessionId) {
      setSessionId(generateSessionId());
    }
  }, [sessionId, setSessionId]);

  useEffect(() => {
    const handleOnline = () => setIsOffline(false);
    const handleOffline = () => setIsOffline(true);

    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    return () => {
      window.removeEventListener('online', handleOnline);
      window.removeEventListener('offline', handleOffline);
    };
  }, []);

  useEffect(() => {
    if (!consentGranted || chatMessages.length > 0) return;

    const welcome = createBotMessage(
      '👋 Hallo! Ich bin der KI-Berater von NOBA Experts.\n\n⚠️ Hinweis: Ich arbeite KI-gestützt und kann Fehler machen. Für verbindliche Auskünfte wenden Sie sich gerne direkt an unser Recruiting-Team. Wie kann ich Sie heute unterstützen?',
    );
    setChatMessages([welcome]);
    // Set initial quick replies
    setQuickReplies([
      '👔 Job suchen',
      '🔍 Mitarbeiter finden',
      '💡 Unsere Services'
    ]);
  }, [consentGranted, chatMessages.length, setChatMessages, setQuickReplies]);

  useEffect(() => {
    if (!consentGranted) return;

    const attemptAutoEmail = async () => {
      // Doppelte Absicherung gegen mehrfache E-Mails
      const emailSentKey = `email_sent_${sessionId}`;

      if (autoEmailSentRef.current) {
        console.log('⚠️ E-Mail bereits gesendet (Ref)');
        return;
      }

      if (sessionStorage.getItem(emailSentKey) === 'true') {
        console.log('⚠️ E-Mail bereits gesendet (SessionStorage)');
        return;
      }

      const conversation = conversationRef.current;
      if (!conversation || !hasMeaningfulConversation(conversation)) return;

      const lead = leadProfileRef.current ?? {};
      const hasDocument = !!documentContextRef.current;

      // NUR senden wenn Lead qualifiziert ist ODER Dokument hochgeladen wurde
      const isLeadQualified = isQualifiedLead(lead, conversation) || hasDocument;

      if (!isLeadQualified) {
        console.log('⚠️ Keine Email - kein qualifizierter Lead und kein Dokument');
        return;
      }

      console.log('📧 Sende automatische E-Mail an Admin...', {
        messages: conversation.length,
        leadScore: lead.leadScore,
        hasEmail: !!lead.email,
        hasPhone: !!lead.phone,
        hasDocument,
      });

      // SOFORT markieren um Race Conditions zu verhindern
      autoEmailSentRef.current = true;
      sessionStorage.setItem(emailSentKey, 'true');

      try {
        await emailService.sendSummary(
          {
            recipient_email: APP_CONFIG.notifications.adminEmail,
            conversation: buildHistory(conversation),
            extracted_data: lead,
            include_full_chat: true,
            session_id: sessionId,
            document_context: documentContextRef.current
              ? {
                  type: documentContextRef.current.type,
                  filename: documentContextRef.current.filename,
                  word_count: documentContextRef.current.wordCount,
                  server_path: documentContextRef.current.serverPath,
                  contact_data: documentContextRef.current.contactData,
                  text: trimText(documentContextRef.current.text, 2000),
                }
              : undefined,
            auto_sent: true,
          },
          { keepalive: true },
        );
        console.log('✅ Auto-E-Mail erfolgreich gesendet');
      } catch (error) {
        console.error('❌ Auto-E-Mail fehlgeschlagen:', error);
        // Bei Fehler Flag zurücksetzen damit Retry möglich ist
        autoEmailSentRef.current = false;
        sessionStorage.removeItem(emailSentKey);
      }
    };

    const handleBeforeUnload = () => {
      attemptAutoEmail();
    };

    const handleVisibilityChange = () => {
      if (document.visibilityState === 'hidden') {
        attemptAutoEmail();
      }
    };

    window.addEventListener('beforeunload', handleBeforeUnload);
    document.addEventListener('visibilitychange', handleVisibilityChange);

    return () => {
      window.removeEventListener('beforeunload', handleBeforeUnload);
      document.removeEventListener('visibilitychange', handleVisibilityChange);
    };
  }, [consentGranted, sessionId]);

  useEffect(() => {
    console.log('🎯 Quick replies state changed to:', quickReplies);
  }, [quickReplies]);

  useEffect(() => {
    if (!ttsEnabled) {
      stop();
      setSpeakingMessageId(null);
    }
  }, [ttsEnabled, stop]);

  useEffect(() => {
    if (!ttsEnabled || !ttsAutoPlay || !supportsSpeech) return;
    if (!chatMessages.length) return;

    const lastMessage = chatMessages[chatMessages.length - 1];
    if (lastMessage.role !== AuthorRole.BOT) return;

    if (lastSpokenMessageRef.current === lastMessage.id) return;

    speak(lastMessage.text);
    setSpeakingMessageId(lastMessage.id);
    lastSpokenMessageRef.current = lastMessage.id;
  }, [chatMessages, speak, supportsSpeech, ttsAutoPlay, ttsEnabled]);

  useEffect(() => {
    if (!isSpeaking && speakingMessageId) {
      setSpeakingMessageId(null);
    }
  }, [isSpeaking, speakingMessageId]);

  const setNewQuickReplies = (options?: string[]) => {
    console.log('🔄 setNewQuickReplies called with:', options);

    if (!options || options.length === 0) {
      console.log('⚠️ No options provided, clearing quick replies');
      setQuickReplies([]);
      return;
    }

    // Always show quick replies when they come from backend
    // The backend is smart enough to send context-appropriate quick replies
    console.log('💾 Setting quick replies state to:', options);
    setQuickReplies(options);
  };

  const updateLeadProfile = (payload?: ChatResponsePayload['lead_signals'] | Partial<LeadProfile>) => {
    if (!payload) return;

    const normalized = normalizeLeadProfile(payload as Partial<LeadProfile> & Record<string, unknown>);
    const merged = { ...leadProfileRef.current, ...normalized };
    setLeadProfile(merged);
  };

  const offerMeetingIfQualified = (conversation: ChatMessage[], profile: Partial<LeadProfile>) => {
    if (meetingOfferedRef.current) return;
    const hasContact = Boolean(profile.email || profile.phone);
    if (!hasContact) return;

    const nonSystemMessages = conversation.filter((message) => message.role !== AuthorRole.SYSTEM);
    if (nonSystemMessages.length < 4) return;

    meetingOfferedRef.current = true;

    setChatMessages((prev) => [
      ...prev,
      createBotMessage('Möchten Sie einen persönlichen Beratungstermin mit unserem Team vereinbaren?'),
    ]);

    setNewQuickReplies(['📅 Ja, Termin vereinbaren', '👋 Nein, danke']);
  };

  const ensureUploadSuggestion = (conversation: ChatMessage[]) => {
    if (uploadSuggestedRef.current || documentContextRef.current || isUploadOpen) return;

    const suggestedType = determineDocumentTypeFromConversation(conversation);
    if (suggestedType === 'unknown') return;

    uploadSuggestedRef.current = true;
    setUploadDocumentType(suggestedType);
    setIsUploadOpen(true);
  };

  const handleAssistantResponse = async (
    response: ChatResponsePayload,
    metadata?: ChatMessage['metadata'],
  ) => {
    const fallback = 'Entschuldigung, es ist ein Fehler aufgetreten. Bitte versuchen Sie es erneut.';
    const messageText = response.message?.trim() || fallback;
    const assistantMessage = createBotMessage(messageText, {
      ...metadata,
      quickReplies: response.quick_replies,
      leadQualified: (response.lead_signals?.lead_score ?? 0) >= 40,
    });

    setChatMessages((prev: ChatMessage[]) => {
      const next = [...prev, assistantMessage];
      conversationRef.current = next;
      return next;
    });

    console.log('📨 Backend response quick_replies:', response.quick_replies);

    if (response.quick_replies) {
      console.log('✅ Setting quick replies:', response.quick_replies);
      setNewQuickReplies(response.quick_replies);
    } else {
      console.log('❌ No quick replies in response');
    }

    updateLeadProfile(response.lead_signals);

    try {
      const loggerResponse = await loggerService.logConversation({
        messages: conversationRef.current,
        sessionId,
        documentContext: documentContextRef.current ?? undefined,
      });

      if (loggerResponse?.extracted_data) {
        updateLeadProfile(loggerResponse.extracted_data);
      }
    } catch (error) {
      console.info('[logger] konnte nicht ausgeführt werden', error);
    }

    offerMeetingIfQualified(conversationRef.current, leadProfileRef.current ?? {});
    ensureUploadSuggestion(conversationRef.current);
  };

  const handleUserMessage = useCallback(
    async (text: string, quickReplyUsed?: string) => {
      if (!text.trim() || !sessionId || isTyping || !consentGranted) return;

      const userMessage: ChatMessage = {
        id: `user-${crypto.randomUUID?.() ?? Date.now().toString(36)}`,
        role: AuthorRole.USER,
        text: text.trim(),
        timestamp: new Date().toISOString(),
      };

      const nextConversation = [...conversationRef.current, userMessage];
      setChatMessages(nextConversation);
      conversationRef.current = nextConversation;
      setMessageDraft('');
      setQuickReplies([]);
      setIsTyping(true);

      try {
        const response = await chatService.sendMessage({
          message: userMessage.text,
          history: nextConversation,
          sessionId,
          documentContext: documentContextRef.current ?? undefined,
          quickReplyUsed,
        });

        await handleAssistantResponse(response);
      } catch (error) {
        console.error('[chat] Anfrage fehlgeschlagen', error);
        setChatMessages((prev) => [
          ...prev,
          createBotMessage('Entschuldigung, es gab ein technisches Problem. Bitte versuchen Sie es erneut.'),
        ]);
      } finally {
        setIsTyping(false);
      }
    },
    [consentGranted, handleAssistantResponse, isTyping, sessionId, setChatMessages],
  );

  const handleQuickReply = async (option: string) => {
    setQuickReplies([]);

    if (option.includes('Termin vereinbaren')) {
      setIsMeetingModalOpen(true);
      setChatMessages((prev: ChatMessage[]) => {
        const userSelection: ChatMessage = {
          id: `user-${crypto.randomUUID?.() ?? Date.now().toString(36)}`,
          role: AuthorRole.USER,
          text: option,
          timestamp: new Date().toISOString(),
        };
        const next = [...prev, userSelection];
        conversationRef.current = next;
        return next;
      });
      return;
    }

    // CV hochladen (für Job-Matching) → Upload-Dialog öffnen
    if (option.includes('CV hochladen')) {
      setUploadDocumentType('cv_matching');
      setIsUploadOpen(true);
      setChatMessages((prev: ChatMessage[]) => {
        const userSelection: ChatMessage = {
          id: `user-${crypto.randomUUID?.() ?? Date.now().toString(36)}`,
          role: AuthorRole.USER,
          text: option,
          timestamp: new Date().toISOString(),
        };
        const next = [...prev, userSelection];
        conversationRef.current = next;
        return next;
      });
      return;
    }

    await handleUserMessage(option, option);
  };

  const handleDocumentUpload = async (file: File) => {
    if (!sessionId) return;

    setIsUploading(true);
    setUploadStatus({ variant: 'loading', message: 'Dokument wird analysiert …' });

    try {
      const uploadResult = await uploadService.uploadDocument({
        file,
        sessionId,
        documentType: uploadDocumentType,
      });

      if (!uploadResult.success || !uploadResult.data) {
        throw new Error(uploadResult.message ?? 'Upload fehlgeschlagen');
      }

      const data = uploadResult.data;
      const context: DocumentContext = {
        type: data.document_type ?? 'unknown',
        filename: data.filename,
        text: data.extracted_text,
        wordCount: data.word_count,
        serverPath: data.server_path,
        fileSize: data.file_size,
        contactData: normalizeContactData(data.contact_data as Partial<LeadProfile> | undefined),
      };

      setDocumentContext(context);
      setUploadStatus({ variant: 'success', message: `${context.filename} erfolgreich analysiert.` });
      setIsUploadOpen(false);
      const systemMessage = createSystemMessage(
        `📄 Dokument "${context.filename}" wurde hochgeladen und in die Beratung übernommen.`,
      );
      setChatMessages((prev: ChatMessage[]) => {
        const next = [...prev, systemMessage];
        conversationRef.current = next;
        return next;
      });

      if (context.contactData) {
        updateLeadProfile(context.contactData);
      }

      // Email wird NICHT sofort gesendet - nur beim Verlassen der Seite
      // Dokument-Upload wird als Lead-Signal gespeichert und beim Verlassen berücksichtigt
      console.log('📄 Dokument hochgeladen - Email wird beim Verlassen der Seite gesendet');

      setIsTyping(true);
      const summaryPrompt = buildDocumentSummaryPrompt(context);
      const summaryResponse = await chatService.sendMessage({
        message: summaryPrompt,
        history: conversationRef.current,
        sessionId,
        documentContext: context,
        isDocumentSummary: true,
      });

      await handleAssistantResponse(summaryResponse, { source: 'document-summary' });
    } catch (error) {
      console.error('[upload] Fehler', error);
      setUploadStatus({ variant: 'error', message: error instanceof Error ? error.message : 'Upload fehlgeschlagen.' });
      setIsUploadOpen(true);
    } finally {
      setIsTyping(false);
      setIsUploading(false);
    }
  };

  const handleEmailSummary = async ({ email, includeFullChat }: { email: string; includeFullChat: boolean }) => {
    setIsEmailSubmitting(true);
    try {
      await emailService.sendSummary({
        recipient_email: email,
        conversation: buildHistory(conversationRef.current),
        extracted_data: leadProfileRef.current ?? {},
        include_full_chat: includeFullChat,
        session_id: sessionId,
        document_context: documentContextRef.current
          ? {
              type: documentContextRef.current.type,
              filename: documentContextRef.current.filename,
              word_count: documentContextRef.current.wordCount,
              server_path: documentContextRef.current.serverPath,
              contact_data: documentContextRef.current.contactData,
              text: trimText(documentContextRef.current.text, 2000),
            }
          : undefined,
      });

      setIsEmailModalOpen(false);
    } catch (error) {
      console.error('[email] Versand fehlgeschlagen', error);
      setIsEmailModalOpen(false);
    } finally {
      setIsEmailSubmitting(false);
    }
  };

  const handleNewChat = () => {
    setChatMessages([]);
    clearChatMessages();
    setLeadProfile({});
    clearLeadProfile();
    setDocumentContext(null);
    documentContextRef.current = null;
    conversationRef.current = [];
    autoEmailSentRef.current = false;
    meetingOfferedRef.current = false;
    uploadSuggestedRef.current = false;
    const freshSession = generateSessionId();
    setSessionId(freshSession);
    setIsSettingsOpen(false);
  };

  const status = isOffline ? 'offline' : isTyping ? 'typing' : 'idle';

  const defaultEmail = useMemo(() => leadProfile.email ?? '', [leadProfile.email]);

  const handleSpeak = (messageId: string, text: string) => {
    if (!supportsSpeech) return;
    lastSpokenMessageRef.current = messageId;
    speak(text);
    setSpeakingMessageId(messageId);
  };

  const handleStopSpeaking = () => {
    stop();
    setSpeakingMessageId(null);
  };

  return (
    <div className="flex min-h-screen flex-col bg-slate-100">
      <ConsentModal
        isOpen={!consentGranted}
        onAccept={() => setConsentGranted(true)}
        onDecline={() => {
          window.open('tel:+4921197532474');
        }}
      />

      <SettingsDrawer
        open={isSettingsOpen}
        onClose={() => setIsSettingsOpen(false)}
        onNewChat={handleNewChat}
        onContact={() => window.open('tel:+492119753247', '_self')}
        onEmailSummary={() => {
          setIsEmailModalOpen(true);
          setIsSettingsOpen(false);
        }}
        onToggleTts={() => setTtsEnabled((prev: boolean) => !prev)}
        onToggleAutoPlay={() => setTtsAutoPlay((prev: boolean) => !prev)}
        ttsEnabled={ttsEnabled}
        ttsAutoPlay={ttsAutoPlay}
        supportsSpeech={supportsSpeech}
        onOpenMeeting={() => {
          setIsMeetingModalOpen(true);
          setIsSettingsOpen(false);
        }}
      />

      <DocumentUploadSheet
        open={isUploadOpen}
        onClose={() => setIsUploadOpen(false)}
        onUpload={handleDocumentUpload}
        isUploading={isUploading}
        documentType={uploadDocumentType}
        status={uploadStatus}
      />

      <EmailSummaryModal
        open={isEmailModalOpen}
        defaultEmail={defaultEmail}
        onClose={() => setIsEmailModalOpen(false)}
        onSubmit={handleEmailSummary}
        isSubmitting={isEmailSubmitting}
      />

      <MeetingModal
        open={isMeetingModalOpen}
        onClose={() => setIsMeetingModalOpen(false)}
        meetingUrl={APP_CONFIG.notifications.meetingUrl}
      />

      <header className="border-b border-slate-200 bg-white/80 backdrop-blur">
        <div className="mx-auto flex w-full max-w-5xl items-center justify-between px-4 py-4">
          <div>
            <p className="text-xs uppercase tracking-[0.3em] text-slate-400">{APP_CONFIG.branding.company}</p>
            <h1 className="text-xl font-semibold text-slate-900">{APP_CONFIG.branding.name}</h1>
          </div>

          <div className="flex items-center gap-3">
            <span
              className={`flex h-2.5 w-2.5 items-center justify-center rounded-full ${
                isOffline ? 'bg-red-500' : 'bg-emerald-500'
              }`}
            />
            <button
              type="button"
              onClick={() => setIsSettingsOpen(true)}
              className="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-slate-600 transition hover:border-noba-orange hover:text-noba-orange"
            >
              Menü
            </button>
          </div>
        </div>
      </header>

      <StatusBanner status={status as 'offline' | 'typing' | 'idle'} />

      <main className="flex-1">
        <ChatMessageList
          messages={chatMessages}
          onSpeak={handleSpeak}
          onStopSpeaking={handleStopSpeaking}
          ttsEnabled={ttsEnabled}
          supportsSpeech={supportsSpeech}
          speakingMessageId={speakingMessageId}
          isTyping={isTyping}
        />

        <QuickReplies options={quickReplies} onSelect={handleQuickReply} />
      </main>

      <MessageComposer
        value={messageDraft}
        onChange={setMessageDraft}
        onSubmit={() => handleUserMessage(messageDraft)}
        disabled={!consentGranted || isTyping || isOffline}
        maxLength={APP_CONFIG.limits.maxMessageLength}
        onOpenUpload={() => {
          setUploadDocumentType(determineDocumentTypeFromConversation(chatMessages));
          setIsUploadOpen(true);
        }}
      />
    </div>
  );
};

export default App;
