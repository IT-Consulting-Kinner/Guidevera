# CSS Architecture

## Single File Approach

All custom styles are consolidated into a single file: `webroot/css/app.css` (290 lines).

Third-party CSS files are loaded separately and unmodified:
- `bootstrap.min.css` — Grid system, utilities, form controls
- `all.min.css` — FontAwesome 5 icons
- `jquery-ui.css` — Dialog, tooltip, sortable styles
- `summernote-lite.css` — WYSIWYG editor styles

Load order in the layout (important — app.css last to override):
```html
<link rel="stylesheet" href="/css/bootstrap.min.css">
<link rel="stylesheet" href="/css/all.min.css">
<link rel="stylesheet" href="/css/jquery-ui.css">
<link rel="stylesheet" href="/css/summernote-lite.css">
<link rel="stylesheet" href="/css/app.css">
```

## Design Tokens

All colors, spacing, and typography are defined as CSS custom properties in `:root`:

```css
:root {
    --brand-primary: #1a73e8;      /* Primary action color */
    --brand-accent: #e8710a;        /* Accent/link color */
    --bg-body: #f4f5f7;            /* Page background */
    --bg-surface: #ffffff;          /* Card/content background */
    --bg-sidebar: #fafbfc;         /* Sidebar background */
    --text-primary: #1d1d1f;       /* Main text */
    --text-secondary: #5f6368;     /* Secondary text */
    --border-color: #e0e0e0;       /* Default borders */
    --header-height: 56px;         /* Fixed header height */
    --sidebar-width: 280px;        /* Sidebar width */
    --radius: 8px;                 /* Border radius */
    --transition: 0.2s ease;       /* Default transition */
}
```

To customize the theme, override these variables in your `app_local.css` or inline styles.

## Layout System

The layout uses CSS Flexbox (no Bootstrap grid for the main structure):

```
┌──────────────────────────────────────────────┐
│ .app-header (fixed height, sticky top)       │
├─────────────┬────────────────────────────────┤
│ .app-sidebar│ .app-content                   │
│ (280px)     │ (flex: 1, scrollable)          │
│             │                                │
│ Tabs:       │ #content_actions (sticky)      │
│ Pages|Index │ #content_pane (max-width 900)  │
│ |Search     │                                │
│             │                                │
│ Tree nav    │                                │
│             │                                │
├─────────────┴────────────────────────────────┤
│ Notifications (fixed, top of main)           │
└──────────────────────────────────────────────┘
```

## CSS Sections

| Section | Purpose |
|---------|---------|
| Design Tokens | CSS custom properties for theming |
| Base | Body, html reset |
| Header | `.app-header`, brand, user dropdown |
| Main Layout | `.app-main` flex container |
| Sidebar | `.app-sidebar`, tabs, tree, search, index |
| Content | `.app-content`, toolbar, content pane |
| Typography | Headings, paragraphs, code, tables, lists |
| Edit Mode | Form fields, Summernote overrides |
| Notifications | Error/success toast messages |
| Forms | `.form-page` for login, profile, etc. |
| Utilities | `.inactive`, `.hidden`, `.print_size` |
| jQuery UI | Dialog, tooltip overrides |
| Mobile | Sidebar drawer, backdrop, responsive adjustments |
| Print | Hide non-content elements |

## Responsive Breakpoints

| Breakpoint | Behavior |
|------------|----------|
| >= 768px (Desktop) | Sidebar visible, content scrollable, toggle button hidden |
| < 768px (Tablet/Mobile) | Sidebar as offcanvas drawer (left slide-in), toggle button in header, toolbar meta hidden |
| < 480px (Small Mobile) | Reduced padding, smaller fonts, compact toolbar buttons |

### Mobile Sidebar

On mobile, the sidebar becomes a fixed-position drawer:

```css
.app-sidebar {
    position: fixed;
    left: -100%;              /* Hidden off-screen */
    transition: left 0.3s ease;
}
.app-sidebar.mobile-open {
    left: 0;                  /* Slide in */
}
```

A backdrop overlay (`.sidebar-backdrop.open`) covers the content area and closes the sidebar on tap.

## BEM-ish Naming

CSS classes follow a simplified BEM convention:
- `.app-header` — Block
- `.app-header__brand` — Element
- `.app-header__dropdown` — Element
- `.sidebar-tabs__tab` — Element
- `.sidebar-tabs__tab.active` — Modifier via additional class
- `.toolbar-btn` — Standalone component
- `.toolbar-btn.danger` — Modifier
