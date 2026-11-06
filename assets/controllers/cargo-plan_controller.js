import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = { plan: Object };

    planValueChanged() {
        window.dispatchEvent(new CustomEvent('cargo:plan', { detail: this.planValue }));
    }
}
