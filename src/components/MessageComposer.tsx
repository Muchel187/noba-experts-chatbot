import { FormEvent, useRef, type ChangeEvent, type KeyboardEvent } from 'react';
import { useAutoResizeTextarea } from '@/hooks/useAutoResizeTextarea';
import { Send, Paperclip, Loader2 } from 'lucide-react';
import { motion } from 'framer-motion';
import clsx from 'clsx';

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

  const handleSubmit = (event: FormEvent) => {
    event.preventDefault();
    if (!value.trim() || disabled) return;
    onSubmit();
  };

  const charPercentage = (value.length / maxLength) * 100;
  const isNearLimit = charPercentage > 80;

  return (
    <form
      onSubmit={handleSubmit}
      className="glass sticky bottom-0 border-t border-slate-200/60 bg-white/80 px-4 py-4 backdrop-blur-xl"
    >
      <div className="mx-auto flex w-full max-w-3xl items-end gap-3">
        {/* Upload Button */}
        <motion.button
          type="button"
          onClick={onOpenUpload}
          disabled={disabled || !onOpenUpload}
          whileHover={{ scale: disabled || !onOpenUpload ? 1 : 1.05 }}
          whileTap={{ scale: disabled || !onOpenUpload ? 1 : 0.95 }}
          className={clsx(
            'flex h-12 w-12 shrink-0 items-center justify-center rounded-full border shadow-lg transition-all',
            disabled || !onOpenUpload
              ? 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-400'
              : 'border-slate-200 bg-white text-slate-600 hover:border-noba-500 hover:text-noba-500 hover:shadow-glow-orange'
          )}
        >
          <Paperclip className="h-5 w-5" />
        </motion.button>

        {/* Input Area */}
        <div className="relative flex-1">
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
            placeholder="Nachricht schreiben …"
            disabled={disabled}
            className={clsx(
              'w-full resize-none rounded-2xl border bg-white px-4 py-3.5 pr-16 text-sm text-slate-800 shadow-inner transition-all placeholder:text-slate-400',
              'focus:border-noba-500 focus:shadow-lg focus:ring-4 focus:ring-noba-500/10',
              disabled && 'cursor-not-allowed opacity-60',
              'border-slate-200',
            )}
            rows={1}
          />

          {/* Character Count - Circular */}
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
                  className="text-slate-200"
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
                    isNearLimit ? 'text-orange-500' : 'text-noba-500',
                  )}
                />
              </svg>
              <div className="absolute inset-0 flex items-center justify-center">
                <span className={clsx(
                  'text-[9px] font-bold',
                  isNearLimit ? 'text-orange-500' : 'text-slate-400',
                )}>
                  {value.length}
                </span>
              </div>
            </div>
          </div>
        </div>

        {/* Send Button */}
        <motion.button
          type="submit"
          disabled={disabled || !value.trim()}
          whileHover={{ scale: disabled || !value.trim() ? 1 : 1.05 }}
          whileTap={{ scale: disabled || !value.trim() ? 1 : 0.95 }}
          className={clsx(
            'flex h-12 w-12 shrink-0 items-center justify-center rounded-full shadow-lg transition-all',
            disabled || !value.trim()
              ? 'cursor-not-allowed bg-slate-300 text-slate-500'
              : 'bg-gradient-to-br from-noba-500 to-noba-600 text-white shadow-glow-orange hover:shadow-glow-orange-lg',
          )}
        >
          {disabled ? (
            <Loader2 className="h-5 w-5 animate-spin" />
          ) : (
            <Send className="h-5 w-5" />
          )}
        </motion.button>
      </div>
    </form>
  );
};
