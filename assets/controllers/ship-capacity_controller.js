import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['capacity'];

    fill({ detail: { option } }) {
        if (!option?.capacity || String(option.capacity) === this.capacityTarget.value) {
            return;
        }

        this.capacityTarget.value = option.capacity;
        // Brief flash so the user notices the capacity was filled in for them (it stays editable).
        this.capacityTarget.classList.remove('is-autofilled');
        void this.capacityTarget.offsetWidth;
        this.capacityTarget.classList.add('is-autofilled');
    }
}
