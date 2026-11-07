// Règles du placement manuel, côté navigateur (aperçu vert / rouge). Le serveur revalide tout (PlacementRules.php).
// Une caisse : { x, y, z, width, height, length, size } en coordonnées du monde (1 cellule = 1 SCU).
const EPS = 1e-6;

export const SIZES = {
    1: [1, 1, 1], 2: [1, 2, 1], 4: [2, 2, 1], 8: [2, 2, 2], 16: [2, 4, 2], 24: [2, 6, 2], 32: [2, 8, 2],
};

const overlaps = (a, b) => a.x < b.x + b.width - EPS && b.x < a.x + a.width - EPS
    && a.y < b.y + b.height - EPS && b.y < a.y + a.height - EPS
    && a.z < b.z + b.length - EPS && b.z < a.z + a.length - EPS;

const insideGrid = (grid, box) => box.x > grid.x - EPS && box.x + box.width < grid.x + grid.width + EPS
    && box.y > grid.y - EPS && box.y + box.height < grid.y + grid.height + EPS
    && box.z > grid.z - EPS && box.z + box.length < grid.z + grid.length + EPS;

const accepts = (grid, size) => size <= (grid.maxSize ?? 32) && size >= (grid.minSize ?? 1);

export const gridOf = (grids, box) => grids.find((grid) => insideGrid(grid, box)) ?? null;

export const isShapeAllowed = ({ size, width, height, length }) => {
    const [w, l, h] = SIZES[size] ?? [0, 0, 0];

    return height === h && ((width === w && length === l) || (width === l && length === w));
};

// Chaque cellule du dessous (couche y - 1) doit être couverte par le sol ou par une caisse.
const supported = (grid, blockers, box) => {
    if (box.y <= grid.y + EPS) {
        return true;
    }

    for (let x = box.x; x < box.x + box.width - EPS; x++) {
        for (let z = box.z; z < box.z + box.length - EPS; z++) {
            const cell = { x, y: box.y - 1, z, width: 1, height: 1, length: 1 };

            if (!blockers.some((other) => other !== box && overlaps(other, cell))) {
                return false;
            }
        }
    }

    return true;
};

// Retourne la clé du problème (outside, shape, size, overlap, unsupported, supports) ou null si la disposition est valide.
export function layoutProblem(grids, fixed, moved) {
    const blockers = [...fixed];

    for (const box of [...moved].sort((a, b) => a.y - b.y)) {
        const grid = gridOf(grids, box);

        if (!isShapeAllowed(box)) return 'shape';
        if (!grid) return 'outside';
        if (!accepts(grid, box.size)) return 'size';
        if (blockers.some((other) => overlaps(other, box))) return 'overlap';
        if (!supported(grid, blockers, box)) return 'unsupported';

        blockers.push(box);
    }

    const unsupported = fixed.some((box) => {
        const grid = gridOf(grids, box);

        return grid && !supported(grid, blockers, box);
    });

    return unsupported ? 'supports' : null;
}

// Hauteur à laquelle une caisse posée à (x, z) s'arrête : sur le sol de la grille, ou sur la caisse la plus haute en dessous.
export function restHeight(grid, fixed, box) {
    return fixed
        .filter((other) => other.y >= grid.y - EPS && other.x < box.x + box.width - EPS && box.x < other.x + other.width - EPS
            && other.z < box.z + box.length - EPS && box.z < other.z + other.length - EPS)
        .reduce((top, other) => Math.max(top, other.y + other.height), grid.y);
}

// Grille la plus proche du point (x, z) du monde.
export function nearestGrid(grids, x, z) {
    const distance = (grid) => Math.hypot(Math.max(grid.x - x, 0, x - (grid.x + grid.width)), Math.max(grid.z - z, 0, z - (grid.z + grid.length)));

    return grids.reduce((best, grid) => (best === null || distance(grid) < distance(best) ? grid : best), null);
}

// Calque la position (x, z) du centre voulu sur les cellules de la grille, la caisse reposant sur ce qu'il y a dessous.
export function snap(grids, fixed, crate, centerX, centerZ) {
    const grid = nearestGrid(grids, centerX, centerZ);

    if (!grid) {
        return null;
    }

    const x = grid.x + Math.round(centerX - crate.width / 2 - grid.x);
    const z = grid.z + Math.round(centerZ - crate.length / 2 - grid.z);
    const box = { ...crate, x, z };

    return { ...box, y: restHeight(grid, fixed, box) };
}

// Comme layoutProblem pour une caisse posée par `snap` : si elle a été remontée sur une caisse voisine qu'elle ne couvre
// qu'en partie, le vrai motif est le chevauchement au niveau du sol.
export function restProblem(grids, fixed, box) {
    const problem = layoutProblem(grids, fixed, [box]);
    const grid = nearestGrid(grids, box.x + box.width / 2, box.z + box.length / 2);

    if (problem !== 'unsupported' || !grid) {
        return problem;
    }

    return layoutProblem(grids, fixed, [{ ...box, y: grid.y }]) === 'overlap' ? 'overlap' : problem;
}
