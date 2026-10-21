import { PageFlip } from 'page-flip';
import { paginateText, pageForOffset, physicalIndex, logicalIndex, clamp } from './pagination';

export async function setupReader(root, api, announce) {
    const labels = JSON.parse(document.body.dataset.labels || '{}');
    const rtl = root.dataset.direction === 'rtl';
    const text = root.querySelector('[data-chapter-text]').value;
    const textLength = Array.from(text).length;
    const chapter = Number(root.dataset.chapter);
    const scroll = root.querySelector('.scroll-reader');
    const article = root.querySelector('[data-chapter-html]');
    const stage = root.querySelector('.book-stage');
    const frame = root.querySelector('.book-frame');
    const range = root.querySelector('[data-page-range]');
    const status = root.querySelector('[data-page-status]');
    const storage = {
        get(key, fallback) { try { return JSON.parse(localStorage.getItem(key)) ?? fallback; } catch { return fallback; } },
        set(key, value) { try { localStorage.setItem(key, JSON.stringify(value)); } catch { /* private browsing */ } },
    };
    let font = clamp(storage.get('imary.reader.font', 22), 16, 32);
    let theme = storage.get('imary.reader.theme', 'paper');
    if (!['paper','night','day'].includes(theme)) theme = 'paper';
    let mode = root.dataset.mode;
    let offset = clamp(root.dataset.offset, 0, textLength);
    let flip = null, pages = [], pageIndex = 0, resizing = false, saveTimer, resizeTimer;
    let savedOffset = -1;
    root.style.setProperty('--reader-font', `${font}px`);
    root.dataset.paperTheme = theme;
    root.querySelector('[data-reader-theme]').value = theme;
    const persist = (now = false) => {
        if (!root.dataset.progressUrl) return;
        clearTimeout(saveTimer);
        const write = () => {
            if (savedOffset === offset) return;
            const value = offset;
            api(root.dataset.progressUrl, { chapter_id: chapter, offset: value, mode }, { keepalive: true })
                .then(() => { savedOffset = value; }).catch(() => { /* retain the current position for the next retry */ });
        };
        if (now) write(); else saveTimer = setTimeout(write, 1000);
    };
    function positionScroll() {
        const rect = article.getBoundingClientRect();
        const target = window.scrollY + rect.top + Math.max(0, rect.height - window.innerHeight * .5) * (offset / Math.max(1, textLength)) - 100;
        if (offset) window.scrollTo({ top: Math.max(0, target), behavior: 'instant' });
    }
    function updateStatus(physical) {
        if (!pages.length) return;
        let logical = clamp(logicalIndex(physical, pages.length, rtl), 0, pages.length - 1);
        // In a two-page RTL spread the right-hand page is read first.
        if (rtl && flip?.getOrientation() === 'landscape') logical = Math.max(0, logical - 1);
        pageIndex = logical;
        offset = pages[logical].start;
        range.value = String(logical);
        status.textContent = `${logical + 1} / ${pages.length}`;
        persist();
    }
    function buildBook() {
        if (mode !== 'book' || resizing) return;
        resizing = true;
        const previousOffset = offset;
        if (flip) { flip.destroy(); flip = null; }
        frame.replaceChildren();
        const mount = document.createElement('div');
        frame.append(mount);
        const availableWidth = Math.max(220, frame.clientWidth - 20);
        const onePage = availableWidth < 660;
        const width = Math.floor(Math.min(onePage ? availableWidth : availableWidth / 2, 490));
        const height = Math.max(400, Math.min(680, Math.floor(width * 1.46)));
        const probe = document.createElement('div');
        probe.className = 'book-page measurement-page';
        probe.style.width = `${width}px`; probe.style.height = `${height}px`;
        const probeInner = document.createElement('div'); probeInner.className = 'book-page-inner';
        const probeTitle = document.createElement('div'); probeTitle.className = 'book-page-heading'; probeTitle.textContent = root.dataset.title;
        const probeText = document.createElement('div'); probeText.className = 'book-page-text'; probeText.dir = root.dataset.direction;
        const probeNumber = document.createElement('div'); probeNumber.className = 'book-page-number'; probeNumber.textContent = '1';
        probeInner.append(probeTitle,probeText,probeNumber); probe.append(probeInner); root.append(probe);
        pages = paginateText(text, candidate => { probeText.textContent = candidate; return probeText.scrollHeight <= probeText.clientHeight + 1; });
        probe.remove();
        if (!onePage && pages.length && root.dataset.cover) pages.splice(1, 0, { start: pages[0].end, end: pages[0].end, text: '', illustration: true });
        // A blank leaf keeps RTL spreads paired. It is a real trailing blank, never truncated text.
        if (!onePage && pages.length % 2) pages.push({ start: textLength, end: textLength, text: '' });
        const physicalPages = rtl ? [...pages].reverse() : pages;
        const nodes = physicalPages.map((page, i) => {
            const element = document.createElement('div'); element.className = 'book-page'; element.dataset.side = i % 2 ? 'right' : 'left';
            const inner = document.createElement('div'); inner.className = 'book-page-inner'; inner.dir = root.dataset.direction;
            const heading = document.createElement('div'); heading.className = 'book-page-heading'; heading.textContent = root.dataset.title;
            const body = document.createElement('div'); body.className = 'book-page-text'; body.textContent = page.text;
            if (page.illustration) { body.classList.add('book-illustration'); const picture=document.createElement('img'); picture.src=root.dataset.cover; picture.alt=root.dataset.title; body.append(picture); }
            const number = document.createElement('div'); number.className = 'book-page-number'; number.textContent = String(logicalIndex(i, pages.length, rtl) + 1);
            inner.append(heading, body, number); element.append(inner); mount.append(element); return element;
        });
        const current = pageForOffset(pages, previousOffset);
        flip = new PageFlip(mount, { width, height, size: 'fixed', showCover: false, usePortrait: onePage, autoSize: true, startPage: physicalIndex(current, pages.length, rtl), maxShadowOpacity: .4, flippingTime: matchMedia('(prefers-reduced-motion: reduce)').matches ? 1 : 650, mobileScrollSupport: true, showPageCorners: true, useMouseEvents: true, swipeDistance: 40 });
        range.max = String(pages.length - 1);
        flip.on('flip', event => updateStatus(event.data));
        flip.loadFromHTML(nodes);
        offset = previousOffset;
        pageIndex = current;
        range.value = String(current);
        status.textContent = `${current + 1} / ${pages.length}`;
        resizing = false;
    }
    async function setMode(nextMode) {
        mode = nextMode; root.dataset.mode = mode;
        root.querySelectorAll('[data-mode-button]').forEach(b => { b.classList.toggle('active', b.dataset.modeButton === mode); b.setAttribute('aria-pressed', String(b.dataset.modeButton === mode)); });
        scroll.hidden = mode !== 'scroll'; stage.hidden = mode !== 'book';
        await document.fonts.ready;
        if (mode === 'book') buildBook(); else positionScroll();
        savedOffset = -1; persist();
        const url = new URL(location.href); url.searchParams.set('mode', mode); history.replaceState(null, '', url);
        root.querySelectorAll('.chapter-navigation a,.contents-menu a').forEach(a => { const next = new URL(a.href); next.searchParams.set('mode', mode); a.href = next.toString(); });
    }
    root.querySelectorAll('[data-mode-button]').forEach(button => button.addEventListener('click', () => setMode(button.dataset.modeButton)));
    root.querySelectorAll('[data-font]').forEach(button => button.addEventListener('click', () => { font = clamp(font + Number(button.dataset.font), 16, 32); root.style.setProperty('--reader-font', `${font}px`); storage.set('imary.reader.font', font); if (mode === 'book') buildBook(); else positionScroll(); }));
    root.querySelector('[data-reader-theme]').addEventListener('change', e => { theme = e.target.value; root.dataset.paperTheme = theme; storage.set('imary.reader.theme', theme); });
    const turn = next => { if (!flip) return; (next !== rtl ? flip.flipNext.bind(flip) : flip.flipPrev.bind(flip))(); };
    root.querySelector('[data-page-prev]').addEventListener('click', () => turn(false));
    root.querySelector('[data-page-next]').addEventListener('click', () => turn(true));
    range.addEventListener('input', () => { if (!flip) return; const logical = clamp(range.value,0,pages.length-1); flip.turnToPage(physicalIndex(logical,pages.length,rtl)); updateStatus(flip.getCurrentPageIndex()); });
    root.addEventListener('keydown', e => { if (mode !== 'book' || /INPUT|TEXTAREA|SELECT/.test(e.target.tagName)) return; if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') { e.preventDefault(); turn(e.key === (rtl ? 'ArrowLeft' : 'ArrowRight')); } });
    root.tabIndex = -1;
    root.querySelector('[data-fullscreen]').addEventListener('click', async () => { try { if (document.fullscreenElement) await document.exitFullscreen(); else await root.requestFullscreen(); } catch { announce(labels.error); } });
    root.querySelector('[data-bookmark]').addEventListener('click', async () => { if (!root.dataset.bookmarkUrl) { location.href = '/login'; return; } try { await api(root.dataset.bookmarkUrl,{ chapter_id:chapter,offset }); announce(labels.saved); } catch { announce(labels.save_error); } });
    window.addEventListener('scroll', () => { if (mode !== 'scroll') return; const rect = article.getBoundingClientRect(); const ratio = clamp((100 - rect.top) / Math.max(1,rect.height - window.innerHeight*.5),0,1); offset = Math.round(ratio*textLength); persist(); }, { passive:true });
    window.addEventListener('pagehide', () => persist(true));
    document.addEventListener('visibilitychange', () => { if (document.visibilityState==='hidden') persist(true); });
    let oldWidth = Math.round(root.clientWidth);
    new ResizeObserver(() => { const width=Math.round(root.clientWidth); if (width===oldWidth) return; oldWidth=width; clearTimeout(resizeTimer);resizeTimer=setTimeout(buildBook,180); }).observe(root);
    const requested = Number(new URL(location.href).searchParams.get('offset'));
    if (new URL(location.href).searchParams.has('offset')) offset=clamp(requested,0,textLength);
    await setMode(mode);
}
