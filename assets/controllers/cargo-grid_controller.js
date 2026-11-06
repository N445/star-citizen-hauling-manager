import { Controller } from '@hotwired/stimulus';
import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';

const PALETTES = {
    light: { floor: '#8a9097', floorOpacity: 0.14, line: '#a3a9b0', lineOpacity: 0.6, outline: '#5c636a', contrast: '#111418', edgeShade: 0.55 },
    dark: { floor: '#7d8a99', floorOpacity: 0.1, line: '#4d5866', lineOpacity: 0.75, outline: '#8d99a8', contrast: '#ffffff', edgeShade: 1.35 },
};
const ACCENTS = { pickup: ['--hm-pickup', '#2e9e5b'], drop: ['--hm-drop', '#e8890c'], stacked: ['--bs-danger', '#dc3545'] };
const DOOR_COLOR = '#e8890c';
const ARROW_LENGTH = 3;
const ARROW_GAP = 0.4;
const CRATE_GAP = 0.08;
const DROP_HEIGHT = 1.5;
const DURATION = { appear: 420, leave: 380, dim: 160, camera: 650, pulse: 2400 };
const PULSES = 3;

const range = (count) => Array.from({ length: count + 1 }, (_, index) => index);
const point = (x, y, z) => new THREE.Vector3(x, y, z);
const easeOut = (k) => 1 - (1 - k) ** 3;
const easeIn = (k) => k ** 3;
const easeInOut = (k) => (k < 0.5 ? 4 * k ** 3 : 1 - (-2 * k + 2) ** 3 / 2);

const floorLines = ({ width, length }) => [
    ...range(length).flatMap((z) => [point(0, 0, z), point(width, 0, z)]),
    ...range(width).flatMap((x) => [point(x, 0, 0), point(x, 0, length)]),
];

const boxGeometry = (width, height, length) => new THREE.BoxGeometry(width, height, length).translate(width / 2, height / 2, length / 2);

const doubleArrow = () => {
    const half = ARROW_LENGTH / 2;
    const shape = new THREE.Shape([
        [-half, 0], [-half + 1, 0.8], [-half + 1, 0.3], [half - 1, 0.3], [half - 1, 0.8],
        [half, 0], [half - 1, -0.8], [half - 1, -0.3], [-half + 1, -0.3], [-half + 1, -0.8],
    ].map(([x, y]) => new THREE.Vector2(x, y)));

    return new THREE.ShapeGeometry(shape);
};

const onFloor = (geometry) => geometry.rotateX(-Math.PI / 2);
const alongZ = (geometry) => onFloor(geometry).rotateY(Math.PI / 2);
const upright = (geometry) => geometry.rotateZ(Math.PI / 2);
const reach = ARROW_LENGTH / 2 + ARROW_GAP;

const doorArrows = {
    rear: (box, center) => ({ shape: alongZ, at: [center.x, box.min.y + 0.02, box.max.z + reach] }),
    front: (box, center) => ({ shape: alongZ, at: [center.x, box.min.y + 0.02, box.min.z - reach] }),
    right: (box, center) => ({ shape: onFloor, at: [box.max.x + reach, box.min.y + 0.02, center.z] }),
    left: (box, center) => ({ shape: onFloor, at: [box.min.x - reach, box.min.y + 0.02, center.z] }),
    top: (box, center) => ({ shape: upright, at: [center.x, box.max.y + reach, center.z] }),
    bottom: (box, center) => ({ shape: upright, at: [center.x, box.min.y - reach, center.z] }),
};

const crateId = (crate) => [crate.key, crate.x, crate.y, crate.z, crate.width, crate.height, crate.length].join('|');
const crateStatus = (crate) => (crate.unloading ? 'toUnload' : crate.phantom ? 'toLoad' : 'onBoard');
const crateVolume = (crate) => crate.size ?? crate.width * crate.height * crate.length;

// Résout n'importe quelle couleur CSS (variables, color-mix, oklch…) en RGB via un canvas 1×1.
const colorProbe = document.createElement('canvas').getContext('2d', { willReadFrequently: true });
const cssColor = (value, fallback) => {
    colorProbe.fillStyle = fallback;
    colorProbe.fillStyle = value || fallback;
    colorProbe.clearRect(0, 0, 1, 1);
    colorProbe.fillRect(0, 0, 1, 1);
    const [red, green, blue] = colorProbe.getImageData(0, 0, 1, 1).data;

    return new THREE.Color().setRGB(red / 255, green / 255, blue / 255, THREE.SRGBColorSpace);
};

