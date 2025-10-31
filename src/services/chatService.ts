import { APP_CONFIG } from '@/constants/config';
import { SYSTEM_PROMPT } from '@/constants/systemPrompt';
import { apiClient } from '@/services/apiClient';
import {
  AuthorRole,
  ChatMessage,
  ChatRequestPayload,
  ChatResponsePayload,
  DocumentContext,
} from '@/types';

export const mapMessagesToHistory = (messages: ChatMessage[]): ChatRequestPayload['history'] =>
  messages
    .filter((message) => message.role !== AuthorRole.SYSTEM)
    .slice(-10)
    .map((message) => ({
      role: message.role as Exclude<AuthorRole, AuthorRole.SYSTEM>,
      text: message.text,
      timestamp: message.timestamp,
    }));

const formatDocumentContext = (context?: DocumentContext): ChatRequestPayload['document_context'] => {
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

interface SendMessageOptions {
  message: string;
  history: ChatMessage[];
  sessionId: string;
  documentContext?: DocumentContext;
  isDocumentSummary?: boolean;
  quickReplyUsed?: string;
}

export const chatService = {
  async sendMessage(options: SendMessageOptions) {
    const payload: ChatRequestPayload = {
      session_id: options.sessionId,
      message: options.message,
  history: mapMessagesToHistory(options.history),
      document_context: formatDocumentContext(options.documentContext),
      is_document_summary: options.isDocumentSummary,
      quick_reply_used: options.quickReplyUsed,
      system_prompt: SYSTEM_PROMPT,
    };

    return apiClient.post<ChatResponsePayload>(APP_CONFIG.endpoints.chat, payload);
  },
};
