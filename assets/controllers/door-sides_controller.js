import { Controller } from '@hotwired/stimulus';

// Fait suivre en direct à la soute 3D les portes cochées, avant même l'enregistrement.
export default class extends Controller {
    static targets = ['input'];

    connect() {
        this.emit();
    }

    fill({ detail: { option } }) {
        if (!option?.doorSides?.length) {
            return;
        }

        this.inputTargets.forEach((input) => {
            input.checked = option.doorSides.includes(input.value);
        });
        this.emit();
    }

    emit() {
        const doors = this.inputTargets.filter((input) => input.checked).map((input) => input.value);
        window.dispatchEvent(new CustomEvent('cargo:doors', { detail: { doors } }));
    }
}
