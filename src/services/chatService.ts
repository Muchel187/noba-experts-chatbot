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
  file?: File; // NEU: Optional file upload
}

export const chatService = {
  async sendMessage(options: SendMessageOptions) {
    // Wenn ein File vorhanden ist, verwende FormData statt JSON
    if (options.file) {
      const formData = new FormData();
      formData.append('action', 'chat'); // Backend needs action parameter
      formData.append('file', options.file);
      formData.append('message', options.message);
      formData.append('session_id', options.sessionId);
      formData.append('history', JSON.stringify(mapMessagesToHistory(options.history)));
      
      if (options.documentContext) {
        formData.append('document_context', JSON.stringify(formatDocumentContext(options.documentContext)));
      }
      if (options.isDocumentSummary !== undefined) {
        formData.append('is_document_summary', String(options.isDocumentSummary));
      }
      if (options.quickReplyUsed) {
        formData.append('quick_reply_used', options.quickReplyUsed);
      }
      if (SYSTEM_PROMPT) {
        formData.append('system_prompt', SYSTEM_PROMPT);
      }
      
      return apiClient.upload<ChatResponsePayload>(APP_CONFIG.endpoints.chat, formData);
    }
    
    // Standard-JSON-Request ohne File
    const payload: ChatRequestPayload = {
      action: 'chat', // Backend needs action parameter
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
