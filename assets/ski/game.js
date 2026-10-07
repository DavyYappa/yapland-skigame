import '../styles/tokens.css';
import './game.css';

/*
 * Placeholder ski game for the PoC: the real game is Jeroen's out-of-office.
 * Everything client-specific comes from data-config, set by Twig.
 */
const root = document.getElementById('ski-game');
const config = JSON.parse(root.dataset.config);
const canvas = root.querySelector('canvas');
const ctx = canvas.getContext('2d');
const hud = root.querySelector('.hud');
const hudScore = root.querySelector('.hud-score');
const startPanel = root.querySelector('.start');
const overPanel = root.querySelector('.over');
const finalScore = root.querySelector('.final-score');
const statusLine = root.querySelector('.status');
const board = root.querySelector('.board');

// Must stay under SkiController::MAX_POINTS_PER_SECOND (40)
const PX_PER_POINT = 25;
const MAX_SPEED = 660; // px/s → 26.4 points/s from distance
const GATE_BONUS = 10;
const GATE_SPACING = 640; // at most ~1 gate a second at full speed

let width = 0;
let height = 0;
let state = 'idle';
let skier;
let obstacles;
let flakes;
let distance;
let bonus;
let speed;
let nextObstacleAt;
let nextGateAt;
let steer = 0;
let last = 0;

function resize() {
    const dpr = window.devicePixelRatio || 1;
    width = root.clientWidth;
    height = root.clientHeight;
    canvas.width = Math.round(width * dpr);
    canvas.height = Math.round(height * dpr);
    canvas.style.width = width + 'px';
    canvas.style.height = height + 'px';
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    if (state !== 'playing') drawIdle();
}

function reset() {
    skier = { x: width / 2, y: height * 0.28, vx: 0 };
    obstacles = [];
    distance = 0;
    bonus = 0;
    speed = 240;
    nextObstacleAt = 120;
    nextGateAt = 400;
    flakes = Array.from({ length: 60 }, () => ({
        x: Math.random() * width,
        y: Math.random() * height,
        r: Math.random() * 1.8 + 0.6,
    }));
    // a few trees on screen from the start, away from the skier's line
    for (let y = height * 0.5; y < height; y += 90) spawnTree(y, true);
}

function points() {
    return Math.floor(distance / PX_PER_POINT) + bonus;
}

function spawnTree(y, avoidCenter = false) {
    let x;
    do {
        x = 20 + Math.random() * (width - 40);
    } while (avoidCenter && Math.abs(x - width / 2) < 70);
    const size = 18 + Math.random() * 14;
    obstacles.push({ type: Math.random() < 0.15 ? 'rock' : 'tree', x, y, size });
}

function spawnGate(y) {
    const gap = Math.max(110, 170 - distance / 400);
    const center = gap / 2 + 20 + Math.random() * (width - gap - 40);
    obstacles.push({ type: 'gate', x: center, y, gap, passed: false });
}

function update(dt) {
    speed = Math.min(MAX_SPEED, speed + 14 * dt);
    const dy = speed * dt;
    distance += dy;

    skier.vx += (steer * 900 - skier.vx * 4) * dt;
    skier.x = Math.max(14, Math.min(width - 14, skier.x + skier.vx * dt));

    for (const o of obstacles) o.y -= dy;
    obstacles = obstacles.filter((o) => o.y > -60);

    nextObstacleAt -= dy;
    if (nextObstacleAt <= 0) {
        const density = Math.min(3, 1 + Math.floor(width / 260));
        for (let i = 0; i < density; i++) spawnTree(height + 40 + Math.random() * 60);
        nextObstacleAt = Math.max(70, 150 - distance / 300);
    }
    nextGateAt -= dy;
    if (nextGateAt <= 0) {
        spawnGate(height + 60);
        nextGateAt = GATE_SPACING + Math.random() * 300;
    }

    for (const f of flakes) {
        f.y -= dy * 0.35 + 20 * dt;
        if (f.y < -4) {
            f.y = height + 4;
            f.x = Math.random() * width;
        }
    }

    for (const o of obstacles) {
        if (o.type === 'gate') {
            if (!o.passed && o.y < skier.y) {
                o.passed = true;
                if (Math.abs(skier.x - o.x) < o.gap / 2 - 6) {
                    bonus += GATE_BONUS;
                    o.hit = true;
                }
            }
            for (const side of [-1, 1]) {
                if (hits(o.x + (side * o.gap) / 2, o.y + 6, 6)) return crash();
            }
        } else if (hits(o.x, o.y + o.size * 0.35, o.size * 0.42)) {
            return crash();
        }
    }

    hudScore.textContent = points();
}

