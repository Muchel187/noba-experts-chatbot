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
    <div className="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/40 px-4 backdrop-blur-sm animate-fade-in">
      <div className="relative mx-4 w-full max-w-lg animate-scale-in">
        {/* Outer Glow */}
        <div className="absolute -inset-1 rounded-3xl bg-gradient-to-r from-noba-orange-500 via-neon-purple to-noba-orange-500 opacity-20 blur-2xl"></div>
        
        {/* Modal Card */}
        <div className="relative rounded-3xl bg-white/95 p-8 shadow-2xl backdrop-blur-xl">
          {/* Header */}
          <div className="mb-6 flex items-start justify-between">
            <div>
              <h2 className="mb-1 bg-gradient-to-r from-slate-900 to-slate-700 bg-clip-text text-2xl font-bold text-transparent">
                {getLabel(documentType)}
              </h2>
              <p className="text-sm text-slate-500">PDF, DOCX oder TXT bis 10 MB</p>
            </div>
            <button
              type="button"
              onClick={onClose}
              className="rounded-lg p-2 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600"
            >
              <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
          
          {/* Drop Zone */}
          <div className="group relative overflow-hidden rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50/50 p-12 transition-all hover:border-noba-orange-500 hover:bg-noba-orange-50/50">
            <div className="absolute inset-0 bg-gradient-to-br from-noba-orange-500/5 to-neon-purple/5 opacity-0 transition-opacity group-hover:opacity-100"></div>
            
            <div className="relative text-center">
              <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-neon-purple to-noba-orange-500 shadow-lg">
                <svg className="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                </svg>
              </div>
              <p className="mb-1 text-sm font-medium text-slate-700">
                Klicken oder Datei hierher ziehen
              </p>
              <p className="text-xs text-slate-500">Maximal 10 MB</p>
              
              <label className="mt-4 inline-flex cursor-pointer items-center gap-2 rounded-xl bg-white px-5 py-2.5 text-sm font-medium text-slate-700 shadow-md transition-all hover:scale-105 hover:shadow-lg">
                <input
                  type="file"
                  accept={ALLOWED_TYPES.join(',')}
                  className="sr-only"
                  onChange={(event: ChangeEvent<HTMLInputElement>) => handleFileChange(event.target.files)}
                />
                📁 Datei auswählen
              </label>
            </div>
          </div>

          {selectedFile && (
            <div className="mt-4 rounded-xl bg-slate-50 px-4 py-3">
              <p className="font-medium text-slate-700">{selectedFile.name}</p>
              <p className="text-xs text-slate-500">{(selectedFile.size / 1024 / 1024).toFixed(2)} MB</p>
            </div>
          )}

          {validationError && (
            <p className="mt-3 rounded-lg bg-red-50 px-4 py-2 text-sm text-red-600">{validationError}</p>
          )}
          
          {status && status.variant !== 'idle' && !validationError && (
            <p
              className={`mt-3 rounded-lg px-4 py-2 text-sm ${
                status.variant === 'error'
                  ? 'bg-red-50 text-red-600'
                  : status.variant === 'success'
                    ? 'bg-emerald-50 text-emerald-600'
                    : 'bg-slate-50 text-slate-600'
              }`}
            >
              {status.message}
            </p>
          )}

          {/* Action Buttons */}
          <div className="mt-6 flex gap-3">
            <button
              type="button"
              onClick={onClose}
              className="flex-1 rounded-xl border border-slate-300 bg-white px-6 py-3 font-medium text-slate-700 transition-all hover:border-slate-400 hover:bg-slate-50"
            >
              Abbrechen
            </button>
            <button
              type="button"
              disabled={!selectedFile || !!validationError || isUploading}
              onClick={handleUploadClick}
              className="flex-1 rounded-xl bg-gradient-to-r from-noba-orange-500 to-noba-orange-600 px-6 py-3 font-medium text-white shadow-lg transition-all hover:shadow-glow-orange disabled:cursor-not-allowed disabled:opacity-50"
            >
              {isUploading ? 'Analysiere…' : 'Hochladen'}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};
