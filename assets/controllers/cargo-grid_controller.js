import { Controller } from '@hotwired/stimulus';
import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import { getComponent } from '@symfony/ux-live-component';
import { layoutProblem, restProblem, snap, nearestGrid, restHeight } from '../cargo-rules.js';
import { track } from '../stats.js';

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
const DURATION = { appear: 420, leave: 380, dim: 160, camera: 650, pulse: 2400, blink: 1500 };
const PULSES = 3;
const SELECT_COLOR = '#4dabf7';
const DRAG_THRESHOLD = 5;
const NOTICE_DURATION = 5000;
const HALO = 0.16;
const NUDGES = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] };

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
const boxOf = (crate) => ({ x: crate.x, y: crate.y, z: crate.z, width: crate.width, height: crate.height, length: crate.length, size: crate.size });
const sameBox = (a, b) => ['x', 'y', 'z', 'width', 'length'].every((key) => a[key] === b[key]);
// Coche « bien posée » dessinée une fois pour toutes sur un canvas, posée à plat sur le dessus de la caisse.
let checkMap = null;
const checkTexture = () => {
    if (checkMap) {
        return checkMap;
    }

    const canvas = Object.assign(document.createElement('canvas'), { width: 128, height: 128 });
    const context = canvas.getContext('2d');
    context.fillStyle = 'rgba(20, 24, 28, 0.82)';
    context.beginPath();
    context.arc(64, 64, 58, 0, Math.PI * 2);
    context.fill();
    context.strokeStyle = '#ffffff';
    context.lineWidth = 12;
    context.lineCap = 'round';
    context.lineJoin = 'round';
    context.beginPath();
    context.moveTo(36, 66);
    context.lineTo(56, 87);
    context.lineTo(93, 44);
    context.stroke();
    checkMap = new THREE.CanvasTexture(canvas);
    checkMap.colorSpace = THREE.SRGBColorSpace;

    return checkMap;
};

const placeCheck = (mesh) => {
    const { crate, check } = mesh.userData;
    const side = Math.min(crate.width, crate.length) * 0.6;
    check.scale.set(side, 1, side);
    check.position.y = crate.height / 2 + 0.03;
};

