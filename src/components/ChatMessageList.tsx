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
                    className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-neon-purple via-neon-purple to-neon-orange shadow-neon-purple ring-2 ring-neon-purple/30"
                  >
                    <svg className="h-5 w-5 text-dark-primary" viewBox="0 0 24 24" fill="currentColor">
                      <path d="M12 2L2 7L12 12L22 7L12 2Z" />
                      <path d="M2 17L12 22L22 17" />
                      <path d="M2 12L12 17L22 12" />
                    </svg>
                  </motion.div>
                )}

                {/* Message Bubble */}
                <div
                  className={clsx(
                    'group relative max-w-[85%] rounded-2xl px-5 py-3.5 text-sm transition-all sm:max-w-md md:max-w-lg lg:max-w-2xl',
                    isSystem && 'glass-strong w-full max-w-full border border-emerald-500/30 bg-emerald-500/10 text-emerald-300',
                    isUser && 'rounded-br-md border border-neon-orange/30 bg-gradient-to-br from-neon-orange to-neon-orange-bright text-white shadow-neon-orange',
                    isBot && 'glass-strong rounded-bl-md border border-neon-purple/20 text-gray-100 shadow-glass-lg backdrop-blur-xl',
                  )}
                >
                  {/* Hover Glow for User Messages */}
                  {isUser && (
                    <div className="absolute -inset-1 rounded-2xl bg-gradient-to-r from-neon-orange to-neon-purple opacity-0 blur-xl transition-opacity duration-500 group-hover:opacity-30"></div>
                  )}
                  
                  {/* Neon Border Glow for Bot Messages */}
                  {isBot && (
                    <div className="absolute -inset-[1px] rounded-2xl bg-gradient-to-r from-neon-purple/0 via-neon-purple/20 to-neon-purple/0 opacity-0 transition-opacity duration-500 group-hover:opacity-100"></div>
                  )}
                  
                  {/* Message Content */}
                  {isUser || isSystem ? (
                    <p className="relative z-10 whitespace-pre-wrap text-[15px] leading-relaxed">{message.text}</p>
                  ) : (
                    <div
                      className="prose prose-sm prose-invert relative z-10 max-w-none whitespace-pre-wrap text-[15px] leading-relaxed"
                      dangerouslySetInnerHTML={{ __html: formatMessageText(message.text) }}
                    />
                  )}

                  {/* Timestamp & Metadata */}
                  <div
                    className={clsx(
                      'relative z-10 mt-2 flex items-center gap-2 text-xs',
                      isUser ? 'text-white/60' : 'text-gray-500',
                    )}
                  >
                    <span>{formatTime(message.timestamp)}</span>
                    {message.metadata?.leadQualified && (
                      <span className="rounded-full bg-neon-purple/20 px-2 py-0.5 text-neon-purple">
                        Lead
                      </span>
                    )}
                  </div>

                  {/* TTS Button */}
                  {isBot && supportsSpeech && ttsEnabled && (
                    <motion.button
                      type="button"
                      aria-label="Nachricht vorlesen"
                      whileHover={{ scale: 1.05 }}
                      whileTap={{ scale: 0.95 }}
                      onClick={() =>
                        speakingMessageId === message.id
                          ? onStopSpeaking?.()
                          : onSpeak?.(message.id, message.text)
                      }
                      className="relative z-10 mt-3 flex items-center gap-2 rounded-lg border border-neon-purple/30 bg-dark-card px-3 py-1.5 text-xs font-medium text-gray-300 backdrop-blur-xl transition-all hover:border-neon-purple hover:bg-dark-card-strong hover:text-white hover:shadow-neon-purple"
                    >
                      {speakingMessageId === message.id ? (
                        <>
                          <Pause className="h-4 w-4" />
                          <span>Pause</span>
                        </>
                      ) : (
                        <>
                          <Volume2 className="h-4 w-4" />
                          <span>Vorlesen</span>
                        </>
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
              <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-neon-purple via-neon-purple to-neon-orange shadow-neon-purple ring-2 ring-neon-purple/30">
                <svg className="h-5 w-5 text-dark-primary" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M12 2L2 7L12 12L22 7L12 2Z" />
                  <path d="M2 17L12 22L22 17" />
                  <path d="M2 12L12 17L22 12" />
                </svg>
              </div>
              <div className="glass-strong rounded-2xl rounded-bl-md border border-neon-purple/20 px-6 py-4 shadow-glass-md backdrop-blur-xl">
                <div className="flex items-center gap-1.5">
                  <div className="h-2.5 w-2.5 animate-bounce rounded-full bg-gradient-to-r from-neon-purple to-neon-purple shadow-neon-purple [animation-delay:-0.3s]"></div>
                  <div className="h-2.5 w-2.5 animate-bounce rounded-full bg-gradient-to-r from-neon-purple to-neon-orange shadow-neon-purple [animation-delay:-0.15s]"></div>
                  <div className="h-2.5 w-2.5 animate-bounce rounded-full bg-gradient-to-r from-neon-orange to-neon-purple shadow-neon-orange"></div>
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
