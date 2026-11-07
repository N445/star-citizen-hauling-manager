import { Controller } from '@hotwired/stimulus';
import { track } from '../stats.js';

const INTERACTIVE = 'button, a, input, select, textarea, label, summary, .route-stop-handle, .ts-wrapper';

// Surbrillance croisée. Survol : éphémère. Clic sur un élément (ou une caisse 3D, événement `cargo:lock`) : verrouillée,
// elle ne suit plus le survol jusqu'à un second clic sur le même élément, un clic dans le vide ou Échap.
export default class extends Controller {
    connect() {
        this.key = null;
        this.lockedKey = null;
        this.onPointerOver = (event) => this.hover(event.target);
        this.onHighlight = (event) => this.apply(event.detail.key, event.detail.locked === true);
        this.onLock = (event) => (event.detail.key === null ? this.unlock() : this.lock(event.detail.key));
        this.onClick = (event) => this.click(event.target);
        this.onKey = (event) => event.key === 'Escape' && this.lockedKey !== null && this.unlock();
        this.element.addEventListener('pointerover', this.onPointerOver);
        this.element.addEventListener('focusin', this.onPointerOver);
        this.element.addEventListener('click', this.onClick);
        document.addEventListener('keydown', this.onKey);
        window.addEventListener('cargo:highlight', this.onHighlight);
        window.addEventListener('cargo:lock', this.onLock);
        this.observer = new MutationObserver(() => this.key !== null && this.refresh());
        this.observer.observe(this.element, { childList: true, subtree: true });
    }

    disconnect() {
        this.element.removeEventListener('pointerover', this.onPointerOver);
        this.element.removeEventListener('focusin', this.onPointerOver);
        this.element.removeEventListener('click', this.onClick);
        document.removeEventListener('keydown', this.onKey);
        window.removeEventListener('cargo:highlight', this.onHighlight);
        window.removeEventListener('cargo:lock', this.onLock);
        this.observer.disconnect();
        cancelAnimationFrame(this.frame);
    }

    // La 3D gère elle-même le survol de ses caisses : un survol du canvas ne doit pas effacer sa surbrillance.
    hover(target) {
        const key = target.closest?.('[data-highlight-key]')?.dataset.highlightKey ?? null;

        if (this.lockedKey !== null || key === this.key || target.closest?.('canvas')) {
            return;
        }

        this.dispatch(key, false);
    }

    click(target) {
        if (target.closest?.('canvas, .cargo-stage') || target.closest?.(INTERACTIVE)) {
            return;
        }

        const key = target.closest?.('[data-highlight-key]')?.dataset.highlightKey ?? null;

        if (key === null) {
            this.unlock();
        } else if (key === this.lockedKey) {
            this.unlock(key);
        } else {
            this.lock(key);
        }
    }

    lock(key) {
        this.lockedKey = key;
        track('highlight-lock');
        this.dispatch(key, true);
    }

    // Garde la surbrillance du survol quand le pointeur est encore sur l'élément déverrouillé.
    unlock(keep = null) {
        if (this.lockedKey === null) {
            return;
        }

        this.lockedKey = null;
        track('highlight-unlock');
        this.dispatch(keep, false);
    }

    dispatch(key, locked) {
        window.dispatchEvent(new CustomEvent('cargo:highlight', { detail: { key, locked } }));
    }

    apply(key, locked) {
        this.key = key;
        this.lockedKey = locked ? key : null;
        this.refresh();
    }

    // Les rendus Live Component remplacent le DOM : la surbrillance (surtout verrouillée) est reposée ensuite.
    refresh() {
        cancelAnimationFrame(this.frame);
        this.frame = requestAnimationFrame(() => this.paint());
    }

    paint() {
        this.observer.disconnect();
        document.querySelectorAll('.is-highlighted, .is-highlight-locked').forEach((element) => element.classList.remove('is-highlighted', 'is-highlight-locked'));

        if (this.key !== null) {
            document.querySelectorAll(`[data-highlight-tokens~="${CSS.escape(this.key)}"]`).forEach((element) => element.classList.add('is-highlighted'));
        }

        if (this.lockedKey !== null) {
            document.querySelectorAll(`[data-highlight-key="${CSS.escape(this.lockedKey)}"]`).forEach((element) => element.classList.add('is-highlight-locked'));
        }

        this.observer.observe(this.element, { childList: true, subtree: true });
    }
}
