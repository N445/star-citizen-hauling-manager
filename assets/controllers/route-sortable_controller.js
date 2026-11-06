import { Controller } from '@hotwired/stimulus';
import { getComponent } from '@symfony/ux-live-component';
import Sortable from 'sortablejs';
import { track } from '../stats.js';

const MOVABLE = '.route-stop--movable';

export default class extends Controller {
    connect() {
        const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
        this.sortable = Sortable.create(this.element, {
            draggable: MOVABLE,
            handle: '.route-stop-handle',
            delay: 120,
            delayOnTouchOnly: true,
            touchStartThreshold: 6,
            fallbackTolerance: 4,
            scrollSensitivity: 90,
            animation: reduce ? 0 : 180,
            easing: 'cubic-bezier(0.2, 0, 0, 1)',
            ghostClass: 'route-stop--ghost',
            chosenClass: 'route-stop--chosen',
            dragClass: 'route-stop--drag',
            onEnd: ({ item, oldIndex, newIndex }) => oldIndex !== newIndex && this.reorder(item),
        });
    }

    disconnect() {
        this.sortable.destroy();
    }

    // Keyboard alternative to drag-and-drop: arrow keys on the handle move the stop among the movable ones.
    move(event) {
        const offset = { ArrowUp: -1, ArrowDown: 1 }[event.key];

        if (!offset) {
            return;
        }

        event.preventDefault();
        const stop = event.target.closest(MOVABLE);
        const movable = [...this.element.querySelectorAll(MOVABLE)];
        const target = movable[movable.indexOf(stop) + offset];

        if (!target) {
            return;
        }

        target.insertAdjacentElement(offset < 0 ? 'beforebegin' : 'afterend', stop);
        event.target.focus();
        this.reorder(stop, true);
    }

    async reorder(stop, refocus = false) {
        const locations = [...this.element.querySelectorAll('.route-stop:not(.route-stop--start)')].map((element) => element.dataset.location);
        const component = await getComponent(this.element.closest('[data-controller~="live"]'));
        await component.action('reorder', { locations: JSON.stringify(locations) });
        track('route-reorder');

        if (refocus) {
            this.focusHandle(stop.dataset.location);
        }
    }

    // The re-render may reuse other nodes: give the focus back to the moved stop's handle.
    focusHandle(location) {
        [...this.element.querySelectorAll(MOVABLE)].find((element) => element.dataset.location === location)?.querySelector('.route-stop-handle')?.focus();
    }
}
