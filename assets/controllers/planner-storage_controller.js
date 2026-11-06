import { Controller } from '@hotwired/stimulus';
import { getComponent } from '@symfony/ux-live-component';

const KEY = 'sch.planner.v1';

function read() {
    try {
        return window.localStorage.getItem(KEY);
    } catch {
        return null;
    }
}

function write(value) {
    try {
        window.localStorage.setItem(KEY, value);
    } catch {
        // storage unavailable or full: the planner simply is not persisted
    }
}

/**
 * Persists the planner (ship, start, missions, "calculated" flag) in localStorage and restores it through the
 * Live Component. The planner stays hidden while the restore request is in flight to avoid a flash of defaults.
 */
export default class extends Controller {
    async connect() {
        const planner = this.element.querySelector('[data-controller~="live"][data-live-name-value="HaulingPlanner"]');
        if (!planner) {
            return;
        }

        const saved = read();
        if (saved) {
            this.element.classList.add('restoring');
            this.failsafe = setTimeout(() => this.element.classList.remove('restoring'), 4000);
        }

        try {
            this.component = await getComponent(planner);
        } catch {
            this.done();

            return;
        }

        this.listener = () => this.save();
        this.component.on('model:set', this.listener);
        this.component.on('render:finished', this.listener);

        if (saved) {
            try {
                await this.component.action('restore', { state: saved });
            } catch {
                // invalid or outdated data: keep the defaults
            }
        }
        this.done();
    }

    disconnect() {
        clearTimeout(this.timer);
        clearTimeout(this.failsafe);
        this.component?.off('model:set', this.listener);
        this.component?.off('render:finished', this.listener);
    }

    done() {
        clearTimeout(this.failsafe);
        this.element.classList.remove('restoring');
    }

    save() {
        clearTimeout(this.timer);
        this.timer = setTimeout(() => {
            if (!this.component) {
                return;
            }
            try {
                write(JSON.stringify({
                    ship: this.component.getData('ship'),
                    start: this.component.getData('start'),
                    missions: this.component.getData('missions'),
                    doors: this.component.getData('doors'),
                    pins: this.component.getData('pins'),
                    calculated: this.component.getData('calculated') === true,
                }));
            } catch {
                // ignore
            }
        }, 250);
    }
}
