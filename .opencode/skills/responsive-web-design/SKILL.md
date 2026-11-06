---
name: responsive-web-design
description: Use this skill when implementing, auditing, or fixing layout behavior across screen sizes in this Laravel + Inertia + React 19 + Tailwind CSS v4 POS. Desktop/laptop cashier terminals are the primary target, with tablet and narrow-window fallbacks. Trigger on "responsive", "tablet", "small screen", "overflow", "horizontal scroll", "breakpoint", "layout breaks", "cart panel", "product grid", "data table", "modal vs sheet", "touch targets", or when writing/editing any page, dialog, table, or checkout component that must also work below 1280px.
---

# Responsive Web Design — POS Screens

This skill governs layout behavior across `example-app/resources/js` for the STOCKRAGE POS.
The primary device is a **desktop/laptop cashier terminal (1280px and up)**. Tablet and narrow
windows are **fallback modes**, not co-equal design targets. The goal is: full functionality at
desktop density, no broken layout or dead controls at 768–1279px, and a usable read-only or
quick-action mode on phones.

Stack facts to assume unless the repo says otherwise:

- Laravel 13 + Inertia 3 + React 19 + TypeScript.
- Tailwind CSS v4, configured inline in `example-app/resources/css/app.css` (`@theme`, no JS config).
- shadcn/ui primitives over Radix; icons from `lucide-react`; toasts from `sonner`.
- Sidebar shell: `components/app-shell.tsx`, `components/ui/sidebar.tsx`, `hooks/use-mobile.tsx`.
- `useIsMobile()` uses a **768px** breakpoint and `Sidebar` switches to an off-canvas sheet below `md`.

## 0. Mandatory context-gathering

Do not guess at the current state. Before changing layout:

1. Read the actual page/component file(s) involved and the `@theme` block in `resources/css/app.css`. Verify whether custom breakpoints or container widths exist before assuming defaults.
2. Check whether the screen lives inside `AppShell` with the sidebar variant. Account for the sidebar width when judging available space — a 1024px window is not 1024px of content.
3. Check `useIsMobile()` and existing `md:` / `lg:` classes before introducing a second breakpoint source. Do not add a parallel JS breakpoint if CSS can express the rule.
4. If fixing a reported break, reproduce it at the exact viewport first and identify the failure mode: overflow, clipped columns, unreachable button, stacked panel hiding the cart, broken dialog, or unreadable density.
5. Prefer existing `ui/` primitives (`Dialog`, `Sheet`, `Table`, `Card`, `Button`, `Input`) over new components or libraries.

## 1. Breakpoint strategy

Desktop-first is acceptable **only** for the dense cashier workspace. Base components remain
mobile-safe by default.

| Range | Tailwind | Mode | Expectation |
|---|---|---|---|
| 1536px+ | `2xl:` | Large terminal | Full density, optional side-by-side cart + catalog |
| 1280–1535px | `xl:` | Standard terminal | Primary design target; no horizontal scroll |
| 1024–1279px | `lg:` | Small laptop / tablet landscape | Cart may collapse to a drawer; tables stay tabular |
| 768–1023px | `md:` | Tablet portrait | Sidebar off-canvas; two-pane layouts stack; keypad-friendly |
| < 768px | base / `sm:` | Phone fallback | Single column; read-only or critical actions only |

Rules:

- Never require a minimum page width. If a screen cannot work below a width, explicitly render a
  supported fallback state rather than letting it clip.
- Use `lg:` for the cart-rail ↔ drawer switch, not a custom JS breakpoint.
- Keep `useIsMobile()` as the single JS source for mobile-only behavior (sheet vs dialog).
- Do not edit global theme breakpoints to fix one component. Use standard prefixes or a scoped
  arbitrary variant such as `min-[900px]:`.
- Test at **1024, 1280, 1440, and 1920px** as the main matrix, plus **768px** for tablet fallback.

## 2. Screen-specific rules

