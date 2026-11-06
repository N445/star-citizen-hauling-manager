import { Controller } from '@hotwired/stimulus';
import { getComponent } from '@symfony/ux-live-component';

/**
 * Drag and drop of crates in the displayed layer of the cargo grid.
 *
 * The valid positions of the dragged crate are fetched once when the drag starts; hover and drop
 * validation then run in the browser, and the server re-validates the move (LiveAction moveCrate).
 */
export default class extends Controller {
    static targets = ['grid'];
    static values = { layer: Number, height: Number };

    connect() {
        this.onDown = (event) => this.start(event);
        this.element.addEventListener('pointerdown', this.onDown);
    }

    disconnect() {
        this.element.removeEventListener('pointerdown', this.onDown);
        this.cleanup();
    }

    stepper() {
        return this.element.closest('[data-live-name-value="RouteStepper"]');
    }

    input() {
        try {
            return JSON.parse(this.stepper().dataset.livePropsValue).input;
        } catch {
            return null;
        }
    }

    async start(event) {
        const crateEl = event.target.closest?.('.crate');
        if (this.drag || !crateEl || !this.hasGridTarget || (event.pointerType === 'mouse' && event.button !== 0)) {
            return;
        }
        const input = this.input();
        if (!input) {
            return;
        }
        event.preventDefault();

        const grid = this.gridTarget;
        const rect = grid.getBoundingClientRect();
        const cols = Number(grid.dataset.cols);
        const rows = Number(grid.dataset.rows);
        const cell = rect.width / cols;
        const crateRect = crateEl.getBoundingClientRect();
        const d = crateEl.dataset;
        const drag = {
            id: d.crate,
            pointerId: event.pointerId,
            cell, cols, rows,
            orientation: Number(d.o),
            w: Number(d.w), l: Number(d.l), h: Number(d.h),
            z: Number(d.z), x: Number(d.x), y: Number(d.y),
            gx: Math.min(Number(d.w) - 1, Math.max(0, Math.floor((event.clientX - crateRect.left) / cell))),
            gy: Math.min(Number(d.l) - 1, Math.max(0, Math.floor((event.clientY - crateRect.top) / cell))),
            positions: null,
            target: null,
            checkTimer: null,
            input,
        };
        this.drag = drag;
        crateEl.classList.add('dragging');
        drag.crateEl = crateEl;

        this.buildUi();
        this.moveListener = (e) => this.move(e);
        this.upListener = (e) => this.end(e);
        this.keyListener = (e) => this.key(e);
        document.addEventListener('pointermove', this.moveListener);
        document.addEventListener('pointerup', this.upListener);
        document.addEventListener('pointercancel', this.upListener);
        document.addEventListener('keydown', this.keyListener);
        this.update(event);

        try {
            const response = await fetch('/placement/positions', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ input, crate: drag.id }),
            });
            if (!response.ok) {
                throw new Error('positions');
            }
            const data = await response.json();
            if (this.drag !== drag) {
                return;
            }
            drag.footprints = data.footprints;
            drag.count = data.count;
            drag.positions = new Map();
            for (const o of data.orientations) {
                const byCell = new Map();
                for (const [x, y, z] of o.cells) {
                    const key = `${x},${y}`;
                    if (!byCell.has(key)) {
                        byCell.set(key, []);
                    }
                    byCell.get(key).push(z);
                }
                drag.positions.set(o.o, { w: o.w, l: o.l, h: o.h, byCell });
            }
            this.drawValid();
            this.update(this.lastEvent ?? event);
        } catch {
            this.message('Impossible de charger les emplacements valides.');
        }
    }

    buildUi() {
        const grid = this.gridTarget;
        this.ghost = document.createElement('div');
        this.ghost.className = 'dnd-ghost';
        grid.appendChild(this.ghost);

        this.canvas = document.createElement('canvas');
        this.canvas.className = 'dnd-valid';
        this.canvas.width = grid.clientWidth;
        this.canvas.height = grid.clientHeight;
        grid.appendChild(this.canvas);

        this.bar = document.createElement('div');
        this.bar.className = 'dnd-bar';
        this.bar.innerHTML = '<button type="button" class="small" data-role="rotate">Pivoter (R)</button><span class="dnd-msg" role="status"></span>';
        this.bar.querySelector('[data-role=rotate]').addEventListener('pointerdown', (e) => {
            e.stopPropagation();
            e.preventDefault();
            this.rotate();
        });
        this.element.insertBefore(this.bar, this.element.firstChild);
    }

    cleanup() {
        const drag = this.drag;
        this.drag = null;
        clearTimeout(drag?.checkTimer);
        drag?.crateEl?.classList.remove('dragging');
        this.ghost?.remove();
        this.canvas?.remove();
        this.bar?.remove();
        this.ghost = this.canvas = this.bar = null;
        document.removeEventListener('pointermove', this.moveListener);
        document.removeEventListener('pointerup', this.upListener);
        document.removeEventListener('pointercancel', this.upListener);
        document.removeEventListener('keydown', this.keyListener);
    }

    message(text) {
        const el = this.bar?.querySelector('.dnd-msg');
        if (el) {
            el.textContent = text ?? '';
        }
    }

    dims() {
        const drag = this.drag;
        const f = drag.footprints?.[drag.orientation];

        return f ? { w: f.w, l: f.l, h: f.h } : { w: drag.w, l: drag.l, h: drag.h };
    }

    rotate() {
        const drag = this.drag;
        if (!drag || !drag.count) {
            return;
        }
        drag.orientation = (drag.orientation + 1) % drag.count;
        const { w, l } = this.dims();
        drag.gx = Math.min(drag.gx, w - 1);
        drag.gy = Math.min(drag.gy, l - 1);
        this.drawValid();
        this.update(this.lastEvent);
    }

    key(event) {
        if (event.key === 'r' || event.key === 'R') {
            event.preventDefault();
            this.rotate();
        } else if (event.key === 'Escape') {
            this.cleanup();
        }
    }

    move(event) {
        if (this.drag && event.pointerId === this.drag.pointerId) {
            event.preventDefault();
            this.update(event);
        }
    }

    /** Target origin (x, y) in grid cells, from the pointer and the grab offset. */
    update(event) {
        const drag = this.drag;
        if (!drag || !event) {
            return;
        }
        this.lastEvent = event;
        const rect = this.gridTarget.getBoundingClientRect();
        const x = Math.floor((event.clientX - rect.left) / drag.cell) - drag.gx;
        const y = Math.floor((event.clientY - rect.top) / drag.cell) - drag.gy;
        const { w, l, h } = this.dims();
        const layer = this.layerValue;

        let z = null;
        let known = false;
        const info = drag.positions ? (drag.positions.get(drag.orientation) ?? { byCell: new Map(), h }) : null;
        if (info) {
            known = true;
            const zs = (info.byCell.get(`${x},${y}`) ?? []).filter((candidate) => candidate <= layer && layer < candidate + h);
            if (zs.length) {
                z = zs.reduce((best, c) => (Math.abs(c - drag.z) < Math.abs(best - drag.z) ? c : best));
            }
        }
        drag.target = { x, y, z, w, l, h, valid: z !== null, known };

        this.ghost.style.cssText = `left:${x * drag.cell}px;top:${y * drag.cell}px;width:${w * drag.cell}px;height:${l * drag.cell}px;`;
        this.ghost.className = `dnd-ghost ${known ? (z !== null ? 'valid' : 'invalid') : ''}`;

        if (known && z === null) {
            this.scheduleCheck(x, y, Math.max(0, Math.min(layer, this.heightValue - h)));
        } else {
            clearTimeout(drag.checkTimer);
            this.message(known ? '' : 'Chargement des emplacements…');
        }
    }

    scheduleCheck(x, y, z) {
        const drag = this.drag;
        clearTimeout(drag.checkTimer);
        const key = `${x},${y},${z},${drag.orientation}`;
        drag.checkTimer = setTimeout(async () => {
            try {
                const response = await fetch('/placement/check', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ input: drag.input, crate: drag.id, x, y, z, o: drag.orientation }),
                });
                const data = await response.json();
                if (this.drag === drag && drag.target && !drag.target.valid) {
                    this.message(data.reason ?? 'Emplacement refusé.');
                }
            } catch {
                this.message('Emplacement refusé.');
            }
        }, 140);
        drag.lastCheck = key;
    }

    drawValid() {
        const drag = this.drag;
        const ctx = this.canvas?.getContext('2d');
        if (!ctx) {
            return;
        }
        ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        const info = drag.positions?.get(drag.orientation);
        if (!info) {
            return;
        }
        ctx.fillStyle = 'rgba(57, 217, 138, 0.22)';
        const layer = this.layerValue;
        for (const [key, zs] of info.byCell) {
            if (!zs.some((z) => z <= layer && layer < z + info.h)) {
                continue;
            }
            const [x, y] = key.split(',').map(Number);
            ctx.fillRect(x * drag.cell + 1, y * drag.cell + 1, drag.cell - 2, drag.cell - 2);
        }
    }

    async end(event) {
        const drag = this.drag;
        if (!drag || event.pointerId !== drag.pointerId) {
            return;
        }
        const target = drag.target;
        const cancelled = event.type === 'pointercancel';
        this.cleanup();
        if (cancelled || !target || !target.valid) {
            return;
        }
        if (target.x === drag.x && target.y === drag.y && target.z === drag.z && drag.orientation === Number(drag.crateEl.dataset.o)) {
            return;
        }
        try {
            const component = await getComponent(this.stepper());
            await component.action('moveCrate', { crate: drag.id, x: target.x, y: target.y, z: target.z, orientation: drag.orientation });
        } catch {
            // the server refusal is shown by the component notice
        }
    }
}
