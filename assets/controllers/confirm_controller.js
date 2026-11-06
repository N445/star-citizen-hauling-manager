import { Controller } from '@hotwired/stimulus';

// Destructive button in two clicks: the first one arms it (new label), the second one lets the next actions run.
export default class extends Controller {
    static values = { label: String, timeout: { type: Number, default: 3000 } };

    connect() {
        this.original = { html: this.element.innerHTML, aria: this.element.getAttribute('aria-label') };
    }

    ask(event) {
        if (this.element.classList.contains('is-armed')) {
            this.disarm();

            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();
        this.element.textContent = this.labelValue;
        this.element.setAttribute('aria-label', this.labelValue);
        this.element.classList.add('is-armed');
        this.timer = setTimeout(() => this.disarm(), this.timeoutValue);
    }

    disconnect() {
        clearTimeout(this.timer);
    }

    disarm() {
        clearTimeout(this.timer);
        this.element.innerHTML = this.original.html;
        this.element.setAttribute('aria-label', this.original.aria ?? '');
        this.element.classList.remove('is-armed');
    }
}
