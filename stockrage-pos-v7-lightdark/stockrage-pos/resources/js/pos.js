/**
 * POS cart logic (Alpine.js).
 * The server re-calculates the total from database prices, so nothing
 * here is trusted. This only makes the screen fast and friendly.
 */
export default (initialProducts, checkoutUrl) => ({
    products: initialProducts,
    search: '',
    category: 'All',
    cart: [],
    paid: 0,
    method: 'cash',
    busy: false,
    message: null, // { type: 'success' | 'error', text, saleId? }

    // ---- product grid -------------------------------------------------
    get categories() {
        const set = new Set(this.products.map((p) => p.category).filter(Boolean));
        return ['All', ...Array.from(set).sort()];
    },

    get filtered() {
        const q = this.search.trim().toLowerCase();
        return this.products.filter(
            (p) =>
                (this.category === 'All' || p.category === this.category) &&
                (q === '' || p.name.toLowerCase().includes(q)),
        );
    },

    get catalog() {
        const groups = new Map();
        for (const product of this.filtered) {
            const match = product.category === 'Takoyaki' &&
                product.name.match(/^(.+) - ((?:Solo|Barkada|Family Tray|Party Tray) \(\d+ pcs\))$/);
            const key = match ? `flavor:${match[1]}` : product.id;
            if (!groups.has(key)) {
                groups.set(key, { key, name: match ? match[1] : product.name, sizes: [] });
            }
            groups.get(key).sizes.push({ product, label: match ? match[2] : null });
        }
        return [...groups.values()];
    },

    isLow(p) {
        return p.stock > 0 && p.stock <= p.threshold;
    },

    inCart(p) {
        const row = this.cart.find((c) => c.product_id === p.id);
        return row ? row.qty : 0;
    },

    left(p) {
        return p.stock - this.inCart(p);
    },

    // ---- cart ---------------------------------------------------------
    add(p, qty = 1) {
        this.message = null;
        if (this.busy || !Number.isInteger(qty) || qty < 1 || qty > this.left(p)) return;
        const found = this.cart.find((c) => c.product_id === p.id);
        if (found) {
            found.qty += qty;
        } else {
            this.cart.push({ product_id: p.id, name: p.name, price: p.price, qty });
        }
    },

    stockOf(item) {
        const p = this.products.find((x) => x.id === item.product_id);
        return p ? p.stock : 0;
    },

    inc(item) {
        if (item.qty < this.stockOf(item)) item.qty++;
    },

    dec(item) {
        if (item.qty > 1) {
            item.qty--;
        } else {
            this.remove(item);
        }
    },

    remove(item) {
        this.cart = this.cart.filter((c) => c !== item);
    },

    clear() {
        this.cart = [];
        this.paid = 0;
    },

    // ---- money (integer centavos so the math is exact) -----------------
    get totalCents() {
        return this.cart.reduce((sum, c) => sum + Math.round(c.price * 100) * c.qty, 0);
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
        return this.cart.reduce((n, c) => n + c.qty, 0);
    },

    get canConfirm() {
        return this.cart.length > 0 && this.paidCents >= this.totalCents && !this.busy;
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
                    items: this.cart.map((c) => ({ product_id: c.product_id, qty: c.qty })),
                    amount_paid: this.paid,
                    payment_method: this.method,
                }),
            });
            const data = await res.json();

            if (res.ok && data.ok) {
                // Reflect the new stock on the grid without reloading
                Object.entries(data.stocks).forEach(([id, stock]) => {
                    const p = this.products.find((x) => x.id === Number(id));
                    if (p) p.stock = stock;
                });
                this.message = {
                    type: 'success',
                    saleId: data.sale_id,
                    text: `Sale #${data.sale_id} saved. Change: ${window.peso(data.change)}`,
                };
                this.cart = [];
                this.paid = 0;
            } else {
                // Keep the cart so the cashier can fix it
                const firstError = data.errors ? Object.values(data.errors)[0][0] : null;
                this.message = { type: 'error', text: data.message || firstError || 'Could not save the sale.' };
            }
        } catch (e) {
            this.message = { type: 'error', text: 'Network problem. The sale was not saved. Try again.' };
        } finally {
            this.busy = false;
        }
    },
});
