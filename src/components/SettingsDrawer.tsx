interface SettingsDrawerProps {
  open: boolean;
  onClose: () => void;
  onNewChat: () => void;
  onContact: () => void;
  onEmailSummary: () => void;
  onToggleTts: () => void;
  onToggleAutoPlay: () => void;
  ttsEnabled: boolean;
  ttsAutoPlay: boolean;
  supportsSpeech: boolean;
  onOpenMeeting: () => void;
}

const drawerBaseClass =
  'fixed inset-y-0 right-0 z-40 w-full max-w-sm transform bg-white shadow-2xl transition-transform duration-200 ease-in-out';

export const SettingsDrawer = ({
  open,
  onClose,
  onNewChat,
  onContact,
  onEmailSummary,
  onToggleTts,
  onToggleAutoPlay,
  ttsEnabled,
  ttsAutoPlay,
  supportsSpeech,
  onOpenMeeting,
}: SettingsDrawerProps) => (
  <div className={drawerBaseClass + (open ? ' translate-x-0' : ' translate-x-full')}>
    <div className="flex items-center justify-between border-b border-slate-100 px-6 py-5">
      <h2 className="text-lg font-semibold text-slate-900">Aktionen</h2>
      <button
        type="button"
        onClick={onClose}
        className="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-500 hover:border-slate-300 hover:text-slate-700"
      >
        Schließen
      </button>
    </div>

    <div className="flex flex-col gap-3 px-6 py-6">
      <button
        type="button"
        onClick={onEmailSummary}
        className="flex items-center justify-between rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:border-noba-orange hover:text-noba-orange"
      >
        <span>E-Mail-Zusammenfassung senden</span>
        <span className="text-base">📧</span>
      </button>

      <button
        type="button"
        onClick={onOpenMeeting}
        className="flex items-center justify-between rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:border-noba-orange hover:text-noba-orange"
      >
        <span>Termin vereinbaren</span>
        <span className="text-base">📅</span>
      </button>

      <button
        type="button"
        onClick={onNewChat}
        className="flex items-center justify-between rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:border-red-200 hover:text-red-500"
      >
        <span>Neuen Chat starten</span>
        <span className="text-base">🔄</span>
      </button>

      <button
        type="button"
        onClick={onContact}
        className="flex items-center justify-between rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:border-noba-orange hover:text-noba-orange"
      >
        <span>Direktkontakt aufnehmen</span>
        <span className="text-base">📞</span>
      </button>

      <div className="mt-4 space-y-3">
        <p className="text-xs font-semibold uppercase tracking-wide text-slate-400">Sprachausgabe</p>
        <button
          type="button"
          onClick={onToggleTts}
          disabled={!supportsSpeech}
          className="flex w-full items-center justify-between rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium transition hover:border-noba-orange hover:text-noba-orange disabled:cursor-not-allowed disabled:opacity-60"
        >
          <span>Sprachausgabe {ttsEnabled ? '(aktiv)' : '(deaktiviert)'}</span>
          <span className="text-base">🔊</span>
        </button>
        <button
          type="button"
          onClick={onToggleAutoPlay}
          disabled={!supportsSpeech || !ttsEnabled}
          className="flex w-full items-center justify-between rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium transition hover:border-noba-orange hover:text-noba-orange disabled:cursor-not-allowed disabled:opacity-60"
        >
          <span>Auto-Vorlesen {ttsAutoPlay ? '(aktiv)' : '(deaktiviert)'}</span>
          <span className="text-base">🔁</span>
        </button>
      </div>
    </div>
  </div>
);