function hits(x, y, r) {
    const dx = skier.x - x;
    const dy = skier.y + 6 - y;
    return dx * dx + dy * dy < (r + 9) * (r + 9);
}

function draw() {
    ctx.fillStyle = '#F7FAFD';
    ctx.fillRect(0, 0, width, height);

    for (const o of obstacles || []) {
        if (o.type === 'tree') drawTree(o);
        else if (o.type === 'rock') drawRock(o);
        else drawGate(o);
    }
    if (skier && state !== 'idle') drawSkier();

    ctx.fillStyle = 'rgba(160,185,210,.55)';
    for (const f of flakes || []) {
        ctx.beginPath();
        ctx.arc(f.x, f.y, f.r, 0, Math.PI * 2);
        ctx.fill();
    }
}

function drawTree({ x, y, size }) {
    ctx.fillStyle = 'rgba(0,0,0,.08)';
    ctx.beginPath();
    ctx.ellipse(x + 4, y + size * 0.55, size * 0.5, size * 0.18, 0, 0, Math.PI * 2);
    ctx.fill();
    ctx.fillStyle = '#6B4A2F';
    ctx.fillRect(x - 3, y + size * 0.3, 6, size * 0.3);
    ctx.fillStyle = '#2F6444'; // Yapland green
    for (let i = 0; i < 3; i++) {
        const w = size * (0.95 - i * 0.22);
        const top = y - size * 0.75 + i * size * 0.3;
        ctx.beginPath();
        ctx.moveTo(x, top - size * 0.25);
        ctx.lineTo(x - w / 2, top + size * 0.45);
        ctx.lineTo(x + w / 2, top + size * 0.45);
        ctx.closePath();
        ctx.fill();
    }
    ctx.fillStyle = 'rgba(255,255,255,.85)';
    ctx.beginPath();
    ctx.moveTo(x, y - size);
    ctx.lineTo(x - size * 0.16, y - size * 0.72);
    ctx.lineTo(x + size * 0.16, y - size * 0.72);
    ctx.closePath();
    ctx.fill();
}

function drawRock({ x, y, size }) {
    ctx.fillStyle = '#8D99A6';
    ctx.beginPath();
    ctx.ellipse(x, y + size * 0.3, size * 0.5, size * 0.3, 0, 0, Math.PI * 2);
    ctx.fill();
    ctx.fillStyle = '#FFFFFF';
    ctx.beginPath();
    ctx.ellipse(x - 2, y + size * 0.18, size * 0.3, size * 0.12, 0, 0, Math.PI * 2);
    ctx.fill();
}

function drawGate(o) {
    for (const side of [-1, 1]) {
        const fx = o.x + (side * o.gap) / 2;
        ctx.strokeStyle = '#333';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.moveTo(fx, o.y + 8);
        ctx.lineTo(fx, o.y - 18);
        ctx.stroke();
        ctx.fillStyle = o.hit ? config.colors.accent : config.colors.secondary;
        ctx.beginPath();
        ctx.moveTo(fx, o.y - 18);
        ctx.lineTo(fx - side * 14, o.y - 12);
        ctx.lineTo(fx, o.y - 6);
        ctx.closePath();
        ctx.fill();
    }
}

