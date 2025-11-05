import { useState, useEffect, useCallback, useRef } from 'react';

interface SpeechRecognitionOptions {
  language?: string;
  continuous?: boolean;
  interimResults?: boolean;
  onResult?: (transcript: string) => void;
  onError?: (error: string) => void;
}

interface UseSpeechRecognitionReturn {
  isListening: boolean;
  transcript: string;
  startListening: () => void;
  stopListening: () => void;
  supportsRecognition: boolean;
  error: string | null;
}

// TypeScript types for Web Speech API
interface SpeechRecognitionEvent extends Event {
  results: SpeechRecognitionResultList;
  resultIndex: number;
}

interface SpeechRecognitionErrorEvent extends Event {
  error: string;
  message: string;
}

interface SpeechRecognitionResultList {
  length: number;
  item(index: number): SpeechRecognitionResult;
  [index: number]: SpeechRecognitionResult;
}

interface SpeechRecognitionResult {
  length: number;
  item(index: number): SpeechRecognitionAlternative;
  [index: number]: SpeechRecognitionAlternative;
  isFinal: boolean;
}

interface SpeechRecognitionAlternative {
  transcript: string;
  confidence: number;
}

interface SpeechRecognitionInterface {
  continuous: boolean;
  interimResults: boolean;
  lang: string;
  start(): void;
  stop(): void;
  abort(): void;
  onstart: ((this: SpeechRecognitionInterface, ev: Event) => void) | null;
  onend: ((this: SpeechRecognitionInterface, ev: Event) => void) | null;
  onresult: ((this: SpeechRecognitionInterface, ev: SpeechRecognitionEvent) => void) | null;
  onerror: ((this: SpeechRecognitionInterface, ev: SpeechRecognitionErrorEvent) => void) | null;
}

declare global {
  interface Window {
    SpeechRecognition: new () => SpeechRecognitionInterface;
    webkitSpeechRecognition: new () => SpeechRecognitionInterface;
  }
}

export const useSpeechRecognition = (
  options: SpeechRecognitionOptions = {}
): UseSpeechRecognitionReturn => {
  const {
    language = 'de-DE',
    continuous = false,
    interimResults = false,
    onResult,
    onError,
  } = options;

  const [isListening, setIsListening] = useState(false);
  const [transcript, setTranscript] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [supportsRecognition, setSupportsRecognition] = useState(false);

  const recognitionRef = useRef<SpeechRecognitionInterface | null>(null);
  const onResultRef = useRef(onResult);
  const onErrorRef = useRef(onError);

  // Update refs when callbacks change
  useEffect(() => {
    onResultRef.current = onResult;
    onErrorRef.current = onError;
  }, [onResult, onError]);

  useEffect(() => {
    // Check if browser supports speech recognition
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;

    if (SpeechRecognition) {
      setSupportsRecognition(true);

      try {
        const recognition = new SpeechRecognition();
        recognition.continuous = continuous;
        recognition.interimResults = interimResults;
        recognition.lang = language;

        recognition.onstart = () => {
          setIsListening(true);
          setError(null);
        };

        recognition.onend = () => {
          setIsListening(false);
        };

        recognition.onresult = (event: SpeechRecognitionEvent) => {
          let finalTranscript = '';
          let interimTranscript = '';

          for (let i = event.resultIndex; i < event.results.length; i++) {
            const result = event.results[i];
            const transcriptPart = result[0].transcript;

            if (result.isFinal) {
              finalTranscript += transcriptPart + ' ';
            } else {
              interimTranscript += transcriptPart;
            }
          }

          const combinedTranscript = finalTranscript || interimTranscript;

          if (combinedTranscript) {
            setTranscript(combinedTranscript.trim());
            if (onResultRef.current) {
              onResultRef.current(combinedTranscript.trim());
            }
          }
        };

        recognition.onerror = (event: SpeechRecognitionErrorEvent) => {
          let errorMessage = 'Fehler bei der Spracherkennung';

          switch (event.error) {
            case 'no-speech':
              errorMessage = 'Keine Sprache erkannt';
              break;
            case 'audio-capture':
              errorMessage = 'Kein Mikrofon gefunden';
              break;
            case 'not-allowed':
              errorMessage = 'Mikrofonzugriff verweigert';
              break;
            case 'network':
              errorMessage = 'Netzwerkfehler';
              break;
            case 'aborted':
              errorMessage = 'Spracherkennung abgebrochen';
              break;
            default:
              errorMessage = `Fehler: ${event.error}`;
          }

          setError(errorMessage);
          setIsListening(false);

          if (onErrorRef.current) {
            onErrorRef.current(errorMessage);
          }
        };

        recognitionRef.current = recognition;
      } catch (err) {
        console.error('Failed to initialize speech recognition:', err);
        setSupportsRecognition(false);
      }
    } else {
      setSupportsRecognition(false);
    }

    return () => {
      if (recognitionRef.current) {
        try {
          recognitionRef.current.abort();
        } catch (err) {
          console.error('Error aborting recognition:', err);
        }
      }
    };
  }, [language, continuous, interimResults]);

  const startListening = useCallback(() => {
    if (recognitionRef.current) {
      try {
        // Stop any existing recognition first
        try {
          recognitionRef.current.abort();
        } catch {}

        setTranscript('');
        setError(null);
        recognitionRef.current.start();
      } catch (err) {
        console.error('Error starting recognition:', err);
        setError('Konnte Spracherkennung nicht starten');
      }
    }
  }, []);

  const stopListening = useCallback(() => {
    if (recognitionRef.current) {
      try {
        recognitionRef.current.abort();
        setIsListening(false);
      } catch (err) {
        console.error('Error stopping recognition:', err);
        setIsListening(false);
      }
    }
  }, []);

  return {
    isListening,
    transcript,
    startListening,
    stopListening,
    supportsRecognition,
    error,
  };
};
