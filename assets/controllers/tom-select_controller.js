import { Controller } from '@hotwired/stimulus';
import TomSelect from 'tom-select';

const DELIMITER = ',';

export default class extends Controller {
    static values = { options: Array, optgroups: Array, multiple: Boolean, badges: Boolean, fallbackColor: String };

    connect() {
        this.tomSelect = new TomSelect(this.element, {
            options: [...this.currentOptions(), ...this.suggestedOptions()],
            optgroups: this.optgroupsValue,
            optgroupField: 'optgroup',
            lockOptgroupOrder: true,
            maxItems: this.multipleValue ? null : 1,
            create: true,
            createOnBlur: true,
            persist: false,
            delimiter: DELIMITER,
            onChange: (value) => this.changed(value),
            onItemAdd: () => this.clearSearch(),
            render: this.badgesValue ? this.badgeRenderers() : this.plainRenderers(),
        });
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

        return { item: badge, option: (data, escape) => `<div class="option">${badge(data, escape)}</div>` };
    }

    plainRenderers() {
        const moon = (data, escape) => (data.moon ? ` <small class="text-body-secondary">${escape(data.moon)}</small>` : '');

        return { option: (data, escape) => `<div>${escape(data.text)}${moon(data, escape)}</div>` };
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
