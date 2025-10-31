interface StatusBannerProps {
  status: 'offline' | 'typing' | 'idle';
  message?: string;
}

export const StatusBanner = ({ status, message }: StatusBannerProps) => {
  if (status === 'idle') return null;

  const defaultMessage =
    status === 'offline'
      ? 'Offline – wir senden Ihre Nachricht, sobald Sie wieder online sind.'
      : 'Der KI-Assistent schreibt gerade …';

  return (
    <div
      className={`bg-slate-900 text-white ${
        status === 'offline' ? 'bg-red-600' : 'bg-slate-900'
      }`}
    >
      <div className="mx-auto flex w-full max-w-3xl items-center justify-center gap-2 px-4 py-2 text-xs font-medium">
        <span>{status === 'offline' ? '⚠️' : '💬'}</span>
        <span>{message ?? defaultMessage}</span>
      </div>
    </div>
  );
};
