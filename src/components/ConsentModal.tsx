import { motion, AnimatePresence } from 'framer-motion';
import { Shield, Check, X } from 'lucide-react';

interface ConsentModalProps {
  isOpen: boolean;
  onAccept: () => void;
  onDecline: () => void;
}

const backdropVariants = {
  hidden: { opacity: 0 },
  visible: { opacity: 1 },
};

const modalVariants = {
  hidden: {
    opacity: 0,
    scale: 0.8,
    y: 50,
  },
  visible: {
    opacity: 1,
    scale: 1,
    y: 0,
    transition: {
      type: 'spring',
      stiffness: 300,
      damping: 25,
    },
  },
  exit: {
    opacity: 0,
    scale: 0.9,
    y: 20,
  },
};

export const ConsentModal = ({ isOpen, onAccept, onDecline }: ConsentModalProps) => (
  <AnimatePresence>
    {isOpen && (
      <>
        {/* Backdrop */}
        <motion.div
          variants={backdropVariants}
          initial="hidden"
          animate="visible"
          exit="hidden"
          className="fixed inset-0 z-50 bg-gradient-to-br from-slate-900/90 via-slate-800/90 to-slate-900/90 backdrop-blur-md"
        />

        {/* Modal */}
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
          <motion.div
            variants={modalVariants}
            initial="hidden"
            animate="visible"
            exit="exit"
            className="glass w-full max-w-lg overflow-hidden rounded-3xl border border-slate-200 bg-white/95 shadow-3d-lg backdrop-blur-xl"
          >
            {/* Header with Icon */}
            <div className="bg-gradient-to-br from-noba-500 to-noba-600 px-8 py-6">
              <motion.div
                initial={{ scale: 0, rotate: -180 }}
                animate={{ scale: 1, rotate: 0 }}
                transition={{ delay: 0.2, type: 'spring', stiffness: 200 }}
                className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-white/20 backdrop-blur-xl"
              >
                <Shield className="h-8 w-8 text-white" strokeWidth={2} />
              </motion.div>
            </div>

            {/* Content */}
            <div className="p-8">
              <motion.h2
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: 0.3 }}
                className="text-center text-2xl font-bold text-slate-900"
              >
                Datenschutzeinwilligung
              </motion.h2>

              <motion.p
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: 0.4 }}
                className="mt-4 text-center text-sm leading-relaxed text-slate-600"
              >
                Wir verwenden Ihre Angaben, um Sie in unserer Beratung optimal zu unterstützen.
                Bitte bestätigen Sie, dass wir Ihre Eingaben sowie Dokumente verarbeiten und an
                unser Recruiting-Team weiterleiten dürfen.
              </motion.p>

              <motion.div
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: 0.5 }}
                className="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-4"
              >
                <p className="text-xs text-slate-500">
                  ✓ Vertrauliche Behandlung Ihrer Daten<br />
                  ✓ DSGVO-konforme Verarbeitung<br />
                  ✓ Jederzeit widerrufbar
                </p>
              </motion.div>

              {/* Buttons */}
              <motion.div
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: 0.6 }}
                className="mt-8 flex flex-col gap-3 sm:flex-row"
              >
                <motion.button
                  type="button"
                  onClick={onDecline}
                  whileHover={{ scale: 1.02 }}
                  whileTap={{ scale: 0.98 }}
                  className="flex flex-1 items-center justify-center gap-2 rounded-full border-2 border-slate-200 px-6 py-3 text-sm font-semibold text-slate-600 transition-all hover:border-slate-300 hover:bg-slate-50"
                >
                  <X className="h-4 w-4" />
                  Ablehnen
                </motion.button>

                <motion.button
                  type="button"
                  onClick={onAccept}
                  whileHover={{ scale: 1.02 }}
                  whileTap={{ scale: 0.98 }}
                  className="flex flex-1 items-center justify-center gap-2 rounded-full bg-gradient-to-br from-noba-500 to-noba-600 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-noba-500/30 transition-all hover:shadow-glow-orange"
                >
                  <Check className="h-4 w-4" />
                  Einverstanden
                </motion.button>
              </motion.div>
            </div>
          </motion.div>
        </div>
      </>
    )}
  </AnimatePresence>
);
