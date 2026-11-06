import { Controller } from '@hotwired/stimulus';

const STORAGE_KEY = 'hm-theme';
const CHOICES = ['light', 'dark', 'auto'];

/*
 * Light / dark / auto (system) theme. The inline script in base.html.twig applies it before paint.
 * Emits `theme:change` on window with detail {theme: 'light'|'dark'} (resolved theme) on connect and on each change.
 */
export default class extends Controller {
    static targets = ['option'];

    connect() {
        this.media = matchMedia('(prefers-color-scheme: dark)');
        this.onSystemChange = () => this.apply(false);
        this.media.addEventListener('change', this.onSystemChange);
        this.apply(false, true);
    }

    disconnect() {
        this.media.removeEventListener('change', this.onSystemChange);
    }

    choose({ params: { choice } }) {
        if (!CHOICES.includes(choice)) return;
        try { localStorage.setItem(STORAGE_KEY, choice); } catch (e) {}
        document.documentElement.dataset.themeChoice = choice;
        this.apply(true);
    }

    apply(animate, force = false) {
        const root = document.documentElement;
        const choice = CHOICES.includes(root.dataset.themeChoice) ? root.dataset.themeChoice : 'auto';
        const theme = choice === 'dark' || (choice === 'auto' && this.media.matches) ? 'dark' : 'light';

        this.optionTargets.forEach((option) => option.setAttribute('aria-checked', String(option.dataset.themeOption === choice)));
        if (theme === root.dataset.bsTheme && !force) return;

        if (animate) {
            root.classList.add('hm-theme-transition');
            clearTimeout(this.transitionTimer);
            this.transitionTimer = setTimeout(() => root.classList.remove('hm-theme-transition'), 400);
        }
        root.dataset.bsTheme = theme;
        window.dispatchEvent(new CustomEvent('theme:change', { detail: { theme } }));
    }
}
