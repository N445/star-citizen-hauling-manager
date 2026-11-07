import { Controller } from '@hotwired/stimulus';
import { getComponent } from '@symfony/ux-live-component';
import { Modal } from 'bootstrap';
import { track } from '../stats.js';

/*
 * Fenêtre « signaler une erreur » du parcours. La modale est déplacée dans <body> (le panneau est « sticky » : sa
 * pile de superposition passerait sous le fond de la modale) ; l'élément du contrôleur, protégé par data-live-ignore,
 * reste dans le Live Component pour retrouver son composant. L'envoi appelle l'action `report` (arguments scalaires,
 * jeton CSRF géré par Live) ; le serveur répond par un toast et l'événement `report:sent` qui ferme la fenêtre.
 */
export default class extends Controller {
    connect() {
        this.modal = this.element.querySelector('.modal') ?? this.modal;
        document.body.append(this.modal);
        this.field = (name) => this.modal.querySelector(`[data-report="${name}"]`);
        this.onSubmit = (event) => this.submit(event);
        this.onCount = () => this.count();
        this.onSent = () => this.sent();
        this.modal.querySelector('form').addEventListener('submit', this.onSubmit);
        this.field('message').addEventListener('input', this.onCount);
        window.addEventListener('report:sent', this.onSent);
    }

    disconnect() {
        window.removeEventListener('report:sent', this.onSent);
        this.modal?.querySelector('form')?.removeEventListener('submit', this.onSubmit);
        Modal.getInstance(this.modal)?.dispose();
        this.modal?.remove();
    }

    count() {
        this.field('counter').textContent = this.field('message').value.length;
        this.field('error').hidden = true;
    }

    async submit(event) {
        event.preventDefault();
        const message = this.field('message').value.trim();

        if (message === '') {
            this.field('error').hidden = false;
            this.field('message').focus();

            return;
        }

        const submit = this.field('submit');
        submit.disabled = true;

        try {
            const component = await getComponent(this.element.closest('[data-controller~="live"]'));
            track('route-report', { category: this.field('category').value });
            await component.action('report', { category: this.field('category').value, message });
        } finally {
            submit.disabled = false;
        }
    }

    sent() {
        this.field('message').value = '';
        this.count();
        Modal.getInstance(this.modal)?.hide();
    }
}
