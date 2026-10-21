const canvas = document.querySelector('#personalized-poster');
const source = document.querySelector('#invitation-source');
const attendeeName = document.querySelector('#attendee-name')?.textContent ?? '';
const downloadButton = document.querySelector('#download-invitation');
const status = document.querySelector('#personalized-status');
const context = canvas?.getContext('2d', { alpha: false });

function wrapName(text, maxWidth) {
    const words = text.trim().split(/\s+/u);
    const lines = [];
    let line = '';

    for (const word of words) {
        const candidate = line ? `${line} ${word}` : word;
        if (context.measureText(candidate).width <= maxWidth) {
            line = candidate;
            continue;
        }

        if (line) {
            lines.push(line);
            line = '';
        }

        if (context.measureText(word).width <= maxWidth) {
            line = word;
            continue;
        }

        const segments = typeof Intl.Segmenter === 'function'
            ? [...new Intl.Segmenter(undefined, { granularity: 'grapheme' }).segment(word)].map((part) => part.segment)
            : Array.from(word);
        for (const segment of segments) {
            if (line && context.measureText(line + segment).width > maxWidth) {
                lines.push(line);
                line = '';
            }
            line += segment;
        }
    }

    if (line) lines.push(line);
    return lines;
}

function renderInvitation() {
    if (!canvas || !source || !context || !downloadButton || !status || !attendeeName) {
        if (status) status.textContent = 'The personalized invitation could not be prepared.';
        return;
    }

    canvas.width = source.naturalWidth;
    canvas.height = source.naturalHeight;
    context.drawImage(source, 0, 0);

    const scale = canvas.width / 908;
    const nameX = 75 * scale;
    const nameY = 565 * scale;
    const maxWidth = 258 * scale;
    const maxHeight = 150 * scale;
    const direction = /[\u0590-\u08ff]/u.test(attendeeName) ? 'rtl' : 'ltr';
    let fontSize = 34 * scale;
    let lines;

    context.fillStyle = '#64121f';
    context.textAlign = 'left';
    context.textBaseline = 'top';
    context.direction = direction;

    do {
        context.font = `600 ${fontSize}px Arial, sans-serif`;
        lines = wrapName(attendeeName, maxWidth);
        if (lines.length * fontSize * 1.16 <= maxHeight || fontSize <= 12 * scale) break;
        fontSize -= scale;
    } while (fontSize > 0);

    const lineHeight = fontSize * 1.16;
    lines.forEach((line, index) => {
        context.fillText(line, nameX, nameY + index * lineHeight, maxWidth);
    });

    downloadButton.disabled = false;
    status.textContent = 'Your invitation is ready to download.';
}

if (source?.complete && source.naturalWidth > 0) {
    renderInvitation();
} else {
    source?.addEventListener('load', renderInvitation, { once: true });
    source?.addEventListener('error', () => {
        if (status) status.textContent = 'The personalized invitation could not be loaded. Please refresh and try again.';
    }, { once: true });
}

downloadButton?.addEventListener('click', () => {
    if (!canvas || downloadButton.disabled) return;

    canvas.toBlob((imageBlob) => {
        if (!imageBlob) {
            if (status) status.textContent = 'The invitation download could not be created. Please try again.';
            return;
        }

        const imageUrl = URL.createObjectURL(imageBlob);
        const downloadLink = document.createElement('a');
        downloadLink.href = imageUrl;
        downloadLink.download = 'WHS-Egypt-2026-VIP-Invitation.jpeg';
        downloadLink.click();
        window.setTimeout(() => URL.revokeObjectURL(imageUrl), 1000);
    }, 'image/jpeg', 0.96);
});