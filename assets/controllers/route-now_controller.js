import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    jump(event) {
        event.preventDefault();
        const stop = document.querySelector(this.element.getAttribute('href'));
        const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
        stop?.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'center' });
    }
}