// Halo : coque un peu plus grande vue de l'intérieur ; il entoure la caisse sans toucher à ses bordures (couleur de la ressource).
const haloGeometry = (crate) => new THREE.BoxGeometry(crate.width + HALO * 2, crate.height + HALO * 2, crate.length + HALO * 2);
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
    static targets = ['canvas', 'empty', 'stage', 'tooltip', 'legend', 'count', 'reset', 'notice', 'rotate', 'undo', 'resetMission', 'resetAll', 'lock', 'actions', 'placed', 'placedCount'];
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
        this.selected = null;
        this.press = null;
        this.drag = null;
        this.ghost = null;
        this.swapTarget = null;
        this.undoStack = [];
        this.locked = false;
        this.blink = 0;

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
        this.coarse = window.matchMedia('(pointer: coarse)');
        this.applyTouchMode();
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
        this.onPointerLeave = (event) => event.pointerType !== 'touch' && this.leave();
        window.addEventListener('theme:change', this.onThemeChange);
        this.darkScheme.addEventListener('change', this.onThemeChange);
        this.themeObserver = new MutationObserver(this.onThemeChange);
        this.themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['data-bs-theme'] });
        this.renderer.domElement.addEventListener('pointermove', this.onPointerMove);
        this.renderer.domElement.addEventListener('pointerleave', this.onPointerLeave);
        this.onViewportChange = () => this.resize();
        this.onPointerUp = (event) => this.tapAt(event);
        this.renderer.domElement.addEventListener('pointerdown', (event) => { this.downAt = [event.clientX, event.clientY]; });
        this.renderer.domElement.addEventListener('pointerup', this.onPointerUp);
        this.resizeObserver = new ResizeObserver(this.onViewportChange);
        this.resizeObserver.observe(this.canvasTarget);
        window.addEventListener('resize', this.onViewportChange);
        this.coarse.addEventListener('change', () => this.applyTouchMode());
        this.onKey = (event) => this.keyDown(event);
        this.element.addEventListener('keydown', this.onKey);
        this.onPress = (event) => this.pressAt(event);
        this.onDragMove = (event) => this.dragTo(event);
        this.onDragEnd = (event) => this.release(event);
        this.canvasTarget.addEventListener('pointerdown', this.onPress, { capture: true });

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
        window.removeEventListener('resize', this.onViewportChange);
        this.canvasTarget.removeEventListener('pointerdown', this.onPress, { capture: true });
        this.element.removeEventListener('keydown', this.onKey);
        this.stopPress();
        clearTimeout(this.noticeTimer);
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

    // Édition refusée par le serveur : les caisses déplacées localement reprennent la place du dernier plan reçu.
    rejected({ detail: { message } }) {
        this.syncCrates(false);
        this.say(message, 'error');
    }

    highlight({ detail: { key, locked } }) {
        this.locked = locked === true;
        this.hoveredKey = key;
        this.toggleTarget('lock', this.locked);
        this.startBlink();
        const meshes = [...this.meshes.values()];
        const from = meshes.map((mesh) => mesh.userData.dim);
        const to = meshes.map((mesh) => (key !== null && !mesh.userData.crate.tokens.includes(key) ? 1 : 0));
        this.stopTween(this.dimTween);
        this.dimTween = this.tween(DURATION.dim, (k) => meshes.forEach((mesh, index) => {
            mesh.userData.dim = from[index] + (to[index] - from[index]) * k;
            this.paint(mesh);
        }));
    }

    // Les caisses surlignées clignotent doucement tant que la surbrillance dure (rien ne tourne sinon). Mouvement réduit : atténuation fixe des autres.
    startBlink() {
        this.stopTween(this.blinkTween);
        this.blinkTween = null;
        this.blink = 0;
        const lit = () => [...this.meshes.values()].filter((mesh) => this.hoveredKey !== null && mesh.userData.crate.tokens.includes(this.hoveredKey));

        if (this.hoveredKey === null || this.motion.matches) {
            this.meshes.forEach((mesh) => this.paint(mesh));
            this.draw();

            return;
        }

        const run = () => {
            this.blinkTween = this.tween(DURATION.blink, (_, raw) => {
                this.blink = (1 - Math.cos(raw * 2 * Math.PI)) / 2;
                lit().forEach((mesh) => this.paint(mesh));
            }, () => this.hoveredKey !== null && run(), 0, (k) => k);
        };

        run();
    }

    // Placement manuel : sélection, glisser-déposer (aperçu vert / rouge), échange, rotation, annulation.
    boxes(...excluded) {
        return [...this.meshes.values()].filter((mesh) => !mesh.userData.leaving && !excluded.includes(mesh)).map((mesh) => boxOf(mesh.userData.crate));
    }

    editable(mesh) {
        return mesh.userData.crate.missionId !== undefined;
    }

    crateAt(event, accept) {
        const area = this.renderer.domElement.getBoundingClientRect();
        this.raycaster.setFromCamera(new THREE.Vector2(((event.clientX - area.left) / area.width) * 2 - 1, -((event.clientY - area.top) / area.height) * 2 + 1), this.camera);
        const targets = [...this.meshes.values()].filter((mesh) => mesh.userData.appear > 0.5 && !mesh.userData.leaving && accept(mesh));

        return this.raycaster.intersectObjects(targets, false)[0]?.object ?? null;
    }

    // Capturé avant OrbitControls : sur une caisse, la caméra ne doit pas bouger pendant le glisser.
    pressAt(event) {
        if (event.button !== 0 || this.grids.length === 0 || this.press) {
            return;
        }

        const mesh = this.crateAt(event, (candidate) => this.editable(candidate));
        this.press = { mesh, x: event.clientX, y: event.clientY, wasSelected: mesh !== null && mesh === this.selected };

        if (mesh) {
            this.controls.enabled = mesh.userData.crate.placed === true;
            this.hideTooltip();
        }

        window.addEventListener('pointermove', this.onDragMove);
        window.addEventListener('pointerup', this.onDragEnd);
        window.addEventListener('pointercancel', this.onDragEnd);
        this.stageTarget.focus({ preventScroll: true });
    }

    stopPress() {
        window.removeEventListener('pointermove', this.onDragMove);
        window.removeEventListener('pointerup', this.onDragEnd);
        window.removeEventListener('pointercancel', this.onDragEnd);
        this.press = null;
        this.controls.enabled = true;
    }

    dragTo(event) {
        if (!this.press) {
            return;
        }

        if (!this.drag) {
            if (!this.press.mesh || this.press.mesh.userData.crate.placed || Math.hypot(event.clientX - this.press.x, event.clientY - this.press.y) < DRAG_THRESHOLD) {
                return;
            }

            this.startDrag(this.press.mesh);
        }

        this.lastEvent = event;
        this.aim(event);
    }

    startDrag(mesh) {
        this.drag = { mesh, turned: false, candidate: null, problem: null, swapWith: null };
        this.select(mesh);
        mesh.userData.lifted = true;
        this.paint(mesh);
        this.renderer.domElement.classList.add('is-dragging');
    }

    aim(event) {
        const { drag } = this;
        const crate = drag.mesh.userData.crate;
        const others = this.boxes(drag.mesh);
        const hit = event.shiftKey ? null : this.crateAt(event, (mesh) => mesh !== drag.mesh && this.editable(mesh));
        drag.swapWith = null;
        drag.problem = null;
        this.markSwap(null);

        if (hit && hit.userData.crate.size === crate.size) {
            const there = boxOf(hit.userData.crate);
            const problem = layoutProblem(this.grids, this.boxes(drag.mesh, hit), [there, boxOf(crate)]);

            if (problem === null) {
                drag.swapWith = hit;
                drag.candidate = there;
                this.markSwap(hit);
                this.showGhost(there, true);
                this.hideNotice();

                return;
            }
        }

        const dims = { width: drag.turned ? crate.length : crate.width, height: crate.height, length: drag.turned ? crate.width : crate.length, size: crate.size };
        const plane = new THREE.Plane(new THREE.Vector3(0, 1, 0), -((drag.candidate?.y ?? crate.y) + dims.height / 2));
        const point = this.raycaster.ray.intersectPlane(plane, new THREE.Vector3());

        if (!point) {
            return;
        }

        drag.candidate = snap(this.grids, others, dims, point.x, point.z);
        drag.problem = drag.candidate ? restProblem(this.grids, others, drag.candidate) : 'outside';

        if (drag.candidate) {
            this.showGhost(drag.candidate, drag.problem === null);
        }

        drag.problem ? this.say(this.labelsValue.problems?.[drag.problem], 'error', false) : this.hideNotice();
    }

    release(event) {
        const { press, drag } = this;
        this.stopPress();

        if (!press) {
            return;
        }

        if (drag) {
            this.finishDrag(event.type !== 'pointercancel');

            return;
        }

        if (event.type === 'pointercancel' || Math.hypot(event.clientX - press.x, event.clientY - press.y) >= DRAG_THRESHOLD) {
            return;
        }

        this.clickAt(press.mesh, press.wasSelected);
    }

    // Clic simple : sélectionne la caisse et verrouille la surbrillance de sa livraison ; second clic ou clic dans le vide : relâche.
    clickAt(mesh, wasSelected) {
        const release = mesh === null || (wasSelected && this.locked);
        this.select(release ? null : mesh);
        window.dispatchEvent(new CustomEvent('cargo:lock', { detail: { key: release ? null : mesh.userData.crate.key } }));

        if (!release) {
            track('crate-select');
        }
    }

    finishDrag(commit) {
        const { drag } = this;
        this.drag = null;
        drag.mesh.userData.lifted = false;
        this.paint(drag.mesh);
        this.markSwap(null);
        this.removeGhost();
        this.renderer.domElement.classList.remove('is-dragging');
        this.hideNotice();

        if (!commit || !drag.candidate) {
            return;
        }

        if (drag.problem) {
            this.say(this.labelsValue.problems?.[drag.problem], 'error');
        } else if (drag.swapWith) {
            this.swap(drag.mesh, drag.swapWith);
        } else if (!sameBox(drag.candidate, drag.mesh.userData.crate)) {
            this.move(drag.mesh, drag.candidate);
        }
    }

    select(mesh) {
        if (mesh === this.selected) {
            return;
        }

        const previous = this.selected;
        this.selected = mesh;

        [previous, mesh].filter(Boolean).forEach((crate) => {
            crate.userData.selected = crate === mesh;
            this.outline(crate);
            this.paint(crate);
        });

        this.applyTouchMode();
        this.updateTools();
        this.draw();
    }

    outline(mesh) {
        const { userData } = mesh;
        userData.outline?.geometry.dispose();
        userData.outline?.removeFromParent();
        userData.outline = null;

        if (!userData.selected) {
            return;
        }

        const { crate } = userData;
        const geometry = new THREE.EdgesGeometry(new THREE.BoxGeometry(crate.width + 0.14, crate.height + 0.14, crate.length + 0.14));
        userData.outline = new THREE.LineSegments(geometry, new THREE.LineBasicMaterial({ color: SELECT_COLOR }));
        mesh.add(userData.outline);
    }

    markSwap(mesh) {
        if (this.swapTarget === mesh) {
            return;
        }

        const previous = this.swapTarget;
        this.swapTarget = mesh;
        [previous, mesh].filter(Boolean).forEach((crate) => {
            crate.userData.swap = crate === mesh;
            this.paint(crate);
        });
        this.draw();
    }

    showGhost(box, valid) {
        const key = [box.width, box.height, box.length].join('x');

        if (!this.ghost || this.ghost.userData.key !== key) {
            this.removeGhost();
            const geometry = new THREE.BoxGeometry(box.width - CRATE_GAP, box.height - CRATE_GAP, box.length - CRATE_GAP);
            this.ghost = new THREE.Mesh(geometry, new THREE.MeshBasicMaterial({ transparent: true, opacity: 0.45, depthWrite: false, depthTest: false }));
            this.ghost.add(new THREE.LineSegments(new THREE.EdgesGeometry(geometry), new THREE.LineBasicMaterial({ depthTest: false })));
            this.ghost.renderOrder = 10;
            this.ghost.children[0].renderOrder = 10;
            this.ghost.userData.key = key;
            this.scene.add(this.ghost);
        }

        const color = valid ? this.accents.pickup : this.accents.stacked;
        this.ghost.material.color.copy(color);
        this.ghost.children[0].material.color.copy(color);
        this.ghost.position.set(box.x + box.width / 2, box.y + box.height / 2, box.z + box.length / 2);
        this.draw();
    }

    removeGhost() {
        if (this.ghost) {
            this.dispose(this.ghost);
            this.ghost.removeFromParent();
            this.ghost = null;
            this.draw();
        }
    }

    // Déplacement optimiste : la caisse bouge tout de suite, le serveur valide et renvoie le plan (ou refuse : `rejected`).
    move(mesh, box, record = true) {
        const crate = mesh.userData.crate;

        if (record) {
            this.undoStack.push({ kind: 'move', mission: crate.missionId, index: crate.index, box: boxOf(crate) });
        }

        this.relocate([[mesh, box]]);
        this.send('moveCrate', { mission: crate.missionId, index: crate.index, x: box.x, y: box.y, z: box.z, width: box.width, length: box.length });
        track('cargo-move');
    }

    swap(first, second, record = true) {
        const [one, two] = [first.userData.crate, second.userData.crate];

        if (record) {
            this.undoStack.push({ kind: 'swap', first: { mission: one.missionId, index: one.index }, second: { mission: two.missionId, index: two.index } });
        }

        this.relocate([[first, boxOf(two)], [second, boxOf(one)]]);
        this.send('swapCrates', { mission: one.missionId, index: one.index, otherMission: two.missionId, otherIndex: two.index });
        track('cargo-swap');
    }

    relocate(moves) {
        moves.forEach(([mesh]) => [...this.meshes].filter(([, value]) => value === mesh).forEach(([id]) => this.meshes.delete(id)));
        moves.forEach(([mesh, box]) => {
            const crate = { ...mesh.userData.crate, x: box.x, y: box.y, z: box.z, width: box.width, length: box.length };
            mesh.userData.crate = crate;
            this.meshes.set(crateId(crate), mesh);
            mesh.geometry.dispose();
            mesh.geometry = new THREE.BoxGeometry(crate.width - CRATE_GAP, crate.height - CRATE_GAP, crate.length - CRATE_GAP);
            mesh.children[0].geometry.dispose();
            mesh.children[0].geometry = new THREE.EdgesGeometry(mesh.geometry);
            mesh.userData.halo.geometry.dispose();
            mesh.userData.halo.geometry = haloGeometry(crate);
            mesh.position.set(crate.x + crate.width / 2, crate.y + crate.height / 2, crate.z + crate.length / 2);
            mesh.userData.restY = mesh.position.y;
            this.outline(mesh);
        });
        this.updateTools();
        this.draw();
    }

    async send(action, args) {
        const element = document.querySelector('[data-live-name-value="RoutePanel"]');

        if (element) {
            await (await getComponent(element)).action(action, args);
        }
    }

    rotate() {
        if (this.drag) {
            this.drag.turned = !this.drag.turned;
            this.lastEvent && this.aim(this.lastEvent);

            return;
        }

        const crate = this.selected?.userData.crate;

        if (!crate?.rotatable) {
            return;
        }

        const [dw, dl] = [crate.width - crate.length, crate.length - crate.width];
        const turned = { ...boxOf(crate), width: crate.length, length: crate.width };
        const options = [[0, 0], [dw, 0], [0, dl], [dw, dl]].map(([dx, dz]) => ({ ...turned, x: crate.x + dx, z: crate.z + dz }));
        this.tryMove(this.selected, options);
        track('cargo-rotate');
    }

    nudge(dx, dz) {
        const crate = this.selected?.userData.crate;

        if (crate) {
            this.tryMove(this.selected, [{ ...boxOf(crate), x: crate.x + dx, z: crate.z + dz }]);
        }
    }

    // Première option valide (posée sur ce qu'il y a dessous) ; sinon le motif du premier refus.
    tryMove(mesh, options) {
        const others = this.boxes(mesh);
        let firstProblem = null;

        for (const option of options) {
            const grid = nearestGrid(this.grids, option.x + option.width / 2, option.z + option.length / 2);
            const box = { ...option, y: restHeight(grid, others, option) };
            const problem = restProblem(this.grids, others, box);

            if (problem === null) {
                this.move(mesh, box);

                return;
            }

            firstProblem ??= problem;
        }

        this.say(this.labelsValue.problems?.[firstProblem], 'error');
    }

    undo() {
        const entry = this.undoStack.pop();
        const find = ({ mission, index }) => [...this.meshes.values()].find((mesh) => mesh.userData.crate.missionId === mission && mesh.userData.crate.index === index);

        if (!entry) {
            return;
        }

        if (entry.kind === 'move') {
            const mesh = find(entry);
            mesh && this.move(mesh, entry.box, false);
        } else {
            const [first, second] = [find(entry.first), find(entry.second)];
            first && second && this.swap(first, second, false);
        }

        track('cargo-undo');
        this.updateTools();
    }

    resetPlacement() {
        this.undoStack = [];
        this.send('resetPlacement', {});
        track('cargo-placement-reset');
    }

    resetMission() {
        const crate = this.selected?.userData.crate;

        if (crate) {
            this.undoStack = [];
            this.send('resetPlacement', { mission: crate.missionId });
            track('cargo-mission-reset');
        }
    }

    unlock() {
        window.dispatchEvent(new CustomEvent('cargo:lock', { detail: { key: null } }));
    }

    keyDown(event) {
        if (event.target.closest?.('input, select, textarea')) {
            return;
        }

        const key = event.key.toLowerCase();
        const onStage = this.stageTarget.contains(event.target);

        if (key === 'escape' && (this.drag || this.selected)) {
            this.drag ? this.finishDrag(false) : this.select(null);
        } else if ((key === 'enter' || key === 'v') && onStage && this.selected && event.target === this.stageTarget) {
            this.togglePlaced();
        } else if (key === 'r' && onStage) {
            this.rotate();
        } else if (key === 'z' && (event.ctrlKey || event.metaKey) && onStage) {
            this.undo();
        } else if (NUDGES[event.key] && onStage && this.selected && event.target === this.stageTarget) {
            this.nudge(...NUDGES[event.key]);
        } else {
            return;
        }

        event.preventDefault();
    }

    togglePlaced() {
        const mesh = this.selected;

        if (!mesh) {
            return;
        }

        const crate = mesh.userData.crate;
        mesh.userData.crate = { ...crate, placed: !crate.placed };
        this.paint(mesh);
        this.updateTools();
        this.draw();
        this.send('markCrate', { mission: crate.missionId, index: crate.index, placed: crate.placed ? 0 : 1 });
        track('cargo-placed');
    }

    updateTools() {
        const crate = this.selected?.userData.crate;
        const all = [...this.meshes.values()].filter((mesh) => !mesh.userData.leaving);
        const placed = all.filter((mesh) => mesh.userData.crate.placed).length;

        if (this.hasActionsTarget) {
            this.actionsTarget.hidden = !crate;
            this.placedTargets.forEach((button) => button.setAttribute('aria-pressed', String(crate?.placed === true)));
        }

        this.placedCountTargets.forEach((element) => {
            element.textContent = `${placed}/${all.length}`;
            element.closest('[data-placed-badge]')?.toggleAttribute('hidden', all.length === 0);
        });

        const manual = this.cargoPlan.manual ?? [];
        const states = { rotate: !crate?.rotatable || crate?.placed === true, undo: this.undoStack.length === 0, resetMission: !crate || !manual.includes(crate.missionId), resetAll: manual.length === 0 };

        Object.entries(states).forEach(([name, disabled]) => this.targets.findAll(name).forEach((button) => {
            button.disabled = disabled;
        }));
    }

    say(message, tone = 'warn', autohide = true) {
        if (!this.hasNoticeTarget || !message) {
            return;
        }

        clearTimeout(this.noticeTimer);
        this.noticeTarget.textContent = message;
        this.noticeTarget.dataset.tone = tone;
        this.noticeTarget.hidden = false;

        if (autohide) {
            this.noticeTimer = setTimeout(() => this.hideNotice(), NOTICE_DURATION);
        }
    }

    hideNotice() {
        if (this.hasNoticeTarget) {
            this.noticeTarget.hidden = true;
        }
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
        this.grids = groups.flatMap((group) => group.grids.map((grid) => ({ ...grid, minSize: grid.minSize ?? group.minSize, x: group.x + grid.x, z: group.z + grid.z })));
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
        this.updateTools();
    }

    crate(crate) {
        const geometry = new THREE.BoxGeometry(crate.width - CRATE_GAP, crate.height - CRATE_GAP, crate.length - CRATE_GAP);
        const mesh = new THREE.Mesh(geometry, new THREE.MeshBasicMaterial({ color: crate.color, transparent: true, depthWrite: !crate.phantom }));
        mesh.position.set(crate.x + crate.width / 2, crate.y + crate.height / 2, crate.z + crate.length / 2);
        mesh.add(new THREE.LineSegments(new THREE.EdgesGeometry(geometry), new THREE.LineBasicMaterial({ transparent: true })));
        const halo = new THREE.Mesh(haloGeometry(crate), new THREE.MeshBasicMaterial({ transparent: true, side: THREE.BackSide, depthWrite: false }));
        halo.renderOrder = -1;
        halo.visible = false;
        mesh.add(halo);
        const check = new THREE.Mesh(new THREE.PlaneGeometry(1, 1).rotateX(-Math.PI / 2), new THREE.MeshBasicMaterial({ map: checkTexture(), transparent: true, depthWrite: false }));
        check.renderOrder = 2;
        mesh.add(check);
        mesh.userData = { crate, halo, check, restY: mesh.position.y, appear: 1, dim: 0, leaving: false };

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

        if (this.drag?.mesh === mesh) {
            this.finishDrag(false);
        }

        if (this.selected === mesh) {
            this.select(null);
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

    // Bordure = couleur de la ressource (jamais vert / orange) ; l'état de l'arrêt est un halo : vert = à charger, orange = à décharger, rouge = empilée.
    paint(mesh) {
        const { crate, appear, dim, halo, check, lifted, swap, selected } = mesh.userData;
        const raised = this.pointed === mesh || selected === true;
        const pulseAccent = this.pulseAccent(crate);
        const pulse = pulseAccent ? this.pulse : 0;
        const haloAccent = swap ? this.accents.pickup : (crate.stacked ? this.accents.stacked : pulseAccent);
        const lit = this.hoveredKey !== null && crate.tokens.includes(this.hoveredKey);
        const base = crate.phantom ? 0.3 : 0.85;

        mesh.material.color.set(crate.color);
        if (lit && this.blink > 0) {
            mesh.material.color.lerp(this.palette.contrastColor, 0.4 * this.blink);
        }
        mesh.material.opacity = Math.min(1, (raised ? base + 0.1 : base) * appear * (1 - 0.75 * dim) * (lifted ? 0.3 : 1));

        const edges = mesh.children[0].material;
        edges.color.copy(this.edgeColor(crate.color));
        edges.opacity = appear * (1 - 0.8 * dim);

        check.visible = crate.placed === true;
        check.material.opacity = appear * (1 - 0.8 * dim);
        placeCheck(mesh);

        halo.visible = haloAccent !== null;
        if (halo.visible) {
            halo.material.color.copy(haloAccent);
            halo.material.opacity = Math.min(1, (0.2 + 0.3 * pulse + (lit ? 0.15 : 0)) * appear * (1 - 0.8 * dim));
        }

        if (!mesh.userData.leaving) {
            mesh.scale.setScalar(raised ? 1.04 : 1);
        }
    }

    // Bordure lisible sur le sol gris : couleur de la ressource, éclaircie si trop sombre (thème sombre), assombrie si trop claire.
    edgeColor(hex) {
        const color = new THREE.Color(hex);
        const luminance = 0.2126 * color.r + 0.7152 * color.g + 0.0722 * color.b;

        if (this.theme === 'dark') {
            return luminance < 0.12 ? color.lerp(new THREE.Color('#ffffff'), 0.5) : color.multiplyScalar(Math.min(1.35, 1 / Math.max(luminance, 0.01)));
        }

        return luminance > 0.7 ? color.multiplyScalar(0.55) : color.multiplyScalar(0.8);
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

    // Finger: one-finger vertical swipes scroll the page (touch-action: pan-y), sideways swipes orbit, two fingers zoom and tilt.
    applyTouchMode() {
        const touch = this.coarse.matches;
        this.renderer.domElement.style.touchAction = touch && !this.selected ? 'pan-y' : 'none';
        this.controls.touches = { ONE: THREE.TOUCH.ROTATE, TWO: touch ? THREE.TOUCH.DOLLY_ROTATE : THREE.TOUCH.DOLLY_PAN };
        this.element.classList.toggle('is-touch', touch);
    }

    // No hover on a touchscreen: a tap on a crate shows its tooltip, a tap elsewhere hides it.
    tapAt(event) {
        if (event.pointerType !== 'touch' || !this.downAt || Math.hypot(event.clientX - this.downAt[0], event.clientY - this.downAt[1]) > 8) {
            return;
        }

        this.pointAt(event, true);
    }

    pointAt(event, tap = false) {
        if (event.buttons !== 0 && !tap) {
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
        if (!this.locked && key !== this.hoveredKey) {
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

        const share = window.innerWidth < 1200 ? 0.6 : 0.7;
        const height = Math.round(Math.max(220, Math.min(width * (width < 576 ? 1.15 : 0.85), window.innerHeight * share)));

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
            this.placeActions();
        }
    }

    // La barre d'actions suit la caisse sélectionnée : elle est ancrée au-dessus de son dessus, projeté à l'écran.
    placeActions() {
        if (!this.hasActionsTarget || !this.selected) {
            return;
        }

        const { crate } = this.selected.userData;
        const spot = point(crate.x + crate.width / 2, crate.y + crate.height, crate.z + crate.length / 2).project(this.camera);
        const bar = this.actionsTarget;
        const x = Math.min(Math.max(((spot.x + 1) / 2) * this.size.width, bar.offsetWidth / 2 + 4), this.size.width - bar.offsetWidth / 2 - 4);
        const y = Math.min(Math.max(((1 - spot.y) / 2) * this.size.height - 10, bar.offsetHeight + 4), this.size.height - 4);
        bar.style.transform = `translate(${x}px, ${y}px) translate(-50%, -100%)`;
    }

    dispose(root) {
        root.traverse((object) => {
            object.geometry?.dispose();
            object.material?.dispose();
        });
    }
}
