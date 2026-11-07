// Arbre des lieux de l'admin : repli/dépli, filtre qui déplie les chemins correspondants, navigation au clavier.

const fold = (text) => text.normalize('NFD').replace(/\p{M}/gu, '').toLowerCase();

const isExpandable = (item) => item.hasAttribute('aria-expanded');
const isExpanded = (item) => item.getAttribute('aria-expanded') === 'true';
const setExpanded = (item, expanded) => {
    if (isExpandable(item)) {
        item.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    }
};
const rowOf = (item) => item.querySelector(':scope > .loc-row');
const linkOf = (item) => rowOf(item).querySelector('.loc-link');
const parentItem = (item) => item.parentElement.closest('li[role="treeitem"]');

const init = (root) => {
    if (root.dataset.locInit) {
        return;
    }
    root.dataset.locInit = '1';

    const tree = root.querySelector('[role="tree"]');
    if (!tree) {
        return;
    }
    const items = () => [...tree.querySelectorAll('li[role="treeitem"]')];
    const filter = root.querySelector('[data-loc-filter]');
    const status = root.querySelector('[data-loc-status]');
    let saved = null;

    const visibleLinks = () => items()
        .filter((item) => !item.hidden && !(parentItem(item) && ancestorsCollapsed(item)))
        .map(linkOf);
    const ancestorsCollapsed = (item) => {
        for (let parent = parentItem(item); parent; parent = parentItem(parent)) {
            if (!isExpanded(parent)) {
                return true;
            }
        }
        return false;
    };

    const restore = () => {
        items().forEach((item) => {
            item.hidden = false;
            rowOf(item).classList.remove('is-match');
        });
        if (saved) {
            items().forEach((item, index) => setExpanded(item, saved[index]));
            saved = null;
        }
        if (status) {
            status.textContent = '';
        }
    };

    const apply = (query) => {
        const needle = fold(query.trim());
        if (needle === '') {
            restore();
            return;
        }
        if (!saved) {
            saved = items().map(isExpanded);
        }
        const all = items();
        const matches = new Set(all.filter((item) => fold(item.dataset.name).includes(needle)));
        const shown = new Set(matches);
        matches.forEach((item) => {
            for (let parent = parentItem(item); parent; parent = parentItem(parent)) {
                shown.add(parent);
            }
        });
        all.forEach((item) => {
            item.hidden = !shown.has(item);
            rowOf(item).classList.toggle('is-match', matches.has(item));
            // un parent s'ouvre seulement s'il mène à un résultat plus bas
            setExpanded(item, [...matches].some((match) => match !== item && item.contains(match)));
        });
        if (status) {
            status.textContent = `${matches.size} ✓`;
        }
    };

    root.addEventListener('click', (event) => {
        const toggle = event.target.closest('.loc-toggle');
        if (toggle && tree.contains(toggle)) {
            const item = toggle.closest('li[role="treeitem"]');
            setExpanded(item, !isExpanded(item));
        }
        if (event.target.closest('[data-loc-expand]')) {
            items().forEach((item) => setExpanded(item, true));
        }
        if (event.target.closest('[data-loc-collapse]')) {
            items().forEach((item) => setExpanded(item, false));
        }
    });

    filter?.addEventListener('input', () => apply(filter.value));
    filter?.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            filter.value = '';
            apply('');
        }
    });

    tree.addEventListener('keydown', (event) => {
        const control = event.target.closest('.loc-link, .loc-toggle');
        if (!control) {
            return;
        }
        const item = control.closest('li[role="treeitem"]');
        const links = visibleLinks();
        const index = links.indexOf(linkOf(item));
        let handled = true;

        switch (event.key) {
            case 'ArrowDown':
                links[index + 1]?.focus();
                break;
            case 'ArrowUp':
                links[index - 1]?.focus();
                break;
            case 'Home':
                links[0]?.focus();
                break;
            case 'End':
                links[links.length - 1]?.focus();
                break;
            case 'ArrowRight':
                if (isExpandable(item) && !isExpanded(item)) {
                    setExpanded(item, true);
                } else if (isExpandable(item)) {
                    links[index + 1]?.focus();
                }
                break;
            case 'ArrowLeft':
                if (isExpandable(item) && isExpanded(item)) {
                    setExpanded(item, false);
                } else if (parentItem(item)) {
                    linkOf(parentItem(item)).focus();
                }
                break;
            default:
                handled = false;
        }
        if (handled) {
            event.preventDefault();
        }
    });
};

const boot = () => document.querySelectorAll('[data-location-tree]').forEach(init);
document.addEventListener('DOMContentLoaded', boot);
boot();
