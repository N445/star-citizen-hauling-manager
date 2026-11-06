import { Controller } from '@hotwired/stimulus';
import { getComponent } from '@symfony/ux-live-component';
import Sortable from 'sortablejs';

export default class extends Controller {
    connect() {
        this.sortable = Sortable.create(this.element, {
            draggable: '.route-stop--movable',
            handle: '.route-stop-handle',
            animation: 150,
            onEnd: () => this.reorder(),
        });
    }

    disconnect() {
        this.sortable.destroy();
    }

    async reorder() {
        const locations = [...this.element.querySelectorAll('.route-stop:not(.route-stop--start)')].map((stop) => stop.dataset.location);
        const component = await getComponent(this.element.closest('[data-controller~="live"]'));
        component.action('reorder', { locations: JSON.stringify(locations) });
    }
}
