import { Controller } from '@hotwired/stimulus';

const VARIANTS = ['success', 'danger', 'warning', 'info'];

/*
 * Toast stack. Anything can show a toast by dispatching on window (or any bubbling element):
 *   window.dispatchEvent(new CustomEvent('toast:show', {detail: {message: '…', variant: 'success'|'danger'|'warning'|'info'}}))
 * From a Live Component: $this->dispatchBrowserEvent('toast:show', ['message' => …, 'variant' => …]).
 */
export default class extends Controller {
    static targets = ['template'];

    connect() {
        this.onShow = (event) => this.show(event.detail ?? {});
        window.addEventListener('toast:show', this.onShow);
    }

    disconnect() {
        window.removeEventListener('toast:show', this.onShow);
    }

    show({ message, variant = 'info', delay }) {
        if (!message) return;
        const toast = this.templateTarget.content.firstElementChild.cloneNode(true);
        const safeVariant = VARIANTS.includes(variant) ? variant : 'info';
        toast.className = toast.className.replace(/hm-toast--\w+/, `hm-toast--${safeVariant}`);
        toast.setAttribute('role', ['danger', 'warning'].includes(safeVariant) ? 'alert' : 'status');
        toast.querySelector('[data-toast-message]').textContent = message;
        if (delay) toast.dataset.toastDelayValue = delay;
        this.element.insertBefore(toast, this.templateTarget);
    }
}
