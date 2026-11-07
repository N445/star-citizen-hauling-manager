import { Controller } from '@hotwired/stimulus';
import TomSelect from 'tom-select';

const DELIMITER = ',';

// Language-neutral "free entry" row (Tom Select's default text is English only).
const createOption = (data, escape) => `<div class="create"><span aria-hidden="true">＋</span> <strong>${escape(data.input)}</strong>…</div>`;

export default class extends Controller {
    static values = { options: Array, optgroups: Array, multiple: Boolean, badges: Boolean, fallbackColor: String };

    connect() {
        this.tomSelect = new TomSelect(this.element, {
            options: [...this.currentOptions(), ...this.suggestedOptions()],
            optgroups: this.optgroupsValue,
            optgroupField: 'optgroup',
            lockOptgroupOrder: true,
            sortField: [{ field: 'text', direction: 'asc' }],
            maxItems: this.multipleValue ? null : 1,
            create: true,
            createOnBlur: true,
            persist: false,
            delimiter: DELIMITER,
            // Hors de la carte : sinon les cartes animées et le panneau collant de la soute passent par-dessus la liste.
            dropdownParent: 'body',
            onChange: (value) => this.changed(value),
            onItemAdd: () => this.clearSearch(),
            onDropdownOpen: () => this.reveal(),
            render: this.badgesValue ? this.badgeRenderers() : this.plainRenderers(),
        });
    }

    // Touch screens: bring the field to the top so the list (and the keyboard) do not hide it.
    reveal() {
        if (matchMedia('(pointer: coarse)').matches) {
            this.tomSelect.wrapper.scrollIntoView({ block: 'start', behavior: 'smooth' });
        }
    }

    clearSearch() {
        this.tomSelect.setTextboxValue('');
        this.tomSelect.refreshOptions(false);
    }

    // Tom Select ne fait pas remonter de `change` : sans lui, ni le Live Component ni les autres contrôleurs ne voient la saisie.
    changed(value) {
        this.element.dispatchEvent(new Event('change', { bubbles: true }));
        this.dispatch('change', { detail: { option: this.tomSelect.options[value] } });
    }

    badgeRenderers() {
        const badge = (data, escape) => `<div class="commodity-badge" style="--commodity-color: ${escape(data.color ?? this.fallbackColorValue)}">${escape(data.text)}</div>`;

        return { item: badge, option: (data, escape) => `<div class="option">${badge(data, escape)}</div>`, option_create: createOption };
    }

    plainRenderers() {
        const moon = (data, escape) => (data.moon ? ` <small class="text-body-secondary">${escape(data.moon)}</small>` : '');

        return { option: (data, escape) => `<div>${escape(data.text)}${moon(data, escape)}</div>`, option_create: createOption };
    }

    disconnect() {
        this.tomSelect.destroy();
    }

    // Tom Select écarte au démarrage toute valeur absente des options : une saisie libre disparaîtrait au réaffichage.
    currentOptions() {
        return this.element.value.split(DELIMITER).filter(Boolean).map((value) => ({ value, text: value }));
    }

    suggestedOptions() {
        return this.optionsValue.map((option) => (typeof option === 'string' ? { value: option, text: option } : option));
    }
}
