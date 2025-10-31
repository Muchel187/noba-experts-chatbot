import { Fragment, useEffect, useRef } from 'react';
import { AuthorRole, ChatMessage } from '@/types';
import { Volume2, Pause, User, Bot } from 'lucide-react';
import { motion, AnimatePresence } from 'framer-motion';
import clsx from 'clsx';

interface ChatMessageListProps {
  messages: ChatMessage[];
  onSpeak?: (messageId: string, text: string) => void;
  onStopSpeaking?: () => void;
  ttsEnabled: boolean;
  supportsSpeech: boolean;
  speakingMessageId?: string | null;
  isTyping?: boolean;
}

const formatTime = (timestamp: string) => {
  const date = new Date(timestamp);
  return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
};

const formatMessageText = (text: string) => {
  // Konvertiere **bold** zu <strong>
  let formatted = text.replace(/\*\*(.+?)\*\*/g, '<strong class="font-semibold text-slate-900">$1</strong>');

  // Konvertiere Bullet Points
  formatted = formatted.replace(
    /^([•\-])\s+(.+)$/gm,
    '<div class="flex gap-2 pl-2 my-1"><span class="text-noba-500 mt-1">•</span><span class="flex-1">$2</span></div>'
  );

  // Konvertiere Emojis + Headers
  formatted = formatted.replace(
    /^(📋|✅|💡|🔧|🎯|⭐)\s+(.+?):/gm,
    '<div class="mt-4 mb-2 flex items-center gap-2 border-b border-slate-200/60 pb-2"><span class="text-xl">$1</span><strong class="font-semibold text-slate-800">$2</strong></div>'
  );

  return formatted;
};

const messageVariants = {
  initial: { opacity: 0, y: 20, scale: 0.95 },
  animate: { opacity: 1, y: 0, scale: 1 },
  exit: { opacity: 0, scale: 0.95 },
};

const typingVariants = {
  initial: { opacity: 0, y: 10 },
  animate: { opacity: 1, y: 0 },
  exit: { opacity: 0, y: -10 },
};

