export default (catalog, checkoutUrl, sizes = []) => ({
    products: catalog, // { id, name, price, stock, threshold, image, art } (live stock lives here)
    sizes, // [{ key, label, pcs, mixable, art, flavors: [{ product_id, flavor, unit_pcs, exclusive, price }] }]; price = one tray / one Solo
    search: '',
    bundles: [], // every order line (one flavor or a mix): { id, size, pcs, qty, parts: [{ product_id, flavor, pieces, cents }], unitCents }
    bundleSeq: 0,
    sizeOpen: false,
    sizeKey: null, // the size card that is open
    picked: [], // product ids ticked in the open size
    trayQty: 1,
    limitHit: false, // tried to tick more flavors than the tray allows
    pieces: {}, // product id => pieces in the open mix (the cashier can change them)
    paid: 0,
    method: 'cash',
    busy: false,
    message: null, // { type: 'success' | 'error', text, saleId? }

    // ---- helpers ---------------------------------------------------------
    productById(id) {
        return this.products.find((p) => p.id === id);
    },

    get shownSizes() {
        const q = this.search.trim().toLowerCase();
        return this.sizes.filter((s) => q === '' || s.label.toLowerCase().includes(q) || s.flavors.some((f) => f.flavor.toLowerCase().includes(q)));
    },

    isLow(p) {
        return p.stock > 0 && p.stock <= p.threshold;
    },

    requirements(part) {
        const product = this.productById(part.product_id);
        if (!product.recipe) return { [product.id]: product.pieces ? part.pieces : 1 };
        const ids = Object.keys(product.recipe);
        return ids.length === 1 ? { [ids[0]]: part.pieces } : product.recipe;
    },

    available(stockId) {
        const stock = this.productById(Number(stockId))?.stock ?? 0;
        return this.bundles.reduce((left, tray) => left - tray.qty * tray.parts.reduce(
            (used, part) => used + (this.requirements(part)[stockId] ?? 0), 0
        ), stock);
    },

    capacity(part) {
        return Math.min(...Object.entries(this.requirements(part)).map(
            ([id, needed]) => Math.floor(this.available(id) / needed)
        ));
    },

    availableText(f) {
        const product = this.productById(f.product_id);
        const recipe = product.recipe;
        if (!recipe && product.pieces) return this.available(product.id) + ' pcs left';
        if (recipe && Object.keys(recipe).length === 1) {
            return this.available(Object.keys(recipe)[0]) + ' pcs left';
        }
        return this.capacity({ ...f, pieces: f.unit_pcs }) + ' orders available';
    },

    // ---- size cards ------------------------------------------------------
    get size() {
        return this.sizes.find((s) => s.key === this.sizeKey) || null;
    },

    sizeFrom(s) {
        const prices = s.flavors.filter((f) => f.price > 0 && this.productById(f.product_id)).map((f) => f.price);
        return prices.length ? Math.min(...prices) : 0;
    },

    sizeLeft(s) {
        if (s.flavors.some(f => this.capacity({ ...f, pieces: f.sized ? f.unit_pcs : s.pcs }) > 0)) return 1;
        const available = s.flavors.filter(f => !f.exclusive).map(f => {
            const product = this.productById(f.product_id);
            if (product.recipe) return this.available(Object.keys(product.recipe)[0]);
            return product.pieces ? this.available(product.id) : 0;
        }).filter(pieces => pieces >= s.min_pieces);
        return s.mixable && available.length >= 2 && available.reduce((a, b) => a + b, 0) >= s.pcs ? 1 : 0;
    },

    sizeCount(s) {
        return this.bundles.filter((b) => b.size === s.label).reduce((n, b) => n + b.qty, 0);
    },

    openSize(s) {
        this.message = null;
        this.sizeKey = s.key;
        this.picked = [];
        this.pieces = {};
        this.limitHit = false;
        this.trayQty = 1;
        this.sizeOpen = true;
    },

    closeSize() {
        this.sizeOpen = false;
    },

    isPicked(f) {
        return this.picked.includes(f.product_id);
    },

    flavorOpen(f) {
        const pieces = this.size.mixable && !f.exclusive ? this.size.min_pieces : (f.sized ? f.unit_pcs : this.size.pcs);
        return this.isPicked(f) || this.capacity({ ...f, pieces }) > 0;
    },

    // tick / untick a flavor. Solo and the ready-made Mix take one flavor only; trays can mix.
    toggle(f) {
        if (this.isPicked(f)) {
            this.limitHit = false;
            this.picked = this.picked.filter((id) => id !== f.product_id);
        } else if (!this.flavorOpen(f)) {
            return;
        } else if (!this.size.mixable || f.exclusive) {
            this.picked = [f.product_id];
        } else {
            const ready = this.size.flavors.filter((x) => x.exclusive).map((x) => x.product_id);
            const rest = this.picked.filter((id) => !ready.includes(id));
            if (rest.length >= this.size.max_flavors) {
                this.limitHit = true; // each flavor in a mix needs at least min_pieces pcs
                return;
            }
            this.picked = [...rest, f.product_id];
        }
        this.limitHit = false;
        this.splitEvenly();
        this.fixQty();
    },

    // ticked flavors in menu order
    get chosen() {
        return this.size ? this.size.flavors.filter((f) => this.picked.includes(f.product_id)) : [];
    },

    get isMixed() {
        return this.chosen.length >= 2;
    },

    // start (and "Split evenly"): the size's pieces shared between the ticked flavors, leftovers to the first ones (25 / 2 = 13 + 12)
    splitEvenly() {
        const n = this.chosen.length;
        const next = {};
        if (n >= 2) {
            const base = Math.floor(this.size.pcs / n);
            this.chosen.forEach((f, i) => {
                next[f.product_id] = base + (i < this.size.pcs % n ? 1 : 0);
            });
        }
        this.pieces = next;
    },

    // pieces of a ticked flavor in the mix (0 when not mixing)
    pieceFor(f) {
        if (!this.isMixed || !this.isPicked(f)) return 0;
        return Math.floor(Number(this.pieces[f.product_id]) || 0);
    },

    // the cashier typed a number: keep it a whole number from the minimum up to what the others leave
    fixPieces(f) {
        const min = this.size.min_pieces;
        const max = this.size.pcs - min * (this.chosen.length - 1);
        const n = Math.floor(Number(this.pieces[f.product_id]) || 0);
        this.pieces = { ...this.pieces, [f.product_id]: Math.max(min, Math.min(n, max)) };
    },

    bumpPieces(f, by) {
        this.pieces = { ...this.pieces, [f.product_id]: this.pieceFor(f) + by };
        this.fixPieces(f);
    },

    get assigned() {
        return this.chosen.reduce((n, f) => n + this.pieceFor(f), 0);
    },

    // pieces still to give out (negative = too many)
    get remaining() {
        return this.size ? this.size.pcs - this.assigned : 0;
    },

    get piecesOk() {
        if (!this.isMixed) return true;
        return this.remaining === 0 && this.chosen.every((f) => this.pieceFor(f) >= this.size.min_pieces);
    },

    // price of `pieces` pieces of this flavor in a mix, after the size's discount, in centavos.
    // Whole pesos, same formula as the server (BundleService::resolve).
    pieceCents(f, pieces) {
        const p = this.productById(f.product_id);
        if (!p) return 0;
        const off = this.size ? this.size.discount : 0;
        return Math.round((Math.round(p.price * 100) * pieces * (100 - off)) / (f.unit_pcs * 10000)) * 100;
    },

    // price of ONE tray / one Solo, in centavos: one flavor = that flavor's price for the size, a mix = the sum of its parts
    get unitCents() {
        if (this.chosen.length === 0) return 0;
        if (!this.isMixed) return Math.round(this.chosen[0].price * 100);
        return this.chosen.reduce((n, f) => n + this.pieceCents(f, this.pieceFor(f)), 0);
    },

    get trayCents() {
        return this.unitCents * this.trayQty;
    },

    // most that can be added: what the least-stocked ticked flavor still has
    get trayMax() {
        const lefts = this.chosen.map((f) => this.capacity({
            ...f, pieces: this.isMixed ? this.pieceFor(f) : (f.sized ? f.unit_pcs : this.size.pcs),
        }));
        return lefts.length ? Math.max(0, Math.min(...lefts, 99)) : 0;
    },

    fixQty() {
        const n = Math.floor(Number(this.trayQty) || 1);
        this.trayQty = Math.max(1, Math.min(n, Math.max(1, this.trayMax)));
    },

    bumpQty(by) {
        this.trayQty = Number(this.trayQty) || 1;
        this.trayQty += by;
        this.fixQty();
    },

    get canAddTray() {
        return this.chosen.length > 0 && this.trayMax >= 1 && this.piecesOk;
    },

    addTray() {
        if (!this.canAddTray) return;
        this.fixQty();
        this.message = null;
        const mixed = this.isMixed;
        const parts = this.chosen.map((f) => {
            const pieces = mixed ? this.pieceFor(f) : (f.sized ? f.unit_pcs : this.size.pcs);
            return { product_id: f.product_id, flavor: f.flavor, pieces, cents: mixed ? this.pieceCents(f, pieces) : Math.round(f.price * 100) };
        });
        this.bundles.push({ id: ++this.bundleSeq, size: this.size.label, pcs: parts.reduce((sum, part) => sum + part.pieces, 0), qty: this.trayQty, parts, unitCents: this.unitCents });
        this.sizeOpen = false;
    },

    // ---- mixed trays already in the order -------------------------------
    bundleCents(b) {
        return b.unitCents * b.qty;
    },

    canIncBundle(b) {
        return b.qty < 99 && b.parts.every((x) => this.capacity(x) > 0);
    },

    incBundle(b) {
        if (this.canIncBundle(b)) b.qty++;
    },

    decBundle(b) {
        if (b.qty > 1) {
            b.qty--;
        } else {
            this.removeBundle(b);
        }
    },

    removeBundle(b) {
        this.bundles = this.bundles.filter((x) => x !== b);
    },

    clear() {
        this.bundles = [];
        this.paid = 0;
    },

    // ---- money (integer centavos so the math is exact) -----------------
    get totalCents() {
        return this.bundles.reduce((sum, b) => sum + b.unitCents * b.qty, 0);
    },

    get total() {
        return this.totalCents / 100;
    },

    get paidCents() {
        return Math.round((Number(this.paid) || 0) * 100);
    },

    get change() {
        return Math.max(0, this.paidCents - this.totalCents) / 100;
    },

    get shortBy() {
        return Math.max(0, this.totalCents - this.paidCents) / 100;
    },

    get itemCount() {
        return this.bundles.reduce((n, b) => n + b.qty, 0);
    },

    get canConfirm() {
        return this.bundles.length > 0 && this.paidCents >= this.totalCents && !this.busy;
    },

    quick(amount) {
        this.paid = amount;
    },

    exact() {
        this.paid = this.total;
    },

    // ---- checkout -----------------------------------------------------
    async confirm() {
        if (!this.canConfirm) return;
        this.busy = true;
        this.message = null;

        try {
            const res = await fetch(checkoutUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                },
                body: JSON.stringify({
                    bundles: this.bundles.map((b) => ({ size: b.size, qty: b.qty, parts: b.parts.map((x) => ({ product_id: x.product_id, pieces: x.pieces })) })),
                    amount_paid: this.paid,
                    payment_method: this.method,
                }),
            });
            const data = await res.json();

            if (res.ok && data.ok) {
                this.clear();
                window.location.replace(data.pos_url);
            } else {
                // Keep the cart so the cashier can fix it
                const firstError = data.errors ? Object.values(data.errors)[0][0] : null;
                this.message = { type: 'error', text: data.message || firstError || 'Could not save the sale.' };
            }
        } catch (e) {
            this.message = { type: 'error', text: 'Connection interrupted. Check sales history before retrying.' };
        } finally {
            this.busy = false;
        }
    },
});
