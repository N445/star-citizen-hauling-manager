import { Controller } from '@hotwired/stimulus';
import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';

const FLOOR_COLOR = 0x8a9097;
const FLOOR_LINE_COLOR = 0xa9aeb4;
const OUTLINE_COLOR = 0x5c636a;
const DOOR_COLOR = 0xe8890c;
const UNLOADING_COLOR = 0xe8890c;
const ARROW_LENGTH = 3;
const ARROW_GAP = 0.4;

const range = (count) => Array.from({ length: count + 1 }, (_, index) => index);
const point = (x, y, z) => new THREE.Vector3(x, y, z);

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

export default class extends Controller {
    static targets = ['canvas', 'empty'];
    static values = { groups: Array };

    connect() {
        this.grids = [];
        this.cargoPlan = { doors: ['rear'], crates: [] };
        this.crateMeshes = [];
        this.hoveredKey = null;
        this.scene = new THREE.Scene();
        this.camera = new THREE.PerspectiveCamera(40, 1, 0.1, 2000);
        this.renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
        this.renderer.setPixelRatio(window.devicePixelRatio);
        this.canvasTarget.append(this.renderer.domElement);
        this.controls = new OrbitControls(this.camera, this.renderer.domElement);
        this.controls.addEventListener('change', () => this.draw());
        this.raycaster = new THREE.Raycaster();
        this.renderer.domElement.addEventListener('pointermove', (event) => this.pointAt(event));
        this.renderer.domElement.addEventListener('pointerleave', () => this.announce(null));
        this.resizeObserver = new ResizeObserver(() => this.resize());
        this.resizeObserver.observe(this.canvasTarget);
        this.readInitialPlan();
        this.useGroups(this.groupsValue);
    }

    disconnect() {
        this.resizeObserver.disconnect();
        this.controls.dispose();
        this.clearScene();
        this.renderer.dispose();
    }

    show({ target, detail: { option } }) {
        if (!target.matches('[data-ship-name]')) {
            return;
        }

        this.useGroups(option?.cargoGroups ?? []);
    }

    plan({ detail }) {
        this.cargoPlan = detail;
        this.rebuild();
    }

    highlight({ detail: { key } }) {
        this.hoveredKey = key;
        this.crateMeshes.forEach((mesh) => this.paint(mesh));
        this.draw();
    }

    readInitialPlan() {
        const planElement = document.querySelector('[data-cargo-plan-plan-value]');

        if (planElement) {
            this.cargoPlan = JSON.parse(planElement.dataset.cargoPlanPlanValue);
        }
    }

    useGroups(groups) {
        this.grids = groups.flatMap((group) => group.grids.map((grid) => ({ ...grid, x: group.x + grid.x, z: group.z + grid.z })));
        this.emptyTarget.hidden = this.grids.length > 0;
        this.canvasTarget.hidden = this.grids.length === 0;
        this.rebuild();
        this.frame();
        this.resize();
    }

    rebuild() {
        this.clearScene();
        this.grids.forEach((grid) => this.scene.add(this.floor(grid)));
        this.crateMeshes = this.grids.length === 0 ? [] : this.cargoPlan.crates.map((crate) => this.crate(crate));
        this.crateMeshes.forEach((mesh) => this.scene.add(mesh));
        this.addDoor();
        this.draw();
    }

    floor(grid) {
        const floor = new THREE.Group();
        floor.position.set(grid.x, grid.y, grid.z);
        const plane = new THREE.Mesh(boxGeometry(grid.width, 0.02, grid.length), new THREE.MeshBasicMaterial({ color: FLOOR_COLOR, transparent: true, opacity: 0.15, depthWrite: false }));
        const cells = new THREE.LineSegments(new THREE.BufferGeometry().setFromPoints(floorLines(grid)), new THREE.LineBasicMaterial({ color: FLOOR_LINE_COLOR, transparent: true, opacity: 0.6 }));
        const outline = new THREE.LineSegments(new THREE.EdgesGeometry(boxGeometry(grid.width, 0, grid.length)), new THREE.LineBasicMaterial({ color: OUTLINE_COLOR }));
        floor.add(plane, cells, outline);

        return floor;
    }

