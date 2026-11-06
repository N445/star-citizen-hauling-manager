import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['capacity'];

    fill({ detail: { option } }) {
        if (!option?.capacity) {
            return;
        }

        this.capacityTarget.value = option.capacity;
    }
}
