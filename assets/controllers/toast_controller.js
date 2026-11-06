import { Controller } from '@hotwired/stimulus';

/* One toast: dismisses itself after `delay` ms (paused while hovered or focused). */
export default class extends Controller {
    static targets = ['progress'];
    static values = { delay: { type: Number, default: 6000 } };

    connect() {
        this.remaining = this.delayValue;
        this.element.style.setProperty('--hm-toast-delay', `${this.delayValue}ms`);
        this.pause = this.pause.bind(this);
        this.resume = this.resume.bind(this);
        ['mouseenter', 'focusin'].forEach((type) => this.element.addEventListener(type, this.pause));
        ['mouseleave', 'focusout'].forEach((type) => this.element.addEventListener(type, this.resume));
        this.resume();
    }

    disconnect() {
        clearTimeout(this.timer);
    }

    pause() {
        if (!this.timer) return;
        clearTimeout(this.timer);
        this.timer = null;
        this.remaining -= Date.now() - this.startedAt;
        this.element.classList.add('is-paused');
    }

    resume() {
        if (this.timer || this.element.contains(document.activeElement) || this.element.matches(':hover')) return;
        this.startedAt = Date.now();
        this.timer = setTimeout(() => this.dismiss(), Math.max(this.remaining, 0));
        this.element.classList.remove('is-paused');
    }

    dismiss() {
        clearTimeout(this.timer);
        this.element.classList.add('is-leaving');
        Promise.all(this.element.getAnimations().map((animation) => animation.finished))
            .catch(() => {})
            .then(() => this.element.remove());
    }
}