export default class extends Controller {
    static targets = ['canvas', 'empty', 'stage', 'tooltip', 'legend', 'count', 'reset'];
    static values = { groups: Array, labels: Object };

    connect() {
        this.grids = [];
        this.groupsKey = null;
        this.planKey = null;
        this.cargoPlan = { doors: ['rear'], crates: [] };
        this.meshes = new Map();
        this.hoveredKey = null;
        this.pointed = null;
        this.pulse = 0;
        this.tweens = new Set();
        this.frameId = null;
        this.size = { width: 0, height: 0 };

        this.scene = new THREE.Scene();
        this.floorGroup = new THREE.Group();
        this.crateGroup = new THREE.Group();
        this.scene.add(this.floorGroup, this.crateGroup);
        this.camera = new THREE.PerspectiveCamera(40, 1, 0.1, 2000);
        this.renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
        this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        this.renderer.domElement.setAttribute('role', 'img');
        this.renderer.domElement.setAttribute('aria-label', this.labelsValue.canvas ?? '');
        this.canvasTarget.append(this.renderer.domElement);
        this.raycaster = new THREE.Raycaster();

        this.controls = new OrbitControls(this.camera, this.renderer.domElement);
        this.controls.addEventListener('change', () => this.draw());
        this.controls.addEventListener('start', () => {
            this.userMoved = true;
            this.stopTween(this.cameraTween);
            this.hideTooltip();
        });

        this.motion = window.matchMedia('(prefers-reduced-motion: reduce)');
        this.darkScheme = window.matchMedia('(prefers-color-scheme: dark)');
        this.onThemeChange = () => this.applyTheme();
        this.onPointerMove = (event) => this.pointAt(event);
        this.onPointerLeave = () => this.leave();
        window.addEventListener('theme:change', this.onThemeChange);
        this.darkScheme.addEventListener('change', this.onThemeChange);
        this.themeObserver = new MutationObserver(this.onThemeChange);
        this.themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['data-bs-theme'] });
        this.renderer.domElement.addEventListener('pointermove', this.onPointerMove);
        this.renderer.domElement.addEventListener('pointerleave', this.onPointerLeave);
        this.resizeObserver = new ResizeObserver(() => this.resize());
        this.resizeObserver.observe(this.canvasTarget);

        this.applyTheme(false);
        this.readInitialPlan();
        this.readFormDoors();
        this.useGroups(this.groupsValue);
    }

    disconnect() {
        cancelAnimationFrame(this.frameId);
        this.tweens.clear();
        window.removeEventListener('theme:change', this.onThemeChange);
        this.darkScheme.removeEventListener('change', this.onThemeChange);
        this.themeObserver.disconnect();
        this.resizeObserver.disconnect();
        this.controls.dispose();
        this.dispose(this.scene);
        this.scene.clear();
        this.renderer.dispose();
        this.renderer.domElement.remove();
    }

    show({ target, detail: { option } }) {
        if (target.matches('[data-ship-name]')) {
            this.useGroups(option?.cargoGroups ?? []);
        }
    }

    plan({ detail }) {
        const key = JSON.stringify(detail);

        if (key === this.planKey) {
            return;
        }

        this.planKey = key;
        this.cargoPlan = detail;
        this.syncCrates(true);
        this.addDoors();
        this.draw();
    }

    highlight({ detail: { key } }) {
        this.hoveredKey = key;
        const meshes = [...this.meshes.values()];
        const from = meshes.map((mesh) => mesh.userData.dim);
        const to = meshes.map((mesh) => (key !== null && !mesh.userData.crate.tokens.includes(key) ? 1 : 0));
        this.stopTween(this.dimTween);
        this.dimTween = this.tween(DURATION.dim, (k) => meshes.forEach((mesh, index) => {
            mesh.userData.dim = from[index] + (to[index] - from[index]) * k;
            this.paint(mesh);
        }));
    }

    resetView() {
        this.frame(true);
    }

    // Les portes cochées dans le formulaire priment sur celles du plan : elles s'affichent avant l'enregistrement.
    doors({ detail: { doors } }) {
        if (JSON.stringify(doors) === JSON.stringify(this.formDoors)) {
            return;
        }

        this.formDoors = doors;
        this.addDoors(true);
        this.draw();
    }

    readFormDoors() {
        const inputs = [...document.querySelectorAll('[data-door-sides-target="input"]')];
        this.formDoors = inputs.length ? inputs.filter((input) => input.checked).map((input) => input.value) : null;
    }

    readInitialPlan() {
        const planElement = document.querySelector('[data-cargo-plan-plan-value]');

        if (planElement) {
            this.cargoPlan = JSON.parse(planElement.dataset.cargoPlanPlanValue);
            this.planKey = JSON.stringify(this.cargoPlan);
        }
    }

    useGroups(groups) {
        const key = JSON.stringify(groups);

        if (key === this.groupsKey) {
            return;
        }

        const hadGrid = this.grids.length > 0;
        this.groupsKey = key;
        this.grids = groups.flatMap((group) => group.grids.map((grid) => ({ ...grid, x: group.x + grid.x, z: group.z + grid.z })));
        const hasGrid = this.grids.length > 0;
        this.emptyTarget.hidden = hasGrid;
        this.stageTarget.hidden = !hasGrid;
        this.toggleTarget('legend', hasGrid);
        this.toggleTarget('reset', hasGrid);
        this.buildFloors();
        this.syncCrates(hadGrid);
        this.addDoors();
        this.resize();
        this.frame(hadGrid);
        this.draw();
    }

    toggleTarget(name, visible) {
        this.targets.findAll(name).forEach((element) => {
            element.hidden = !visible;
        });
    }

    // Sol des grilles : reconstruit au changement de vaisseau ou de thème.
    buildFloors() {
        this.dispose(this.floorGroup);
        this.floorGroup.clear();
        const palette = this.palette;
        this.grids.forEach((grid) => {
            const floor = new THREE.Group();
            floor.position.set(grid.x, grid.y, grid.z);
            floor.add(
                new THREE.Mesh(boxGeometry(grid.width, 0.02, grid.length), new THREE.MeshBasicMaterial({ color: palette.floor, transparent: true, opacity: palette.floorOpacity, depthWrite: false })),
                new THREE.LineSegments(new THREE.BufferGeometry().setFromPoints(floorLines(grid)), new THREE.LineBasicMaterial({ color: palette.line, transparent: true, opacity: palette.lineOpacity })),
                new THREE.LineSegments(new THREE.EdgesGeometry(boxGeometry(grid.width, 0, grid.length)), new THREE.LineBasicMaterial({ color: palette.outline })),
            );
            this.floorGroup.add(floor);
        });
    }

    addDoors(animate = false) {
        this.floorGroup.children.filter((child) => child.userData.door).forEach((arrow) => {
            this.dispose(arrow);
            arrow.removeFromParent();
        });

        if (this.grids.length === 0) {
            return;
        }

        const bounds = this.gridBounds();
        const center = bounds.getCenter(new THREE.Vector3());
        const shown = new Set(this.doorsShown ?? []);
        const doors = this.formDoors ?? (this.cargoPlan.doors?.length ? this.cargoPlan.doors : ['rear']);
        this.doorsShown = doors;
        doors.forEach((door) => {
            const { shape, at } = (doorArrows[door] ?? doorArrows.rear)(bounds, center);
            const material = new THREE.MeshBasicMaterial({ color: DOOR_COLOR, side: THREE.DoubleSide, transparent: true, opacity: 0.9 });
            const arrow = new THREE.Mesh(shape(doubleArrow()), material);
            arrow.position.set(...at);
            arrow.userData.door = true;
            this.floorGroup.add(arrow);

            if (animate && !shown.has(door)) {
                material.opacity = 0;
                this.tween(DURATION.appear, (k) => {
                    material.opacity = 0.9 * k;
                    arrow.scale.setScalar(0.6 + 0.4 * k);
                });
            }
        });
    }

    // Garde les caisses inchangées, fait tomber les nouvelles et remonter celles qui partent.
    syncCrates(animate) {
        const crates = this.grids.length === 0 ? [] : this.cargoPlan.crates ?? [];
        const next = new Map(crates.map((crate) => [crateId(crate), crate]));

        this.meshes.forEach((mesh, id) => {
            if (!next.has(id)) {
                this.meshes.delete(id);
                this.removeCrate(mesh, animate);
            }
        });

        let delay = 0;
        next.forEach((crate, id) => {
            const existing = this.meshes.get(id);

            if (existing) {
                existing.userData.crate = crate;
                this.paint(existing);

                return;
            }

            const mesh = this.crate(crate);
            this.meshes.set(id, mesh);
            this.crateGroup.add(mesh);
            this.dropIn(mesh, animate ? delay : null);
            delay = Math.min(delay + 40, 400);
        });

        this.updateLegend(crates);
        this.startPulse();
    }

    crate(crate) {
        const geometry = new THREE.BoxGeometry(crate.width - CRATE_GAP, crate.height - CRATE_GAP, crate.length - CRATE_GAP);
        const mesh = new THREE.Mesh(geometry, new THREE.MeshBasicMaterial({ color: crate.color, transparent: true, depthWrite: !crate.phantom }));
        mesh.position.set(crate.x + crate.width / 2, crate.y + crate.height / 2, crate.z + crate.length / 2);
        mesh.add(new THREE.LineSegments(new THREE.EdgesGeometry(geometry), new THREE.LineBasicMaterial({ transparent: true })));
        mesh.userData = { crate, restY: mesh.position.y, appear: 1, dim: 0, leaving: false };

        return mesh;
    }

    dropIn(mesh, delay) {
        const { userData } = mesh;
        userData.dim = this.hoveredKey !== null && !userData.crate.tokens.includes(this.hoveredKey) ? 1 : 0;

        if (delay === null) {
            this.paint(mesh);

            return;
        }

        userData.appear = 0;
        this.paint(mesh);
        this.tween(DURATION.appear, (k) => {
            userData.appear = k;
            mesh.position.y = userData.restY + (1 - k) * DROP_HEIGHT;
            this.paint(mesh);
        }, null, delay);
    }

    removeCrate(mesh, animate) {
        const { userData } = mesh;
        userData.leaving = true;

        if (this.pointed === mesh) {
            this.leave();
        }

        const done = () => {
            this.dispose(mesh);
            mesh.removeFromParent();
        };

        if (!animate) {
            done();

            return;
        }

        // Inverse de l'arrivée : la caisse est soulevée et s'efface en montant.
        const start = userData.appear;
        const fromY = mesh.position.y;
        this.tween(DURATION.leave, (k) => {
            userData.appear = start * (1 - k);
            mesh.position.y = fromY + k * DROP_HEIGHT;
            this.paint(mesh);
        }, done, 0, easeIn);
    }

    // Les caisses de l'arrêt courant (à charger / à décharger) clignotent quelques fois puis restent colorées.
    startPulse() {
        this.stopTween(this.pulseTween);
        this.pulse = 0;

        if (![...this.meshes.values()].some((mesh) => this.pulseAccent(mesh.userData.crate))) {
            return;
        }

        this.pulseTween = this.tween(DURATION.pulse, (_, raw) => {
            this.pulse = raw >= 1 ? 0 : (1 - Math.cos(raw * PULSES * 2 * Math.PI)) / 2;
            this.meshes.forEach((mesh) => this.pulseAccent(mesh.userData.crate) && this.paint(mesh));
        }, null, 250);
    }

    pulseAccent(crate) {
        if (crate.unloading) {
            return this.accents.drop;
        }

        return crate.phantom ? this.accents.pickup : null;
    }

    paint(mesh) {
        const { crate, appear, dim } = mesh.userData;
        const pointed = this.pointed === mesh;
        const pulseAccent = this.pulseAccent(crate);
        const pulse = pulseAccent ? this.pulse : 0;
        const edgeAccent = crate.stacked ? this.accents.stacked : pulseAccent;
        const base = crate.phantom ? 0.3 + 0.3 * pulse : 0.85;

        mesh.material.color.set(crate.color);
        if (pulse > 0) {
            mesh.material.color.lerp(pulseAccent, 0.45 * pulse);
        }
        mesh.material.opacity = Math.min(1, (pointed ? base + 0.1 : base) * appear * (1 - 0.75 * dim));

        const edges = mesh.children[0].material;
        if (pointed) {
            edges.color.copy(this.palette.contrastColor);
        } else if (edgeAccent) {
            edges.color.copy(edgeAccent);
        } else {
            edges.color.set(crate.color).multiplyScalar(this.palette.edgeShade);
        }
        edges.opacity = appear * (1 - 0.8 * dim);

        if (!mesh.userData.leaving) {
            mesh.scale.setScalar(pointed ? 1.04 : 1);
        }
    }

    updateLegend(crates) {
        const totals = { onBoard: 0, toLoad: 0, toUnload: 0, stacked: 0 };
        crates.forEach((crate) => {
            totals[crateStatus(crate)] += crateVolume(crate);
            totals.stacked += crate.stacked ? 1 : 0;
        });

        this.countTargets.forEach((element) => {
            const value = totals[element.dataset.kind] ?? 0;
            element.textContent = value;
            element.closest('[data-legend-item]')?.toggleAttribute('data-empty', value === 0);
        });
    }

    applyTheme(redraw = true) {
        const attribute = document.documentElement.dataset.bsTheme;
        const theme = attribute === 'dark' || attribute === 'light' ? attribute : (this.darkScheme.matches ? 'dark' : 'light');

        if (redraw && theme === this.theme) {
            return;
        }

        this.theme = theme;
        this.palette = { ...PALETTES[theme], contrastColor: new THREE.Color(PALETTES[theme].contrast) };
        const styles = getComputedStyle(document.documentElement);
        this.accents = Object.fromEntries(Object.entries(ACCENTS).map(([name, [variable, fallback]]) => [name, cssColor(styles.getPropertyValue(variable).trim(), fallback)]));

        if (!redraw) {
            return;
        }

        this.buildFloors();
        this.addDoors();
        this.meshes.forEach((mesh) => this.paint(mesh));
        this.draw();
    }

    gridBounds() {
        return this.grids.reduce(
            (bounds, grid) => bounds.union(new THREE.Box3(point(grid.x, grid.y, grid.z), point(grid.x + grid.width, grid.y + grid.height, grid.z + grid.length))),
            new THREE.Box3(),
        );
    }

    pointAt(event) {
        if (event.buttons !== 0) {
            return;
        }

        const area = this.renderer.domElement.getBoundingClientRect();
        const pointer = new THREE.Vector2(((event.clientX - area.left) / area.width) * 2 - 1, -((event.clientY - area.top) / area.height) * 2 + 1);
        this.raycaster.setFromCamera(pointer, this.camera);
        const targets = [...this.meshes.values()].filter((mesh) => mesh.userData.appear > 0.5);
        const [hit] = this.raycaster.intersectObjects(targets, false);
        this.point(hit?.object ?? null);

        if (hit) {
            this.showTooltip(hit.object.userData.crate, event.clientX - area.left, event.clientY - area.top);
        } else {
            this.hideTooltip();
        }
    }

    point(mesh) {
        if (mesh === this.pointed) {
            return;
        }

        const previous = this.pointed;
        this.pointed = mesh;
        [previous, mesh].filter(Boolean).forEach((crate) => this.paint(crate));
        this.renderer.domElement.classList.toggle('is-pointing', mesh !== null);
        this.announce(mesh?.userData.crate.key ?? null);
        this.draw();
    }

    leave() {
        this.point(null);
        this.hideTooltip();
    }

    announce(key) {
        if (key !== this.hoveredKey) {
            window.dispatchEvent(new CustomEvent('cargo:highlight', { detail: { key } }));
        }
    }

    showTooltip(crate, x, y) {
        if (!this.hasTooltipTarget) {
            return;
        }

        const tooltip = this.tooltipTarget;

        if (tooltip.dataset.crate !== crateId(crate)) {
            tooltip.dataset.crate = crateId(crate);
            this.fillTooltip(tooltip, crate);
        }

        tooltip.hidden = false;
        const { width, height } = this.size;
        const left = x + 14 + tooltip.offsetWidth > width ? x - 14 - tooltip.offsetWidth : x + 14;
        const top = Math.min(Math.max(0, y + 14), height - tooltip.offsetHeight);
        tooltip.style.transform = `translate(${Math.max(0, left)}px, ${Math.max(0, top)}px)`;
        tooltip.classList.add('is-visible');
    }

    fillTooltip(tooltip, crate) {
        const line = (className, text) => Object.assign(document.createElement('div'), { className, textContent: text });
        const status = crateStatus(crate);
        tooltip.replaceChildren();
        tooltip.dataset.status = status;
        tooltip.style.setProperty('--crate-color', crate.color);

        if (crate.resource === undefined) {
            tooltip.append(line('cargo-tooltip__title', crate.label ?? ''));

            return;
        }

        const title = line('cargo-tooltip__title', `${crate.size} SCU · `);
        title.append(Object.assign(document.createElement('span'), { className: 'cargo-tooltip__resource', textContent: crate.resource }));
        tooltip.append(
            title,
            line('cargo-tooltip__mission', crate.mission),
            line('cargo-tooltip__dropoff', `→ ${crate.dropoff}`),
            line('cargo-tooltip__status', this.labelsValue[status] ?? ''),
        );

        if (crate.stacked) {
            tooltip.append(line('cargo-tooltip__warning', this.labelsValue.stacked ?? ''));
        }
    }

    hideTooltip() {
        if (this.hasTooltipTarget) {
            this.tooltipTarget.classList.remove('is-visible');
        }
    }

    frame(animate) {
        if (this.grids.length === 0) {
            return;
        }

        // Cadre la sphère englobante (grilles + flèches de porte) quel que soit le format du canvas.
        this.userMoved = false;
        const sphere = this.gridBounds().expandByVector(point(reach, 0, reach)).getBoundingSphere(new THREE.Sphere());
        const center = sphere.center;
        const halfFov = THREE.MathUtils.degToRad(this.camera.fov / 2);
        const distance = sphere.radius / Math.sin(Math.min(halfFov, Math.atan(Math.tan(halfFov) * this.camera.aspect))) * 0.7;
        const position = center.clone().add(point(0.6, 0.55, 0.6).normalize().multiplyScalar(distance));
        this.stopTween(this.cameraTween);

        if (!animate) {
            this.camera.position.copy(position);
            this.controls.target.copy(center);
            this.controls.update();

            return;
        }

        const fromPosition = this.camera.position.clone();
        const fromTarget = this.controls.target.clone();
        this.cameraTween = this.tween(DURATION.camera, (k) => {
            this.camera.position.lerpVectors(fromPosition, position, k);
            this.controls.target.lerpVectors(fromTarget, center, k);
            this.controls.update();
        }, () => {
            this.cameraTween = null;
        }, 0, easeInOut);
    }

    resize() {
        const width = this.canvasTarget.clientWidth;

        if (width === 0) {
            return;
        }

        const height = Math.round(Math.min(width * 0.85, window.innerHeight * 0.7));

        if (width === this.size.width && height === this.size.height) {
            return;
        }

        this.size = { width, height };
        this.renderer.setSize(width, height);
        this.camera.aspect = width / height;
        this.camera.updateProjectionMatrix();

        if (!this.userMoved && !this.cameraTween) {
            this.frame(false);
        }

        this.draw();
    }

    // Animations : boucle requestAnimationFrame uniquement tant qu'une animation tourne, sinon rendu à la demande.
    tween(duration, step, done = null, delay = 0, ease = easeOut) {
        if (this.motion.matches) {
            step(1, 1);
            done?.();
            this.draw();

            return null;
        }

        const tween = { start: performance.now() + delay, duration, step, done, ease };
        this.tweens.add(tween);
        this.loop();

        return tween;
    }

    stopTween(tween) {
        if (tween) {
            this.tweens.delete(tween);
        }

        if (tween === this.cameraTween) {
            this.cameraTween = null;
        }
    }

    loop() {
        this.frameId ??= requestAnimationFrame((now) => this.tick(now));
    }

    tick(now) {
        this.frameId = null;
        this.tweens.forEach((tween) => {
            const raw = Math.min(1, Math.max(0, (now - tween.start) / tween.duration));

            if (now < tween.start) {
                return;
            }

            tween.step(tween.ease(raw), raw);

            if (raw === 1) {
                this.tweens.delete(tween);
                tween.done?.();
            }
        });
        this.draw();

        if (this.tweens.size > 0) {
            this.loop();
        }
    }

    draw() {
        if (this.size.width > 0) {
            this.renderer.render(this.scene, this.camera);
        }
    }

    dispose(root) {
        root.traverse((object) => {
            object.geometry?.dispose();
            object.material?.dispose();
        });
    }
}
