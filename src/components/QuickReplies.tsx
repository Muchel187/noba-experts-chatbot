import { motion, AnimatePresence } from 'framer-motion';
import { Briefcase, Search, FileText, Lightbulb, Calendar, X, Paperclip } from 'lucide-react';

interface QuickRepliesProps {
  options: string[];
  onSelect: (option: string) => void;
}

const getIconForOption = (option: string) => {
  if (option.includes('Job') || option.includes('suchen')) return Briefcase;
  if (option.includes('Mitarbeiter') || option.includes('finden')) return Search;
  if (option.includes('CV') || option.includes('hochladen')) return Paperclip;
  if (option.includes('Service') || option.includes('Leistung')) return Lightbulb;
  if (option.includes('Termin')) return Calendar;
  if (option.includes('Nein')) return X;
  return Lightbulb;
};

const containerVariants = {
  hidden: { opacity: 0, y: 20 },
  visible: {
    opacity: 1,
    y: 0,
    transition: {
      staggerChildren: 0.05,
      delayChildren: 0.1,
    },
  },
  exit: {
    opacity: 0,
    y: -20,
    transition: {
      staggerChildren: 0.03,
      staggerDirection: -1,
    },
  },
};

const itemVariants = {
  hidden: { opacity: 0, scale: 0.8, y: 20 },
  visible: {
    opacity: 1,
    scale: 1,
    y: 0,
    transition: {
      type: 'spring',
      stiffness: 500,
      damping: 30,
    },
  },
  exit: {
    opacity: 0,
    scale: 0.8,
    transition: {
      duration: 0.2,
    },
  },
};

export const QuickReplies = ({ options, onSelect }: QuickRepliesProps) => {
  if (!options.length) return null;

  return (
    <AnimatePresence mode="wait">
      <motion.div
        variants={containerVariants}
        initial="hidden"
        animate="visible"
        exit="exit"
        className="glass border-t border-slate-200/60 bg-gradient-to-r from-white/50 via-white/70 to-white/50 px-4 py-3 backdrop-blur-xl"
      >
        <div className="mx-auto w-full max-w-3xl">
          <motion.p
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            className="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500"
          >
            Schnellantworten
          </motion.p>
          <div className="flex gap-2 overflow-x-auto pb-1 scrollbar-hide snap-x snap-mandatory">
            {options.map((option) => {
              const Icon = getIconForOption(option);
              return (
                <motion.button
                  key={option}
                  variants={itemVariants}
                  type="button"
                  onClick={() => onSelect(option)}
                  whileHover={{ scale: 1.05, y: -2 }}
                  whileTap={{ scale: 0.95 }}
                  className="glass group flex shrink-0 snap-start items-center gap-2 rounded-full border border-slate-200 bg-white/90 px-4 py-2.5 text-sm font-medium text-slate-700 shadow-lg transition-all hover:border-noba-500 hover:bg-noba-50 hover:text-noba-700 hover:shadow-glow-orange"
                >
                  <Icon className="h-4 w-4 transition-transform group-hover:scale-110" />
                  <span className="whitespace-nowrap">{option}</span>
                </motion.button>
              );
            })}
          </div>
        </div>
      </motion.div>
    </AnimatePresence>
  );
};
