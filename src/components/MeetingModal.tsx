interface MeetingModalProps {
  open: boolean;
  onClose: () => void;
  meetingUrl: string;
}

export const MeetingModal = ({ open, onClose, meetingUrl }: MeetingModalProps) => {
  if (!open) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/80 px-4">
      <div className="w-full max-w-2xl overflow-hidden rounded-3xl bg-white shadow-2xl">
        <div className="flex items-center justify-between border-b border-slate-100 px-6 py-4">
          <h2 className="text-lg font-semibold text-slate-900">Termin vereinbaren</h2>
          <button
            type="button"
            onClick={onClose}
            className="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-500 hover:border-slate-300 hover:text-slate-700"
          >
            Schließen
          </button>
        </div>
        <div className="aspect-[4/3] w-full bg-slate-50">
          <iframe
            title="HubSpot Meeting"
            src={meetingUrl}
            className="h-full w-full"
            allow="geolocation *; microphone *; camera *"
          />
        </div>
        <div className="flex items-center justify-between border-t border-slate-100 px-6 py-4 text-sm text-slate-600">
          <span>Probleme mit dem Embed?</span>
          <a
            href={meetingUrl}
            target="_blank"
            rel="noreferrer"
            className="font-semibold text-noba-orange underline-offset-4 hover:underline"
          >
            Direktlink öffnen
          </a>
        </div>
      </div>
    </div>
  );
};
