// Événements Umami : n'importe quel élément portant data-track="nom" (data-track-on="change" pour un champ,
// data-track-props='{"clé":"valeur"}' pour des détails). Délégué sur le document : survit aux rendus Live Component.
export function track(name, data) {
    try {
        window.umami?.track(name, data);
    } catch {
        // le suivi ne doit jamais gêner l'interface
    }
}

for (const type of ['click', 'change']) {
    document.addEventListener(type, (event) => {
        const element = event.target instanceof Element ? event.target.closest('[data-track]') : null;

        if (!element || (element.dataset.trackOn ?? 'click') !== type) {
            return;
        }

        let props;
        try {
            props = element.dataset.trackProps ? JSON.parse(element.dataset.trackProps) : undefined;
        } catch {
            props = undefined;
        }

        track(element.dataset.track, props);
    });
}
