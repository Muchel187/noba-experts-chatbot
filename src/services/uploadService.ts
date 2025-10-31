import { APP_CONFIG } from '@/constants/config';
import { apiClient } from '@/services/apiClient';
import { DocumentContext, UploadResponse } from '@/types';

interface UploadOptions {
  file: File;
  sessionId: string;
  documentType: DocumentContext['type'];
}

export const uploadService = {
  async uploadDocument({ file, sessionId, documentType }: UploadOptions) {
    const formData = new FormData();
    formData.append('document', file);
    formData.append('session_id', sessionId);
    formData.append('document_type', documentType);

    return apiClient.upload<UploadResponse>(APP_CONFIG.endpoints.upload, formData);
  },
};
