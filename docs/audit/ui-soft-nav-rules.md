# UI rule: soft-navigation-safe scripts

This repo uses a lightweight soft navigation layer in [`resources/js/soft-navigate.js`](../../resources/js/soft-navigate.js) that swaps the inner HTML of `#main-layout` on link clicks. The sidebar, phone widget, and header stay mounted so WebRTC/iframe state survives page changes.

That model is fast, but any `<script>` that runs **only at first page load** and binds to DOM **inside** `#main-layout` will silently break after the first soft navigation. Symptom: "the theme button / some control works only after I hard refresh" or "I need to click twice."

Follow these rules when adding interactive UI.

## 1. If the control lives inside `#main-layout`

Pick one of:

### a. Alpine (`x-data` / `@click`) - preferred

`soft-navigate.js` calls `Alpine.destroyTree(mainLayout)` before the swap and `Alpine.initTree(mainLayout)` after. Components declared via `x-data` are automatically re-hydrated. Nothing else to do.

```blade
<button x-data @click="$store.toast.success('Hi')">Toast</button>
```

### b. Event delegation on `document`

Useful for small vanilla scripts that must stay (e.g. theme toggle). Bind **once** on `document`, resolve the target per click:

```html
<script>
  document.addEventListener('click', function (e) {
    const btn = e.target.closest('#my-action');
    if (!btn) return;
    doSomething();
  });
</script>
```

This script can live in [`resources/views/layouts/app.blade.php`](../../resources/views/layouts/app.blade.php) (outside `#main-layout`) and will keep working across soft navigations because it listens on a node that never gets replaced.

### c. `@push('scripts')`

Page-level scripts that must re-execute on each soft nav should live in a `@push('scripts')` block. `soft-navigate.js` looks for `#soft-nav-scripts-marker` and **re-runs every `<script>` that follows it** on each swap:

```blade
@push('scripts')
<script>
  (function () {
    let chart;
    chart?.destroy();
    chart = new ApexCharts(document.querySelector('#my-chart'), options);
    chart.render();
  })();
</script>
@endpush
```

The `chart?.destroy()` pattern avoids leaking previous instances when the same user navigates between pages that share a chart id.

## 2. If the control is part of the persistent chrome

Scripts and bindings for the sidebar, phone widget, theme toggle, user menu, and floating overlays belong in `resources/js/*` or in the `<body>` tail of [`resources/views/layouts/app.blade.php`](../../resources/views/layouts/app.blade.php) **outside** `#main-layout`. These run once on full page load and must use delegation or Alpine stores so they are immune to DOM swaps.

## 3. Anti-patterns (do not do this)

- Calling `document.getElementById('foo').addEventListener(...)` in a `<script>` inside a page-level Blade file when `foo` is inside `#main-layout` but the script is **not** inside `@push('scripts')`. It will run once at first page load, bind to a node that later gets replaced, and silently stop working.
- Creating ApexCharts/Chart.js instances without destroying the previous instance on re-init. Soft nav will leak DOM + listeners across pages.
- Using Alpine `x-init` to fetch data every time a partial mounts, without any guard. Soft nav re-runs `initTree`, which can double-fire network calls.

## 4. How to verify

1. Load the affected page.
2. Click another sidebar link (soft nav).
3. Click the original page in the sidebar to return.
4. The control should still work on the **first** click. If it needs a refresh or a second click, it is violating this rule.

Known-good reference: the theme toggle in `layouts/app.blade.php` uses event delegation + re-applies icon state on every `soft-navigate` window event.
