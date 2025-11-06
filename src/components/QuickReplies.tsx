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
        className="fixed bottom-24 left-0 right-0 z-[60] px-4 animate-fade-in"
      >
        <div className="mx-auto max-w-4xl">
          <div className="flex flex-wrap justify-center gap-2">
            {options.map((option, index) => {
              const Icon = getIconForOption(option);
              return (
                <motion.button
                  key={option}
                  variants={itemVariants}
                  style={{ animationDelay: `${index * 50}ms` }}
                  type="button"
                  onClick={() => onSelect(option)}
                  whileHover={{ scale: 1.05 }}
                  whileTap={{ scale: 0.95 }}
                  className="group relative overflow-hidden rounded-full glass-strong border border-neon-purple/20 px-4 py-2.5 shadow-glass-md backdrop-blur-xl transition-all duration-300 hover:scale-105 hover:border-neon-purple hover:shadow-neon-purple sm:px-5"
                >
                  <span className="relative z-10 flex items-center gap-2 text-sm font-medium text-gray-300 group-hover:text-neon-purple">
                    <Icon className="h-4 w-4 transition-transform group-hover:scale-110" />
                    <span className="whitespace-nowrap">{option}</span>
                  </span>
                  <div className="absolute inset-0 -z-0 bg-gradient-to-r from-neon-purple/10 to-neon-purple/10 opacity-0 transition-opacity duration-300 group-hover:opacity-100"></div>
                </motion.button>
              );
            })}
          </div>
        </div>
      </motion.div>
    </AnimatePresence>
  );
};