export const ChatMessageList = ({
  messages,
  onSpeak,
  onStopSpeaking,
  ttsEnabled,
  supportsSpeech,
  speakingMessageId,
  isTyping = false,
}: ChatMessageListProps) => {
  const endRef = useRef<HTMLDivElement | null>(null);

  useEffect(() => {
    endRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages.length, isTyping]);

  return (
    <div className="flex-1 overflow-y-auto px-4 py-6 scrollbar-hide">
      <div className="mx-auto flex w-full max-w-3xl flex-col gap-4">
        <AnimatePresence mode="popLayout">
          {messages.map((message, index) => {
            const isUser = message.role === AuthorRole.USER;
            const isSystem = message.role === AuthorRole.SYSTEM;
            const isBot = message.role === AuthorRole.BOT;

            return (
              <motion.div
                key={message.id}
                variants={messageVariants}
                initial="initial"
                animate="animate"
                exit="exit"
                transition={{
                  duration: 0.3,
                  delay: index * 0.05,
                  ease: [0.16, 1, 0.3, 1],
                }}
                className={clsx(
                  'flex w-full gap-3',
                  isUser ? 'justify-end' : 'justify-start',
                )}
              >
                {/* Avatar */}
                {!isUser && !isSystem && (
                  <motion.div
                    initial={{ scale: 0 }}
                    animate={{ scale: 1 }}
                    transition={{ delay: index * 0.05 + 0.1 }}
                    className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-noba-500 to-noba-600 shadow-lg"
                  >
                    <Bot className="h-5 w-5 text-white" strokeWidth={2.5} />
                  </motion.div>
                )}

                {/* Message Bubble */}
                <div
                  className={clsx(
                    'group relative max-w-[85%] rounded-2xl px-5 py-3.5 text-sm transition-all',
                    isSystem && 'glass w-full max-w-full border-emerald-200/60 bg-emerald-50/80 text-emerald-900',
                    isUser && 'rounded-br-sm bg-gradient-to-br from-noba-500 to-noba-600 text-white shadow-lg shadow-noba-500/25',
                    isBot && 'glass rounded-bl-sm bg-white/80 text-slate-900 shadow-3d hover:shadow-3d-lg',
                  )}
                >
                  {/* Message Content */}
                  {isUser || isSystem ? (
                    <p className="whitespace-pre-wrap leading-relaxed">{message.text}</p>
                  ) : (
                    <div
                      className="prose prose-sm max-w-none whitespace-pre-wrap leading-relaxed prose-strong:text-slate-900"
                      dangerouslySetInnerHTML={{ __html: formatMessageText(message.text) }}
                    />
                  )}

                  {/* Timestamp & Metadata */}
                  <div
                    className={clsx(
                      'mt-2 flex items-center gap-2 text-[11px] font-medium uppercase tracking-wider',
                      isUser ? 'text-white/70' : 'text-slate-400',
                    )}
                  >
                    <span>{formatTime(message.timestamp)}</span>
                    {message.metadata?.leadQualified && (
                      <span className="rounded-full bg-emerald-500/20 px-2 py-0.5 text-emerald-600">
                        Lead
                      </span>
                    )}
                  </div>

                  {/* TTS Button */}
                  {isBot && supportsSpeech && ttsEnabled && (
                    <motion.button
                      type="button"
                      aria-label="Nachricht vorlesen"
                      whileHover={{ scale: 1.1 }}
                      whileTap={{ scale: 0.95 }}
                      onClick={() =>
                        speakingMessageId === message.id
                          ? onStopSpeaking?.()
                          : onSpeak?.(message.id, message.text)
                      }
                      className="absolute -right-12 top-1/2 hidden h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 shadow-lg transition-all hover:border-noba-500 hover:text-noba-500 focus-visible:flex group-hover:flex"
                    >
                      {speakingMessageId === message.id ? (
                        <Pause className="h-4 w-4" />
                      ) : (
                        <Volume2 className="h-4 w-4" />
                      )}
                    </motion.button>
                  )}

                  {/* Quick Replies (deprecated in message) */}
                  {message.metadata?.quickReplies && message.metadata.quickReplies.length > 0 && (
                    <div className="mt-3 flex flex-wrap gap-2 text-xs text-slate-400">
                      {message.metadata.quickReplies.map((reply) => (
                        <span key={reply} className="rounded-full border border-slate-200 px-3 py-1">
                          {reply}
                        </span>
                      ))}
                    </div>
                  )}
                </div>

                {/* User Avatar */}
                {isUser && (
                  <motion.div
                    initial={{ scale: 0 }}
                    animate={{ scale: 1 }}
                    transition={{ delay: index * 0.05 + 0.1 }}
                    className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-slate-600 to-slate-700 shadow-lg"
                  >
                    <User className="h-5 w-5 text-white" strokeWidth={2.5} />
                  </motion.div>
                )}
              </motion.div>
            );
          })}

          {/* Typing Indicator */}
          {isTyping && (
            <motion.div
              variants={typingVariants}
              initial="initial"
              animate="animate"
              exit="exit"
              className="flex items-center gap-3"
            >
              <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-noba-500 to-noba-600 shadow-lg">
                <Bot className="h-5 w-5 text-white" strokeWidth={2.5} />
              </div>
              <div className="glass rounded-2xl rounded-bl-sm bg-white/80 px-5 py-4 shadow-3d">
                <div className="flex items-center gap-2">
                  <div className="flex gap-1">
                    <motion.span
                      animate={{ scale: [1, 1.3, 1] }}
                      transition={{ duration: 1, repeat: Infinity, delay: 0 }}
                      className="h-2 w-2 rounded-full bg-noba-500"
                    />
                    <motion.span
                      animate={{ scale: [1, 1.3, 1] }}
                      transition={{ duration: 1, repeat: Infinity, delay: 0.2 }}
                      className="h-2 w-2 rounded-full bg-noba-500"
                    />
                    <motion.span
                      animate={{ scale: [1, 1.3, 1] }}
                      transition={{ duration: 1, repeat: Infinity, delay: 0.4 }}
                      className="h-2 w-2 rounded-full bg-noba-500"
                    />
                  </div>
                  <span className="text-sm font-medium text-slate-600">Der KI-Assistent schreibt</span>
                </div>
              </div>
            </motion.div>
          )}
        </AnimatePresence>
        <div ref={endRef} />
      </div>
    </div>
  );
};
