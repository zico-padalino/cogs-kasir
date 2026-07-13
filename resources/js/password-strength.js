function scorePassword(value) {
    const password = String(value || '');
    if (! password) {
        return { score: 0, label: '', tone: 'idle', checks: {} };
    }

    const checks = {
        length: password.length >= 8,
        lower: /[a-z]/.test(password),
        upper: /[A-Z]/.test(password),
        number: /\d/.test(password),
        symbol: /[^A-Za-z0-9]/.test(password),
    };
    checks.mixed = checks.lower && checks.upper;

    let score = 0;
    if (checks.length) score += 1;
    if (password.length >= 12) score += 1;
    if (checks.lower && checks.upper) score += 1;
    if (checks.number) score += 1;
    if (checks.symbol) score += 1;

    if (score <= 1) {
        return { score: 1, label: 'Lemah', tone: 'weak', checks };
    }
    if (score === 2) {
        return { score: 2, label: 'Kurang', tone: 'fair', checks };
    }
    if (score === 3) {
        return { score: 3, label: 'Cukup', tone: 'good', checks };
    }
    if (score === 4) {
        return { score: 4, label: 'Kuat', tone: 'strong', checks };
    }

    return { score: 5, label: 'Sangat kuat', tone: 'very-strong', checks };
}

function renderMeter(root, result) {
    const bars = root.querySelectorAll('[data-strength-bar]');
    const label = root.querySelector('[data-strength-label]');
    const tips = root.querySelectorAll('[data-strength-tip]');

    bars.forEach((bar, index) => {
        const active = index < result.score;
        bar.dataset.active = active ? '1' : '0';
        bar.dataset.tone = active ? result.tone : 'idle';
    });

    if (label) {
        label.textContent = result.label || 'Masukkan password baru';
        label.dataset.tone = result.tone || 'idle';
    }

    tips.forEach((tip) => {
        const key = tip.dataset.strengthTip;
        const ok = Boolean(result.checks?.[key]);
        tip.dataset.ok = ok ? '1' : '0';
    });
}

export function initPasswordStrength() {
    document.querySelectorAll('[data-password-strength]').forEach((input) => {
        const root = document.querySelector(input.dataset.passwordStrength);
        if (! root) {
            return;
        }

        const update = () => renderMeter(root, scorePassword(input.value));
        input.addEventListener('input', update);
        input.addEventListener('change', update);
        update();
    });
}

document.addEventListener('DOMContentLoaded', initPasswordStrength);
