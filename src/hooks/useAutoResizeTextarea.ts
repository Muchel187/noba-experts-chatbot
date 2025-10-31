import { useEffect, type RefObject } from 'react';

export const useAutoResizeTextarea = (
  ref: RefObject<HTMLTextAreaElement>,
  value: string,
  { maxHeight }: { maxHeight?: number } = {},
) => {
  useEffect(() => {
    const textarea = ref.current;
    if (!textarea) return;

    textarea.style.height = 'auto';
    const nextHeight = textarea.scrollHeight;
    if (maxHeight && nextHeight > maxHeight) {
      textarea.style.height = `${maxHeight}px`;
      textarea.style.overflowY = 'auto';
    } else {
      textarea.style.height = `${nextHeight}px`;
      textarea.style.overflowY = 'hidden';
    }
  }, [ref, value, maxHeight]);
};