### Checkout / cart

- Desktop: catalog and cart can sit side by side. The cart rail gets a stable width and its own
  scroll area; the page itself must not scroll horizontally.
- Below `lg`: move the cart into a `Sheet` or collapsible summary that keeps the total and
  "Charge" action reachable at all times. Do not simply hide the cart.
- Keep totals, change due, and the primary action visible without scrolling when the cart is long.
- Never let a long product name push the quantity controls or line total out of view — truncate the
  name, not the money.
- Do not use `h-screen`; use `h-dvh` so browser chrome does not clip the checkout footer.

### Product grid / catalog

- Grid: `grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-5`, with `min-w-0` on cards.
- Cards need a fixed minimum height so adding a badge or price does not reflow the row.
- Search/filter/barcode controls stack above the grid below `md:`; keep the scan input focused and
  full-width.

### Data tables (inventory, sales, users)

- Wrap tables in an `overflow-x-auto` container with `min-w-0` on the flex/grid parent. A table
  wider than the viewport may scroll **inside its card**, never the page body.
- At `md:` and below, hide low-priority columns (`hidden md:table-cell`) instead of shrinking every
  column. Never hide the primary identifier, status, or amount.
- Keep numeric columns right-aligned and tabular at every size.
- If a row needs many actions, collapse them into a dropdown rather than letting icon buttons wrap.
- Pagination controls must stay reachable and must not overflow at 320px.

### Dialogs, sheets, and confirmations

- `Dialog` for desktop-sized confirmations; `Sheet` for mobile/tablet when content needs height.
- Constrain dialogs with `max-h-[90dvh]` and an internal scroll region; the footer action row stays
  pinned.
- Never open a nested dialog on top of a sheet on small screens — replace the sheet content or use
  a route/page.
- Destructive confirmations (void sale, refund, delete product) must remain legible and reachable
  regardless of viewport; do not rely on hover.

### Forms, keypad, and scanner input

- Inputs use `text-base` (16px) minimum to avoid mobile Safari zoom-on-focus.
- Numeric fields use `inputMode="decimal"` or `inputMode="numeric"`; money fields never rely on a
  spinner.
- Barcode/scanner focus: a dialog opening must not permanently steal focus from the scan field after
  it closes. Restore focus explicitly on close.
- On-screen quantity controls need 44×44px minimum targets in tablet fallback.

### Receipt / print view

- Receipt markup targets a fixed 80mm print width. Do **not** make it fluid or apply responsive
  breakpoints to it; screen preview may scale, print CSS must not.
- Keep receipt preview isolated from the app shell and its sidebar.

### Dashboard / reports

- KPI cards: `grid-cols-1 sm:grid-cols-2 xl:grid-cols-4`; never let a chart force horizontal scroll.
- Charts get a fixed aspect ratio and `min-w-0`; labels may hide below `md:` rather than overlap.

## 3. Layout and overflow rules

- Every flex/grid child that contains text or a table needs `min-w-0`; this is the most common cause
  of POS layout blowouts.
- Use `w-full max-w-*` on containers instead of fixed pixel widths.
- Do not hide critical data to make a layout fit. Reflow, scroll internally, or collapse details.
- Preserve visual density at desktop: compact table rows and tight padding are intentional. Do not
  inflate spacing when adapting to tablet.
- Horizontal scrolling is allowed only inside a bounded table/card region — never on `body`.
- Test with the sidebar both expanded and collapsed; the content must not overflow in either state.

## 4. Tablet and touch fallback

Desktop is primary, but tablets are a plausible fallback and must not be broken.

- Anything hover-only (row actions, tooltips, dropdown triggers) needs an `:active` state or must be
  reachable by tap. Scope decorative hover effects with `@media (hover: hover) and (pointer: fine)`.
- Minimum tap target 44×44px, with at least 8px spacing between adjacent actions.
- Do not rely on `title` or hover tooltips to convey required information.
- `Sidebar` already handles off-canvas below `md:`; do not duplicate that logic.
- Avoid drag-only interactions; provide buttons for reordering or adjusting quantity.

