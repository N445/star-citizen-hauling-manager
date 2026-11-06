import { Controller } from '@hotwired/stimulus';

// Below the xl breakpoint the three panes become tabs (CSS hides the inactive panes); above it every pane is visible.
export default class extends Controller {
    static targets = ['pane', 'tab'];
    static values = { initial: String, key: String };

    connect() {
        this.select(this.stored() ?? this.initialValue, false);
    }

    show({ params: { pane } }) {
        this.select(pane, true);
    }

    select(pane, scroll) {
        if (!this.paneTargets.some((element) => element.dataset.pane === pane)) {
            pane = this.initialValue;
        }

        this.element.dataset.tab = pane;
        this.tabTargets.forEach((tab) => tab.setAttribute('aria-selected', String(tab.dataset.pane === pane)));

        if (scroll) {
            this.store(pane);
            window.scrollTo({ top: 0 });
            // The 3D scene may have been hidden while it was resized: nudge its ResizeObserver-less listeners.
            window.dispatchEvent(new Event('resize'));
        }
    }

    stored() {
        try {
            return sessionStorage.getItem(`hm-tab-${this.keyValue}`);
        } catch (error) {
            return null;
        }
    }

    store(pane) {
        try {
            sessionStorage.setItem(`hm-tab-${this.keyValue}`, pane);
        } catch (error) {
            // Private mode: the tab simply is not remembered.
        }
    }
}
