export const generateSessionId = () => {
  if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
    return `pwa-${crypto.randomUUID()}`;
  }

  const randomPart = Math.random().toString(36).slice(2, 10);
  return `pwa-${Date.now()}-${randomPart}`;
};
