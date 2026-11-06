import { Controller } from '@hotwired/stimulus';

// Brings the mission form into view when a mission is opened for editing (the list may be far below).
export default class extends Controller {
    static values = { active: Boolean, version: Number };

    versionValueChanged(version, previous) {
        if (!this.activeValue || previous === undefined) {
            return;
        }

        const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
        this.element.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'nearest' });
        this.element.querySelector('input:not([type=hidden]), select')?.focus({ preventScroll: true });
    }
}