    crate(crate) {
        const geometry = boxGeometry(crate.width - 0.08, crate.height - 0.08, crate.length - 0.08);
        const mesh = new THREE.Mesh(geometry, new THREE.MeshBasicMaterial({ color: crate.color, transparent: true, depthWrite: !crate.phantom }));
        mesh.position.set(crate.x + 0.04, crate.y + 0.04, crate.z + 0.04);
        mesh.userData = crate;
        mesh.add(new THREE.LineSegments(new THREE.EdgesGeometry(geometry), new THREE.LineBasicMaterial({ transparent: true })));
        this.paint(mesh);

        return mesh;
    }

    paint(mesh) {
        const crate = mesh.userData;
        const dimmed = this.hoveredKey !== null && !crate.tokens.includes(this.hoveredKey);
        const baseOpacity = crate.phantom ? 0.25 : 0.85;
        mesh.material.opacity = dimmed ? baseOpacity * 0.25 : baseOpacity;
        const edges = mesh.children[0].material;
        edges.color.set(crate.unloading ? UNLOADING_COLOR : new THREE.Color(crate.color).multiplyScalar(0.55));
        edges.opacity = dimmed ? 0.2 : 1;
    }

    addDoor() {
        if (this.grids.length === 0) {
            return;
        }

        const bounds = this.gridBounds();
        const center = bounds.getCenter(new THREE.Vector3());
        (this.cargoPlan.doors ?? ['rear']).forEach((door) => {
            const { shape, at } = (doorArrows[door] ?? doorArrows.rear)(bounds, center);
            const arrow = new THREE.Mesh(shape(doubleArrow()), new THREE.MeshBasicMaterial({ color: DOOR_COLOR, side: THREE.DoubleSide }));
            arrow.position.set(...at);
            this.scene.add(arrow);
        });
    }

    gridBounds() {
        return this.grids.reduce(
            (bounds, grid) => bounds.union(new THREE.Box3(point(grid.x, grid.y, grid.z), point(grid.x + grid.width, grid.y + grid.height, grid.z + grid.length))),
            new THREE.Box3(),
        );
    }

    pointAt(event) {
        const area = this.renderer.domElement.getBoundingClientRect();
        const pointer = new THREE.Vector2(((event.clientX - area.left) / area.width) * 2 - 1, -((event.clientY - area.top) / area.height) * 2 + 1);
        this.raycaster.setFromCamera(pointer, this.camera);
        const [hit] = this.raycaster.intersectObjects(this.crateMeshes, false);
        this.renderer.domElement.title = hit?.object.userData.label ?? '';
        this.announce(hit?.object.userData.key ?? null);
    }

    announce(key) {
        if (key === this.hoveredKey) {
            return;
        }

        window.dispatchEvent(new CustomEvent('cargo:highlight', { detail: { key } }));
    }

    frame() {
        if (this.grids.length === 0) {
            return;
        }

        const bounds = this.gridBounds();
        const center = bounds.getCenter(new THREE.Vector3());
        const size = bounds.getSize(new THREE.Vector3()).length();
        this.camera.position.copy(center).add(point(size * 0.7, size * 0.6, size * 0.7));
        this.controls.target.copy(center);
        this.controls.update();
    }

    resize() {
        const width = this.canvasTarget.clientWidth;

        if (width === 0) {
            return;
        }

        const height = Math.min(width * 0.85, window.innerHeight * 0.7);
        this.renderer.setSize(width, height);
        this.camera.aspect = width / height;
        this.camera.updateProjectionMatrix();
        this.draw();
    }

    draw() {
        this.renderer.render(this.scene, this.camera);
    }

    clearScene() {
        this.scene.traverse((object) => {
            object.geometry?.dispose();
            object.material?.dispose();
        });
        this.scene.clear();
    }
}
