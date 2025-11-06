import { FormEvent, useRef, useState, useEffect, type ChangeEvent, type KeyboardEvent } from 'react';
import { useAutoResizeTextarea } from '@/hooks/useAutoResizeTextarea';
import { Send, Paperclip, Loader2, Mic, Square } from 'lucide-react';
import { motion } from 'framer-motion';
import clsx from 'clsx';
import { useSpeechRecognition } from '@/hooks/useSpeechRecognition';

interface MessageComposerProps {
  value: string;
  onChange: (value: string) => void;
  onSubmit: () => void;
  disabled?: boolean;
  maxLength: number;
  onOpenUpload?: () => void;
}

export const MessageComposer = ({
  value,
  onChange,
  onSubmit,
  disabled,
  maxLength,
  onOpenUpload,
}: MessageComposerProps) => {
  const textareaRef = useRef<HTMLTextAreaElement | null>(null);
  useAutoResizeTextarea(textareaRef, value, { maxHeight: 200 });

  const [voiceTranscript, setVoiceTranscript] = useState('');

  // Speech recognition hook
  const {
    isListening,
    transcript,
    startListening,
    stopListening,
    supportsRecognition,
    error: recognitionError,
  } = useSpeechRecognition({
    language: 'de-DE',
    continuous: true,
    interimResults: true,
    onResult: (text) => {
      setVoiceTranscript(text);
    },
  });

  // Update textarea with voice transcript
  useEffect(() => {
    if (voiceTranscript) {
      onChange(voiceTranscript);
    }
  }, [voiceTranscript, onChange]);

  const handleSubmit = (event: FormEvent) => {
    event.preventDefault();
    if (!value.trim() || disabled) return;
    onSubmit();
  };

  const handleMicClick = () => {
    if (isListening) {
      stopListening();
    } else {
      setVoiceTranscript('');
      startListening();
    }
  };

  const charPercentage = (value.length / maxLength) * 100;
  const isNearLimit = charPercentage > 80;

  return (
    <form
      onSubmit={handleSubmit}
      className="fixed bottom-0 left-0 right-0 z-50 px-4 pb-4"
    >
      <div className="mx-auto max-w-4xl">
        <div className="glass-strong rounded-2xl border border-neon-purple/20 p-3 shadow-glass-lg backdrop-blur-2xl transition-all hover:border-neon-purple/40 hover:shadow-neon-purple sm:p-4">
          <div className="flex items-end gap-3">
            {/* Upload Button */}
            <motion.button
              type="button"
              onClick={onOpenUpload}
              disabled={disabled || !onOpenUpload}
              whileHover={{ scale: disabled || !onOpenUpload ? 1 : 1.05 }}
              whileTap={{ scale: disabled || !onOpenUpload ? 1 : 0.95 }}
              className={clsx(
                'group flex h-12 w-12 shrink-0 items-center justify-center rounded-xl border shadow-md backdrop-blur-xl transition-all',
                disabled || !onOpenUpload
                  ? 'cursor-not-allowed border-gray-700 bg-dark-tertiary text-gray-600'
                  : 'border-neon-purple/30 bg-dark-card text-gray-300 hover:border-neon-purple hover:scale-105 hover:text-neon-purple hover:shadow-neon-purple'
              )}
            >
              <Paperclip className="h-5 w-5 transition-colors" />
            </motion.button>

            {/* Input Area */}
            <div className="relative flex-1">
              {/* Recording Indicator */}
              {isListening && (
                <motion.div
                  initial={{ opacity: 0, y: -10 }}
                  animate={{ opacity: 1, y: 0 }}
                  exit={{ opacity: 0, y: -10 }}
                  className="absolute -top-10 left-0 flex items-center gap-2 rounded-full border border-red-500/50 bg-red-500/20 px-4 py-2 text-white shadow-lg backdrop-blur-xl"
                >
                  <span className="relative flex h-3 w-3">
                    <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-white opacity-75"></span>
                    <span className="relative inline-flex h-3 w-3 rounded-full bg-white"></span>
                  </span>
                  <span className="text-sm font-medium">Aufnahme läuft...</span>
                </motion.div>
              )}

              {/* Neon Gradient Border on Focus */}
              <div className="absolute -inset-[1px] rounded-xl bg-gradient-to-r from-neon-purple via-neon-purple to-neon-orange opacity-0 transition-opacity duration-300 focus-within:opacity-60"></div>
              
              <textarea
                ref={textareaRef}
                value={value}
                onChange={(event: ChangeEvent<HTMLTextAreaElement>) => onChange(event.target.value)}
                onKeyDown={(event: KeyboardEvent<HTMLTextAreaElement>) => {
                  if (event.key === 'Enter' && !event.shiftKey) {
                    event.preventDefault();
                    if (!disabled && value.trim()) {
                      onSubmit();
                    }
                  }
                }}
                maxLength={maxLength}
                placeholder="Schreiben Sie eine Nachricht..."
                disabled={disabled}
                className={clsx(
                  'relative w-full resize-none rounded-xl border-0 bg-dark-card px-4 py-3 pr-16 text-[15px] text-gray-100 shadow-inner backdrop-blur-xl transition-all placeholder:text-gray-500',
                  'focus:bg-dark-card-strong focus:outline-none focus:ring-0',
                  disabled && 'cursor-not-allowed opacity-60',
                )}
                rows={1}
              />

              {/* Character Count */}
              <div className="absolute bottom-3 right-3 flex items-center gap-2">
                <div className="relative h-7 w-7">
                  <svg className="h-7 w-7 -rotate-90 transform">
                    <circle
                      cx="14"
                      cy="14"
                      r="12"
                      stroke="currentColor"
                      strokeWidth="2"
                      fill="none"
                      className="text-gray-700"
                    />
                    <circle
                      cx="14"
                      cy="14"
                      r="12"
                      stroke="currentColor"
                      strokeWidth="2"
                      fill="none"
                      strokeDasharray={`${2 * Math.PI * 12}`}
                      strokeDashoffset={`${2 * Math.PI * 12 * (1 - charPercentage / 100)}`}
                      className={clsx(
                        'transition-all duration-300',
                        isNearLimit ? 'text-neon-orange' : 'text-neon-purple',
                      )}
                    />
                  </svg>
                  <div className="absolute inset-0 flex items-center justify-center">
                    <span className={clsx(
                      'text-[9px] font-bold',
                      isNearLimit ? 'text-neon-orange' : 'text-gray-400',
                    )}>
                      {value.length}
                    </span>
                  </div>
                </div>
              </div>
            </div>

            {/* Send/Microphone Button */}
            {value.trim() ? (
              <motion.button
                type="submit"
                disabled={disabled}
                whileHover={{ scale: disabled ? 1 : 1.05, rotate: disabled ? 0 : 45 }}
                whileTap={{ scale: disabled ? 1 : 0.95 }}
                className={clsx(
                  'group relative h-12 w-12 shrink-0 overflow-hidden rounded-xl shadow-lg transition-all',
                  disabled
                    ? 'cursor-not-allowed bg-gray-700 text-gray-500'
                    : 'border border-neon-orange/50 bg-gradient-to-r from-neon-orange to-neon-orange-bright text-white shadow-neon-orange hover:border-neon-orange'
                )}
              >
                {disabled ? (
                  <Loader2 className="relative z-10 mx-auto h-5 w-5 animate-spin" />
                ) : (
                  <>
                    <Send className="relative z-10 mx-auto h-5 w-5 transition-transform" />
                    <div className="absolute inset-0 bg-gradient-to-r from-neon-purple to-neon-purple opacity-0 transition-opacity duration-300 group-hover:opacity-100"></div>
                  </>
                )}
              </motion.button>
            ) : (
              <motion.button
                type="button"
                onClick={handleMicClick}
                disabled={disabled || !supportsRecognition}
                whileHover={{ scale: disabled || !supportsRecognition ? 1 : 1.05 }}
                whileTap={{ scale: disabled || !supportsRecognition ? 1 : 0.95 }}
                className={clsx(
                  'flex h-12 w-12 shrink-0 items-center justify-center rounded-xl border shadow-lg transition-all',
                  disabled || !supportsRecognition
                    ? 'cursor-not-allowed border-gray-700 bg-gray-700 text-gray-500'
                    : isListening
                    ? 'animate-pulse border-red-500/50 bg-red-500/20 text-red-400 shadow-red-500/50 backdrop-blur-xl'
                    : 'border-neon-purple/50 bg-gradient-to-br from-neon-purple to-neon-orange text-white shadow-neon-purple hover:border-neon-purple',
                )}
                title={!supportsRecognition ? 'Spracherkennung nicht unterstützt' : isListening ? 'Aufnahme stoppen' : 'Spracheingabe starten'}
              >
                {isListening ? (
                  <Square className="h-5 w-5" />
                ) : (
                  <Mic className="h-5 w-5" />
                )}
              </motion.button>
            )}
          </div>
          
          {/* Character Counter Text */}
          <div className="mt-2 flex justify-end">
            <span className="text-xs text-gray-500">
              <span className={clsx('font-medium', isNearLimit && 'text-neon-orange')}>{value.length}</span> / {maxLength}
            </span>
          </div>
        </div>
      </div>
    </form>
  );
};
