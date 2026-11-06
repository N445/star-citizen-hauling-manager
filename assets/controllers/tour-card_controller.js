import { Controller } from '@hotwired/stimulus';

// Récapitulatif de la tournée, ou son formulaire après un clic sur le crayon.
export default class extends Controller {
    static targets = ['summary', 'form', 'editButton'];
    static values = { editing: Boolean };

    edit() {
        this.editingValue = true;
        this.editingValueChanged(true);
        this.formTarget.querySelector('input:not([type=hidden]), select')?.focus();
    }

    cancel() {
        if (this.changed) {
            window.location.assign(window.location.pathname);

            return;
        }

        this.editingValue = false;
        this.editingValueChanged(false);
        this.editButtonTarget.focus();
    }

    dirty() {
        this.changed = true;
    }

    editingValueChanged(editing) {
        if (!this.hasSummaryTarget) {
            return;
        }

        this.summaryTarget.hidden = editing;
        this.formTarget.hidden = !editing;
        this.editButtonTarget.hidden = editing;
        const pane = editing ? this.formTarget : this.summaryTarget;
        pane.classList.remove('tour-card__pane--enter');
        void pane.offsetWidth;
        pane.classList.add('tour-card__pane--enter');
    }
}