## 5. Typography and density

- Body copy stays at `text-sm` or `text-base`. Never shrink below 14px for operational text, and
  never below 16px for form inputs on touch-capable widths.
- Money and quantity figures use tabular numerals and do not shrink with the container.
- Step heading sizes with standard prefixes; do not use fluid `clamp()` unless a specific marketing
  surface needs it. This app is a tool, not a landing page.
- Responsiveness changes geometry, not theme. Do not touch color tokens, radii, or fonts in a
  layout task.

## 6. Accessibility and interaction

- Preserve focus order when panels stack or move into a sheet; Radix handles trapping — do not
  reimplement it.
- All icon-only buttons need an `aria-label`.
- Keyboard shortcuts for the cashier (search, pay, void) must not fire while focus is in a text
  input or an open dialog.
- Status must not be conveyed by color alone (low stock, voided sale, offline).
- Respect `prefers-reduced-motion` for any transition or toast animation.

## 7. Testing checklist

Before calling a screen responsive-complete:

- [ ] 1920px and 1440px — full desktop layout, cart rail visible, no wasted empty gutters
- [ ] 1280px — primary target; no horizontal page scroll, no clipped action buttons
- [ ] 1024px — sidebar expanded and collapsed; cart/table fallback works
- [ ] 768px — sidebar becomes off-canvas; dialogs/sheets usable; tables scroll internally
- [ ] 390px (sanity only) — no page-level horizontal scroll; critical actions reachable or explicitly unsupported
- [ ] Long product names, large carts (50+ lines), and long customer names do not break layout
- [ ] Keyboard-only pass: tab order, focus return after dialogs, visible focus ring
- [ ] Zoom to 200% — content remains usable and does not overlap
- [ ] Print preview of a receipt is unaffected by responsive classes

## 8. What NOT to do

- Do NOT make the phone the base design and scale up; this is a desktop cashier tool.
- Do NOT hide the cart, total, or primary action to make a narrow layout fit.
- Do NOT add a new CSS/JS breakpoint system alongside Tailwind and `useIsMobile()`.
- Do NOT wrap the whole page in `overflow-x-auto` to mask a layout bug.
- Do NOT use `h-screen` for full-height checkout layouts; use `h-dvh`.
- Do NOT shrink table columns until text overlaps; drop low-priority columns or scroll the table.
- Do NOT apply responsive rules to the 80mm receipt print stylesheet.
- Do NOT add animation libraries or redesign visuals while fixing layout.
- Do NOT set `user-scalable=no` or `maximum-scale=1` in the viewport meta tag.

## 9. Quick reference patterns

**Two-pane checkout that collapses to a sheet below `lg`:**

```tsx
<div className="grid min-h-0 flex-1 grid-cols-1 lg:grid-cols-[minmax(0,1fr)_24rem]">
    <section className="min-w-0 overflow-y-auto">{/* catalog */}</section>
    <aside className="hidden min-w-0 border-l lg:block">{/* cart rail */}</aside>
</div>
{/* below lg: render the same cart inside a Sheet triggered by a sticky total bar */}
```

**Table that scrolls inside its card, never the page:**

```tsx
<div className="min-w-0 overflow-x-auto rounded-lg border">
    <table className="w-full min-w-[40rem] text-sm">…</table>
</div>
```

**Hover-safe row action:**

```tsx
<Button
    variant="ghost"
    size="icon"
    aria-label="Void sale"
    className="opacity-100 md:opacity-0 md:group-hover:opacity-100 focus-visible:opacity-100"
>
```

**Breakpoint helper for the cart drawer:**

```tsx
const isMobile = useIsMobile(); // 768px, owned by hooks/use-mobile.tsx
// Prefer CSS `lg:` for layout; use isMobile only for behavior that CSS cannot express.
```
