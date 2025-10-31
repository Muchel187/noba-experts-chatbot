import { useState, useCallback } from 'react';

export const useLocalStorage = <T,>(key: string, defaultValue: T) => {
  const [storedValue, setStoredValue] = useState<T>(() => {
    try {
      const raw = window.localStorage.getItem(key);
      return raw ? (JSON.parse(raw) as T) : defaultValue;
    } catch (error) {
      console.warn(`[useLocalStorage] Failed to parse key ${key}`, error);
      return defaultValue;
    }
  });

  const setValue = useCallback(
    (value: T | ((prev: T) => T)) => {
      try {
        setStoredValue((prev: T) => {
          const newValue = value instanceof Function ? value(prev) : value;
          window.localStorage.setItem(key, JSON.stringify(newValue));
          return newValue;
        });
      } catch (error) {
        console.warn(`[useLocalStorage] Failed to set key ${key}`, error);
      }
    },
    [key],
  );

  const removeValue = useCallback(() => {
    window.localStorage.removeItem(key);
    setStoredValue(defaultValue);
  }, [defaultValue, key]);

  return { value: storedValue, setValue, removeValue } as const;
};
