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

    async save() {
        const input = this.element;
        const args = { quantity: input.value };

        if (this.actionValue === 'setDeliveryQuantity') {
            args.delivery = this.deliveryValue;
        } else {
            args.mission = this.missionValue;
            args.location = this.locationValue;
        }

        const component = await getComponent(input.closest('[data-controller~="live"]'));
        track('quantity-edit', { kind: this.kindValue });
        await component.action(this.actionValue, args);

        if (input.isConnected) {
            input.value = input.defaultValue;
        }
    }
}
