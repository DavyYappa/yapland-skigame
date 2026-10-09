// Copy buttons, shared by the admin and the claim page:
// - data-copy="#field" copies a field's value
// - data-copy-text="…" copies that text
// - data-copy-rich=".selector" copies rendered HTML, so it pastes as a formatted signature
document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-copy], [data-copy-text], [data-copy-rich]');
    if (!button) return;

    const label = button.textContent;
    try {
        if (button.dataset.copyRich) {
            const html = document.querySelector(button.dataset.copyRich).innerHTML.trim();
            await navigator.clipboard.write([
                new ClipboardItem({
                    'text/html': new Blob([html], { type: 'text/html' }),
                    'text/plain': new Blob([html], { type: 'text/plain' }),
                }),
            ]);
        } else if (button.dataset.copyText) {
            await navigator.clipboard.writeText(button.dataset.copyText);
        } else {
            await navigator.clipboard.writeText(document.querySelector(button.dataset.copy).value);
        }
        button.textContent = 'Gekopieerd';
    } catch {
        const field = document.querySelector(button.dataset.copy || '#signature-html');
        if (field) {
            field.closest('details')?.setAttribute('open', '');
            field.select();
            button.textContent = 'Geselecteerd, druk Cmd+C';
        }
    }
    setTimeout(() => (button.textContent = label), 2000);
});
