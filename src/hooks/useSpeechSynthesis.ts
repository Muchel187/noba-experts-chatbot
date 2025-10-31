import { useCallback, useEffect, useRef, useState } from 'react';

interface SpeechOptions {
  lang?: string;
  rate?: number;
  pitch?: number;
  volume?: number;
}

export const useSpeechSynthesis = (enabled: boolean, options: SpeechOptions = {}) => {
  const { lang = 'de-DE', rate = 1, pitch = 1, volume = 1 } = options;
  const [supportsSpeech, setSupportsSpeech] = useState<boolean>(false);
  const [isSpeaking, setIsSpeaking] = useState(false);
  const [voices, setVoices] = useState<SpeechSynthesisVoice[]>([]);
  const utteranceRef = useRef<SpeechSynthesisUtterance | null>(null);

  useEffect(() => {
    const available = 'speechSynthesis' in window && typeof window.speechSynthesis?.speak === 'function';
    setSupportsSpeech(available);
    if (!available) return;

    const handleVoicesChanged = () => {
      const list = window.speechSynthesis.getVoices();
      setVoices(list);
    };

    handleVoicesChanged();
    window.speechSynthesis.addEventListener('voiceschanged', handleVoicesChanged);

    return () => {
      window.speechSynthesis.removeEventListener('voiceschanged', handleVoicesChanged);
    };
  }, []);

  const stop = useCallback(() => {
    if (!supportsSpeech) return;
    window.speechSynthesis.cancel();
    setIsSpeaking(false);
    utteranceRef.current = null;
  }, [supportsSpeech]);

  const speak = useCallback(
    (text: string) => {
      if (!supportsSpeech || !enabled || !text.trim()) return;

      stop();
      const utterance = new SpeechSynthesisUtterance(text);
      utterance.lang = lang;
      utterance.rate = rate;
      utterance.pitch = pitch;
      utterance.volume = volume;

      const preferredVoice = voices.find((voice: SpeechSynthesisVoice) => voice.lang.startsWith(lang));
      if (preferredVoice) {
        utterance.voice = preferredVoice;
      }

      utterance.onstart = () => setIsSpeaking(true);
      utterance.onend = () => {
        setIsSpeaking(false);
        utteranceRef.current = null;
      };
      utterance.onerror = () => {
        setIsSpeaking(false);
        utteranceRef.current = null;
      };

      utteranceRef.current = utterance;
      window.speechSynthesis.speak(utterance);
    },
    [enabled, lang, pitch, rate, supportsSpeech, voices, volume, stop],
  );

  useEffect(() => stop, [stop]);

  return { supportsSpeech, speak, stop, isSpeaking } as const;
};
