export enum AuthorRole {
  USER = 'user',
  BOT = 'bot',
  SYSTEM = 'system',
}

export interface ChatMessage {
  id: string;
  role: AuthorRole;
  text: string;
  timestamp: string;
  metadata?: MessageMetadata;
}

export interface ChatHistoryItem {
  role: Exclude<AuthorRole, AuthorRole.SYSTEM>;
  text: string;
  timestamp: string;
}

export interface MessageMetadata {
  source?: 'chat' | 'document-summary' | 'system';
  leadQualified?: boolean;
  quickReplies?: string[];
}

export interface DocumentContext {
  type: 'cv' | 'cv_matching' | 'job_description' | 'unknown';
  filename: string;
  text: string;
  wordCount: number;
  serverPath?: string;
  contactData?: Partial<LeadProfile>;
  fileSize?: number;
}

export interface LeadProfile {
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

export interface QuickReplyPayload {
  label: string;
  value: string;
}

export interface UploadResponse {
  success: boolean;
  message?: string;
  data?: {
    extracted_text: string;
    filename: string;
    document_type: DocumentContext['type'];
    session_id: string;
    file_size: number;
    word_count: number;
    server_filename?: string;
    server_path?: string;
    contact_data?: Partial<LeadProfile>;
  };
}

export interface EmailSummaryRequest {
  recipientEmail: string;
  includeFullChat: boolean;
}

export interface EmailSummaryResponse {
  success: boolean;
  message?: string;
  error?: string;
}

export interface LeadSignals {
  detected_type?: LeadProfile['leadType'];
  missing_fields?: string[];
  updates?: Partial<LeadProfile>;
  lead_score?: number;
}

export interface ChatRequestPayload {
  session_id: string;
  message: string;
  history: ChatHistoryItem[];
  document_context?: {
    type: DocumentContext['type'];
    text: string;
    filename: string;
    word_count?: number;
    server_path?: string;
    contact_data?: Partial<LeadProfile>;
  };
  is_document_summary?: boolean;
  quick_reply_used?: string;
  system_prompt?: string;
}

export interface ChatResponsePayload {
  message: string;
  quick_replies?: string[];
  lead_signals?: LeadSignals;
  error?: string;
  status?: string;
}

export interface LoggerRequestPayload {
  session_id: string;
  messages: ChatHistoryItem[];
  document_context?: ChatRequestPayload['document_context'];
  form_submitted?: boolean;
}

export interface LoggerResponsePayload {
  success: boolean;
  extracted_data?: Partial<LeadProfile> & {
    lead_score?: number;
    lead_type?: LeadProfile['leadType'];
    missing_fields?: string[];
    tech_stack?: string[];
  };
}

export interface EmailSummaryPayload {
  recipient_email: string;
  conversation: ChatHistoryItem[];
  extracted_data?: Partial<LeadProfile>;
  include_full_chat?: boolean;
  session_id: string;
  document_context?: ChatRequestPayload['document_context'];
  auto_sent?: boolean;
}

export interface EmailSummaryServiceResponse {
  success: boolean;
  message?: string;
  error?: string;
}
