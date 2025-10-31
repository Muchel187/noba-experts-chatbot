import { FormEvent, useState, type ChangeEvent } from 'react';

interface EmailSummaryModalProps {
  open: boolean;
  defaultEmail?: string;
  onClose: () => void;
  onSubmit: (options: { email: string; includeFullChat: boolean }) => Promise<void> | void;
  isSubmitting: boolean;
}

export const EmailSummaryModal = ({ open, defaultEmail = '', onClose, onSubmit, isSubmitting }: EmailSummaryModalProps) => {
  const [email, setEmail] = useState(defaultEmail);
  const [includeFullChat, setIncludeFullChat] = useState(true);
  const [error, setError] = useState<string | null>(null);

  if (!open) return null;

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setError(null);

    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      setError('Bitte geben Sie eine gültige E-Mail-Adresse ein.');
      return;
    }

    await onSubmit({ email, includeFullChat });
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/80 px-4">
      <div className="w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl">
        <h2 className="text-lg font-semibold text-slate-900">E-Mail-Zusammenfassung versenden</h2>
        <p className="mt-2 text-sm text-slate-500">
          Wir senden eine strukturierte Zusammenfassung des Chats per E-Mail. Optional kann der vollständige Verlauf
          angehängt werden.
        </p>

        <form onSubmit={handleSubmit} className="mt-6 space-y-4">
          <div>
            <label className="text-xs font-semibold uppercase tracking-wide text-slate-500">Empfänger-Adresse</label>
            <input
              type="email"
              value={email}
              onChange={(event: ChangeEvent<HTMLInputElement>) => setEmail(event.target.value)}
              placeholder="name@unternehmen.de"
              className="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-noba-orange/60"
            />
          </div>

          <label className="flex items-center gap-3 rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-600">
            <input
              type="checkbox"
              checked={includeFullChat}
              onChange={(event: ChangeEvent<HTMLInputElement>) => setIncludeFullChat(event.target.checked)}
              className="h-4 w-4 rounded border-slate-300 text-noba-orange focus:ring-noba-orange"
            />
            Vollständigen Chatverlauf anhängen
          </label>

          {error && <p className="text-sm text-red-500">{error}</p>}

          <div className="flex justify-end gap-3 pt-2">
            <button
              type="button"
              onClick={onClose}
              className="rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:border-slate-300 hover:text-slate-800"
            >
              Abbrechen
            </button>
            <button
              type="submit"
              disabled={isSubmitting}
              className="inline-flex items-center gap-2 rounded-full bg-gradient-to-br from-noba-orange to-orange-500 px-5 py-2 text-sm font-semibold text-white shadow-lg transition hover:shadow-xl disabled:cursor-not-allowed disabled:opacity-60"
            >
              {isSubmitting ? 'Sendet …' : 'Versenden'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};
