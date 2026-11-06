import { Controller } from '@hotwired/stimulus';
import TomSelect from 'tom-select';

let catalog = null;

// The option lists are static: they are embedded once in the page instead of being re-rendered by every Live request.
function loadCatalog() {
    if (catalog === null) {
        try {
            catalog = JSON.parse(document.getElementById('sch-catalog')?.textContent ?? '{}');
        } catch {
            catalog = {};
        }
    }

    return catalog;
}

const escape = (value) => String(value).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

export default class extends Controller {
    static values = { create: Boolean, placeholder: String, current: String, source: String, empty: String };

    connect() {
        const options = [...(loadCatalog()[this.sourceValue] ?? [])];
        if (this.hasEmptyValue) {
            options.unshift({ value: '', text: this.emptyValue });
        }
        this.select = new TomSelect(this.element, {
            create: this.createValue,
            addPrecedence: true,
            createOnBlur: false,
            maxOptions: null,
            openOnFocus: true,
            allowEmptyOption: this.hasEmptyValue,
            valueField: 'value',
            labelField: 'text',
            searchField: ['text', 'value'],
            options,
            placeholder: this.placeholderValue || undefined,
            render: {
                option: (data) => `<div>${escape(data.text)}${data.scu ? ` <span class="opt-meta">${data.scu} SCU</span>` : ''}</div>`,
                item: (data) => `<div>${escape(data.text)}</div>`,
            },
        });
        // destroy() reverts the <select> to its initial markup: re-apply the server value when a morph re-attaches the element.
        this.syncCurrent();
        // Tom Select fires non-bubbling events on the <select>; Live Component listens through delegation.
        this.select.on('change', (value) => {
            this.element.value = value;
            this.element.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }

    currentValueChanged() {
        this.syncCurrent();
    }

    syncCurrent() {
        if (!this.select || !this.hasCurrentValue || this.select.getValue() === this.currentValue) {
            return;
        }
        if (!(this.currentValue in this.select.options)) {
            this.select.addOption({ value: this.currentValue, text: this.currentValue });
        }
        this.select.setValue(this.currentValue, true);
    }

    disconnect() {
        this.select?.destroy();
        this.select = null;
    }
}
