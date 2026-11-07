import './styles/admin-location.css';
import './location-tree.js';
import Pickr from '@simonwep/pickr';
import '@simonwep/pickr/dist/themes/nano.min.css';

const SWATCHES = ['#dc3545', '#fd7e14', '#ffc107', '#198754', '#20c997', '#0dcaf0', '#0d6efd', '#6610f2', '#d63384', '#6c757d'];

const hex = (color) => {
    const [r, g, b] = color.toRGBA().map((channel) => Math.round(channel));

    return `#${[r, g, b].map((channel) => channel.toString(16).padStart(2, '0')).join('')}`;
};

const enhance = (input) => {
    if (input.dataset.pickr) {
        return;
    }
    input.dataset.pickr = '1';
    input.classList.add('d-none');

    const anchor = document.createElement('div');
    input.after(anchor);

    const pickr = Pickr.create({
        el: anchor,
        theme: 'nano',
        default: input.value || '#6c757d',
        swatches: SWATCHES,
        defaultRepresentation: 'HEX',
        components: {
            preview: true,
            hue: true,
            interaction: { hex: true, rgba: true, hsla: true, hsva: true, input: true, save: true },
        },
        i18n: { 'btn:save': 'OK' },
    });

    const apply = (color) => {
        input.value = hex(color);
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    };

    pickr.on('change', (color) => apply(color));
    pickr.on('save', (color) => {
        if (color) {
            apply(color);
        }
        pickr.hide();
    });
};

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form input[type="color"]').forEach(enhance);
});
