import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        this.key = null;
        this.onPointerOver = (event) => this.hover(event.target);
        this.onHighlight = (event) => this.apply(event.detail.key);
        this.element.addEventListener('pointerover', this.onPointerOver);
        window.addEventListener('cargo:highlight', this.onHighlight);
    }

    disconnect() {
        this.element.removeEventListener('pointerover', this.onPointerOver);
        window.removeEventListener('cargo:highlight', this.onHighlight);
    }

    // La 3D gère elle-même le survol de ses caisses : un survol du canvas ne doit pas effacer sa surbrillance.
    hover(target) {
        const key = target.closest?.('[data-highlight-key]')?.dataset.highlightKey ?? null;

        if (key === this.key || target.closest?.('canvas')) {
            return;
        }

        window.dispatchEvent(new CustomEvent('cargo:highlight', { detail: { key } }));
    }

    apply(key) {
        this.key = key;
        document.querySelectorAll('.is-highlighted').forEach((element) => element.classList.remove('is-highlighted'));

        if (key === null) {
            return;
        }

        document.querySelectorAll(`[data-highlight-tokens~="${CSS.escape(key)}"]`).forEach((element) => element.classList.add('is-highlighted'));
    }
}
