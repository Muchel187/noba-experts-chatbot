import { APP_CONFIG } from '@/constants/config';
import { apiClient } from '@/services/apiClient';
import { EmailSummaryPayload, EmailSummaryServiceResponse } from '@/types';

export const emailService = {
  async sendSummary(payload: EmailSummaryPayload, options?: { keepalive?: boolean }) {
    if (options?.keepalive && navigator.sendBeacon) {
      const blob = new Blob([JSON.stringify(payload)], { type: 'application/json' });
      const sent = navigator.sendBeacon(APP_CONFIG.endpoints.emailSummary, blob);
      if (sent) {
        return { success: true } satisfies EmailSummaryServiceResponse;
      }
    }

    return apiClient.post<EmailSummaryServiceResponse>(APP_CONFIG.endpoints.emailSummary, payload, {
      keepalive: options?.keepalive,
    });
  },
};
