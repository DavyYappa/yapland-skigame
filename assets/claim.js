import './styles/tokens.css';
import './styles/app.css';
import './styles/claim.css';
import './copy.js';

// Live preview on the claim form: logo, colors and message as the start screen of the game
const form = document.getElementById('claim-form');
const preview = document.getElementById('mini-game');

if (form && preview) {
    const logo = document.getElementById('mini-logo');
    const placeholder = document.getElementById('mini-logo-placeholder');
    const message = document.getElementById('mini-message');
    const field = (name) => form.querySelector(`[name="claim[${name}]"]`);
    const fallbackMessage = message.textContent;

    // Same rule as Client::getTextOnPrimary(): black or white, whichever reads best
    const textOn = (hex) => {
        const [r, g, b] = [1, 3, 5].map((i) => {
            const c = parseInt(hex.slice(i, i + 2), 16) / 255;
            return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
        });
        return 0.2126 * r + 0.7152 * g + 0.0722 * b > 0.179 ? '#111111' : '#FFFFFF';
    };

    const update = () => {
        const primary = field('primaryColor').value;
        preview.style.setProperty('--primary', primary);
        preview.style.setProperty('--on-primary', textOn(primary));
        preview.style.setProperty('--secondary', field('secondaryColor').value);
        preview.style.setProperty('--accent', field('accentColor').value);
        message.textContent = field('message').value.trim() || fallbackMessage;
    };

    field('logo').addEventListener('change', (event) => {
        const file = event.target.files[0];
        if (!file || !file.type.startsWith('image/')) return;
        const reader = new FileReader();
        reader.onload = () => {
            logo.src = reader.result;
            logo.hidden = false;
            placeholder.hidden = true;
        };
        reader.readAsDataURL(file);
    });

    form.addEventListener('input', update);
    update();
}
