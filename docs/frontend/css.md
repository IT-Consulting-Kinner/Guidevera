# CSS Architecture

## Single File Approach

All custom styles in `webroot/css/app.css` (~490 lines). No build step, no preprocessor.

## CSS Custom Properties

All colors, sizes, and transitions use CSS variables defined on `:root`. Dark mode overrides via `html.dark { ... }`.

### Key Variables

```css
--header-height: 3.5rem;    /* scales with font-size */
--sidebar-width: 20rem;     /* scales with font-size */
--tabs-height: 3rem;        /* sidebar tabs + content toolbar */
--bg-surface: #ffffff;      /* main background */
--bg-body: #f8f9fa;         /* secondary background */
--bg-toolbar: #f0f2f5;      /* toolbar backgrounds */
--text-primary: #1a1a2e;    /* main text */
--text-secondary: #5f6b7a;  /* labels, muted */
--brand-primary: #1a73e8;   /* links, buttons, accents */
--border-color: #e0e0e0;    /* borders */
```

All dimensions use `rem` so they scale with the font-size adjustment (A-/A+ buttons).

## Dark Mode

`html.dark` overrides all CSS variables to dark values. Also overrides:
- Bootstrap variables (`--bs-body-bg`, `--bs-table-color`, etc.)
- Form controls (`input`, `textarea`, `select`, `.form-control`, `::placeholder`)
- Summernote-lite (toolbar, buttons, modals, popovers, dropdowns, color palette, statusbar)
- jQuery UI dialogs
- Bootstrap tooltips

## Layout Structure

```
.app-header          (fixed height: var(--header-height))
.app-main            (flex, height: calc(100vh - header))
  .app-sidebar       (width: var(--sidebar-width))
    .sidebar-tabs    (height: var(--tabs-height), Pages/Index/Search)
    .sidebar-panel   (scrollable content)
  .app-content       (flex: 1, scrollable)
    #content_actions (height: var(--tabs-height), sticky toolbar)
    .breadcrumbs     (optional)
    #content_pane    (page content, padding: 1.25rem 2rem)
```

## Responsive Breakpoints

- `≥768px`: Desktop layout with visible sidebar
- `<768px`: Sidebar hidden, toggle button visible, backdrop overlay

## Print Styles

`@media print`: Hides header, sidebar, toolbar. Content fills full width.

## Component Styles

- `.form-page` — Centered form card (login, create user, change password)
- `#page_navigation` — Tree with nested lists, sortable items
- `.toolbar-btn` — Toolbar action buttons
- `.header-btn` — Header buttons (dark mode, font size) — always visible
- `.note-*` — Summernote dark mode overrides (buttons, modals, popovers, dropdowns)