function drawSkier() {
    const tilt = Math.max(-0.5, Math.min(0.5, skier.vx / 600));
    ctx.save();
    ctx.translate(skier.x, skier.y);
    ctx.rotate(-tilt);
    // skis
    ctx.fillStyle = '#1B1B1B';
    ctx.fillRect(-9, -2, 4, 26);
    ctx.fillRect(5, -2, 4, 26);
    // body
    ctx.fillStyle = config.colors.primary;
    ctx.beginPath();
    ctx.roundRect(-9, -14, 18, 20, 6);
    ctx.fill();
    // poles
    ctx.strokeStyle = '#555';
    ctx.lineWidth = 1.5;
    ctx.beginPath();
    ctx.moveTo(-10, -6);
    ctx.lineTo(-14, 16);
    ctx.moveTo(10, -6);
    ctx.lineTo(14, 16);
    ctx.stroke();
    // head + hat
    ctx.fillStyle = '#F2C9A8';
    ctx.beginPath();
    ctx.arc(0, -18, 6, 0, Math.PI * 2);
    ctx.fill();
    ctx.fillStyle = config.colors.accent;
    ctx.beginPath();
    ctx.arc(0, -20, 6, Math.PI, 0);
    ctx.fill();
    ctx.beginPath();
    ctx.arc(0, -27, 2.5, 0, Math.PI * 2);
    ctx.fill();
    ctx.restore();
}

function drawIdle() {
    if (!skier) reset();
    draw();
}

function loop(now) {
    if (state !== 'playing') return;
    const dt = Math.min(0.05, (now - last) / 1000);
    last = now;
    update(dt);
    draw();
    if (state === 'playing') requestAnimationFrame(loop);
}

function start() {
    reset();
    state = 'playing';
    startPanel.hidden = true;
    overPanel.hidden = true;
    hud.hidden = false;
    hudScore.textContent = '0';
    last = performance.now();
    requestAnimationFrame(loop);
}

async function crash() {
    state = 'over';
    draw();
    const score = points();
    hud.hidden = true;
    finalScore.textContent = score;
    overPanel.hidden = false;
    overPanel.querySelector('.play').focus();

    if (!config.scoreUrl) {
        statusLine.textContent = 'Voorbeeld: deze score wordt niet bewaard.';
        renderBoard(config.top);
        return;
    }

    statusLine.textContent = 'Score doorsturen…';
    try {
        const response = await fetch(config.scoreUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ points: score }),
        });
        const data = await response.json();
        if (!response.ok) {
            statusLine.textContent = data.error || 'Je score kon niet bewaard worden.';
            return;
        }
        config.top = data.top;
        statusLine.textContent = '';
        renderBoard(data.top);
    } catch {
        statusLine.textContent = 'Geen verbinding. Je score kon niet bewaard worden.';
    }
}

function renderBoard(top) {
    board.replaceChildren(
        ...top.map((row) => {
            const li = document.createElement('li');
            if (row.me) li.className = 'me';
            const name = document.createElement('span');
            name.textContent = row.name;
            const pts = document.createElement('b');
            pts.textContent = row.points;
            li.append(name, pts);
            return li;
        }),
    );
    board.hidden = top.length === 0;
}

// controls
const keys = new Set();
function updateSteer() {
    steer = (keys.has('right') ? 1 : 0) - (keys.has('left') ? 1 : 0);
}
window.addEventListener('keydown', (e) => {
    if (['ArrowLeft', 'a', 'q'].includes(e.key)) keys.add('left');
    else if (['ArrowRight', 'd'].includes(e.key)) keys.add('right');
    else if ((e.key === 'Enter' || e.key === ' ') && state !== 'playing' && document.activeElement?.tagName !== 'BUTTON') start();
    else return;
    e.preventDefault();
    updateSteer();
});
window.addEventListener('keyup', (e) => {
    if (['ArrowLeft', 'a', 'q'].includes(e.key)) keys.delete('left');
    if (['ArrowRight', 'd'].includes(e.key)) keys.delete('right');
    updateSteer();
});
canvas.addEventListener('pointerdown', (e) => {
    keys.clear();
    keys.add(e.clientX < width / 2 ? 'left' : 'right');
    updateSteer();
});
for (const type of ['pointerup', 'pointercancel', 'pointerleave']) {
    canvas.addEventListener(type, () => {
        keys.clear();
        updateSteer();
    });
}
for (const button of root.querySelectorAll('.play')) button.addEventListener('click', start);

window.addEventListener('resize', resize);
resize();
