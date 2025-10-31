import { APP_CONFIG } from '@/constants/config';
import { apiClient } from '@/services/apiClient';
import {
  AuthorRole,
  ChatMessage,
  ChatRequestPayload,
  DocumentContext,
  LoggerRequestPayload,
  LoggerResponsePayload,
} from '@/types';

const mapMessagesToLoggerHistory = (messages: ChatMessage[]): ChatRequestPayload['history'] =>
  messages
    .filter((message) => message.role !== AuthorRole.SYSTEM)
    .map((message) => ({
      role: message.role as Exclude<AuthorRole, AuthorRole.SYSTEM>,
      text: message.text,
      timestamp: message.timestamp,
    }));

const formatDocumentContext = (context?: DocumentContext): LoggerRequestPayload['document_context'] => {
  if (!context) return undefined;
  return {
    type: context.type,
    text: context.text,
    filename: context.filename,
    word_count: context.wordCount,
    server_path: context.serverPath,
    contact_data: context.contactData,
  };
};

export const loggerService = {
  async logConversation(options: {
    messages: ChatMessage[];
    sessionId: string;
    documentContext?: DocumentContext;
    formSubmitted?: boolean;
  }) {
    const payload: LoggerRequestPayload = {
      session_id: options.sessionId,
      messages: mapMessagesToLoggerHistory(options.messages),
      document_context: formatDocumentContext(options.documentContext),
      form_submitted: options.formSubmitted,
    };

    return apiClient.post<LoggerResponsePayload>(APP_CONFIG.endpoints.logger, payload);
  },
};
