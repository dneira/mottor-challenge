const segmenter = new Intl.Segmenter('es', { granularity: 'grapheme' });
const chars = (text: string) => Array.from(segmenter.segment(text), (part) => part.segment);

export function ticketLines(text: string): string[] {
  // Unicode White_Space matches PHP's /\s+/u, including U+0085 (unlike JS \s).
  const words = text.split(/\p{White_Space}+/u).filter(Boolean);
  const lines: string[] = [];
  let current = '';
  for (let word of words) {
    if (current && chars(current).length + 1 + chars(word).length <= 28) {
      current += ` ${word}`;
      continue;
    }
    if (current) { lines.push(current); current = ''; }
    let parts = chars(word);
    while (parts.length > 28) {
      lines.push(parts.slice(0, 28).join(''));
      parts = parts.slice(28);
    }
    word = parts.join('');
    current = word;
  }
  if (current) lines.push(current);
  if (lines.length > 4) {
    return [...lines.slice(0, 3), chars(lines[3]).slice(0, 27).join('').replace(/ +$/u, '') + '…'];
  }
  return lines;
}
