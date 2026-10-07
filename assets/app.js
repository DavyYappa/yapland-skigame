import './styles/tokens.css';
import './styles/app.css';

// Copy buttons in the admin: data-copy copies a field's text, data-copy-rich
// copies rendered HTML so it pastes as a formatted signature in Outlook or Gmail.
document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-copy], [data-copy-rich]');
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
        } else {
            const field = document.querySelector(button.dataset.copy);
            await navigator.clipboard.writeText(field.value);
        }
        button.textContent = 'Gekopieerd';
    } catch {
        const field = document.querySelector(button.dataset.copy || '#signature-html');
        field.select();
        button.textContent = 'Geselecteerd, druk Cmd+C';
    }
    setTimeout(() => (button.textContent = label), 2000);
});
