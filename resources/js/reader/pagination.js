/** Offsets count Unicode code points so they agree with PHP's mb_strlen. */
export function paginateText(text, fits, maxChunk = 12000) {
    const chars = Array.from(text);
    const pages = [];
    let start = 0;
    while (start < chars.length) {
        let low = 1, high = Math.min(maxChunk, chars.length - start), best = 1;
        while (low <= high) {
            const middle = Math.floor((low + high) / 2);
            if (fits(chars.slice(start, start + middle).join(''))) { best = middle; low = middle + 1; }
            else high = middle - 1;
        }
        let end = start + best;
        if (end < chars.length) {
            for (let i = end - 1; i > start + Math.floor(best * 0.6); i--) {
                if (/\s/u.test(chars[i])) { end = i + 1; break; }
            }
        }
        pages.push({ start, end, text: chars.slice(start, end).join('') });
        start = end;
    }
    return pages.length ? pages : [{ start: 0, end: 0, text: '' }];
}
export function pageForOffset(pages, offset) {
    const safe = Math.max(0, Number(offset) || 0);
    const index = pages.findIndex(p => safe >= p.start && safe < p.end);
    return index < 0 ? Math.max(0, pages.length - 1) : index;
}
export function physicalIndex(logical, count, rtl) { return rtl ? count - 1 - logical : logical; }
export function logicalIndex(physical, count, rtl) { return rtl ? count - 1 - physical : physical; }
export function clamp(value, min, max) { return Math.max(min, Math.min(max, Number(value) || 0)); }
