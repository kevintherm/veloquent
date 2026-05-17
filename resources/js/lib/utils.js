import { clsx } from 'clsx'
import { twMerge } from 'tailwind-merge'

export function cn(...inputs) {
  return twMerge(clsx(inputs))
}

export function stripHtml(html) {
  if (typeof html !== 'string' || !html) {
    return html || '';
  }


  const withSpaces = html.replace(/<[^>]+>/g, (match) => {
    return /<\/(p|div|h[1-6]|tr|li|blockquote|pre|address|article|aside|dl|fieldset|figcaption|figure|footer|header|hgroup|main|nav|section)>|<(br|hr|p|div|li|h[1-6])\s*\/?>/i.test(match) ? ' ' : '';
  });

  const doc = new DOMParser().parseFromString(withSpaces, 'text/html');
  const text = doc.body.textContent || "";

  return text.replace(/\s+/g, ' ').trim();
}

export function parseServerDate(value) {
  if (!value) return null;

  if (value instanceof Date) {
    return value;
  }

  let dateValue = String(value);

  if (dateValue.includes(' ') && !dateValue.includes('T') && !dateValue.includes('Z')) {
    dateValue = dateValue.replace(' ', 'T') + 'Z';
  }

  const parsed = new Date(dateValue);
  return Number.isNaN(parsed.getTime()) ? new Date(value) : parsed;
}
