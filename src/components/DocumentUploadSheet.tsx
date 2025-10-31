import { useState, type ChangeEvent } from 'react';
import type { DocumentContext } from '@/types';

const MAX_SIZE_BYTES = 10 * 1024 * 1024;
const ALLOWED_TYPES = [
  'application/pdf',
  'application/msword',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];

interface DocumentUploadSheetProps {
  open: boolean;
  onClose: () => void;
  onUpload: (file: File) => Promise<void> | void;
  isUploading: boolean;
  documentType: DocumentContext['type'];
  status?: { variant: 'idle' | 'loading' | 'success' | 'error'; message?: string };
}

const getLabel = (type: DocumentContext['type']) => {
  if (type === 'cv') return 'CV hochladen (optional)';
  if (type === 'job_description') return 'Stellenbeschreibung hochladen (optional)';
  return 'Dokument hochladen (optional)';
};

export const DocumentUploadSheet = ({
  open,
  onClose,
  onUpload,
  isUploading,
  documentType,
  status,
}: DocumentUploadSheetProps) => {
  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const [validationError, setValidationError] = useState<string | null>(null);

  if (!open) return null;

  const handleFileChange = (fileList: FileList | null) => {
    const file = fileList?.[0];
    setValidationError(null);

    if (!file) {
      setSelectedFile(null);
      return;
    }

    if (!ALLOWED_TYPES.includes(file.type)) {
      setValidationError('Ungültiger Dateityp (erlaubt: PDF, DOC, DOCX).');
      setSelectedFile(null);
      return;
    }

    if (file.size > MAX_SIZE_BYTES) {
      setValidationError('Datei zu groß (max. 10 MB).');
      setSelectedFile(null);
      return;
    }

    setSelectedFile(file);
  };

  const handleUploadClick = async () => {
    if (!selectedFile || validationError) return;
    await onUpload(selectedFile);
  };

  return (
    <div className="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/60 px-4 pb-8">
      <div className="w-full max-w-2xl rounded-3xl bg-white p-6 shadow-2xl">
        <div className="flex items-center justify-between">
          <h2 className="text-lg font-semibold text-slate-900">{getLabel(documentType)}</h2>
          <button
            type="button"
            onClick={onClose}
            className="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-500 hover:border-slate-300 hover:text-slate-700"
          >
            Schließen
          </button>
        </div>

        <div className="mt-6 rounded-2xl border border-dashed border-slate-200 bg-slate-50/70 p-6 text-center">
          <p className="text-sm text-slate-600">Datei auswählen oder per Drag & Drop hier ablegen.</p>
          <p className="mt-2 text-xs text-slate-400">Erlaubte Formate: PDF, DOC, DOCX · max. 10 MB</p>

          <label className="mt-4 inline-flex cursor-pointer items-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-medium text-noba-orange shadow-sm ring-1 ring-noba-orange/30 transition hover:scale-[1.02]">
            <input
              type="file"
              accept={ALLOWED_TYPES.join(',')}
              className="sr-only"
              onChange={(event: ChangeEvent<HTMLInputElement>) => handleFileChange(event.target.files)}
            />
            📁 Datei wählen
          </label>

          {selectedFile && (
            <div className="mt-4 text-sm text-slate-600">
              <p className="font-medium">{selectedFile.name}</p>
              <p className="text-xs text-slate-400">{(selectedFile.size / 1024 / 1024).toFixed(2)} MB</p>
            </div>
          )}

          {validationError && <p className="mt-3 text-sm text-red-500">{validationError}</p>}
          {status && status.variant !== 'idle' && !validationError && (
            <p
              className={`mt-3 text-sm ${
                status.variant === 'error'
                  ? 'text-red-500'
                  : status.variant === 'success'
                    ? 'text-emerald-600'
                    : 'text-slate-500'
              }`}
            >
              {status.message}
            </p>
          )}
        </div>

        <div className="mt-6 flex justify-end gap-3">
          <button
            type="button"
            onClick={onClose}
            className="rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:border-slate-300 hover:text-slate-800"
          >
            Später hochladen
          </button>
          <button
            type="button"
            disabled={!selectedFile || !!validationError || isUploading}
            onClick={handleUploadClick}
            className="inline-flex items-center gap-2 rounded-full bg-gradient-to-br from-noba-orange to-orange-500 px-5 py-2 text-sm font-semibold text-white shadow-lg transition hover:shadow-xl disabled:cursor-not-allowed disabled:opacity-60"
          >
            {isUploading ? 'Analysiere …' : 'Hochladen'}
          </button>
        </div>
      </div>
    </div>
  );
};
