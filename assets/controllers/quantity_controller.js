import { Controller } from '@hotwired/stimulus';
import { getComponent } from '@symfony/ux-live-component';
import { track } from '../stats.js';

/*
 * Quantité réelle d'un ramassage ou d'une livraison : un champ ordinaire (hors modèle Live) qui appelle l'action
 * du RoutePanel avec des arguments scalaires. Le serveur peut refuser (message en toast) : le champ reprend alors
 * la valeur rendue par le serveur.
 */
export default class extends Controller {
    static values = { action: String, delivery: Number, mission: Number, location: String, kind: String };
    static targets = ['badge', 'input'];

    edit() {
        this.badgeTarget.hidden = true;
        this.inputTarget.hidden = false;
        this.inputTarget.focus();
        this.inputTarget.select();
    }

    key(event) {
        if ('Enter' === event.key) {
            event.preventDefault();
            this.inputTarget.blur();
        } else if ('Escape' === event.key) {
            this.cancelled = true;
            this.inputTarget.value = this.inputTarget.defaultValue;
            this.inputTarget.blur();
        }
    }

    close() {
        this.inputTarget.hidden = true;
        this.badgeTarget.hidden = false;
    }

    async save() {
        const input = this.inputTarget;
        const unchanged = this.cancelled || input.value === input.defaultValue;
        this.cancelled = false;
        this.close();

        if (unchanged) {
            return;
        }

        const args = { quantity: input.value };

        if (this.actionValue === 'setDeliveryQuantity') {
            args.delivery = this.deliveryValue;
        } else {
            args.mission = this.missionValue;
            args.location = this.locationValue;
        }

        const component = await getComponent(this.element.closest('[data-controller~="live"]'));
        track('quantity-edit', { kind: this.kindValue });
        await component.action(this.actionValue, args);

        if (input.isConnected) {
            input.value = input.defaultValue;
        }
    }
}
