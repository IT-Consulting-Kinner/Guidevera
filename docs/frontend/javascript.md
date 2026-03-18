# JavaScript Architecture

## Module Structure

Two JavaScript files:

### init.js (~70 lines)

Loaded first. Handles pre-render features that don't depend on page data:
- Dark mode initialization (localStorage + system preference detection)
- Font size persistence
- Cookie consent banner (show/accept/reject)
- Global tooltip cleanup on click

### pages.js (~1700 lines)

Main SPA module wrapped in an IIFE. Manages all page interactions.

## State Management

Central `state` object tracks application state:

```javascript
var state = {
    mode: 'show',        // 'show' | 'edit'
    currentId: 0,        // displayed page ID
    rootId: 0,           // root page ID
    hasChanges: false,    // unsaved edit changes
    isAuth: false,        // logged in
    userRole: 'editor',  // current user role
    showNavIcons: false,
    showNavRoot: false,
    ssrRendered: false,   // SSR content was pre-rendered
};
```

## Role Helpers

```javascript
function isContributor() { return state.userRole === 'contributor' || state.userRole === 'admin'; }
function isAdmin() { return state.userRole === 'admin'; }
```

Used throughout to conditionally show/hide UI elements (create, delete, status, revisions, import, reviews).

## API Communication

All server communication via `api()` helper:

```javascript
function api(url, data, success, error) {
    jQuery.post(url, data, success, 'json').fail(error);
}
```

CSRF token sent automatically via `jQuery.ajaxSetup`.

## View Rendering

Three main render functions:
- `renderShowView(d)` — Page display with toolbar, breadcrumbs, content, feedback, comments
- `renderEditView(d)` — Edit form with Summernote, metadata fields, workflow, scheduled publishing
- `renderSearchResults(d)` — Search result list

Each has a corresponding `initShowView(d)` / `initEditView(d)` for post-render setup (tooltips, async data loading).

### SSR Hybrid

`initSsrPage()` runs on first load when SSR content exists. Loads async features (comments, tags, subscriptions, acknowledgements, inline comments) without re-rendering the page.

## Summernote Integration

Custom enhancements:
- **Image upload callback**: Uploads to `/pages/upload_media`, inserts ID-based URL
- **Browse button**: File/page picker in link dialog with folder navigation
- **Image browse**: Filtered to image MIME types
- **Video browse**: Filtered to video MIME types
- **Smart links**: Autocomplete in link dialog (`/pages/link_suggest`)

## Feature Modules (self-initializing)

- **Smart Links**: Injects search field into Summernote link dialog
- **@Mentions**: Autocomplete dropdown on `#commentInput` when typing `@`
- **Inline Comments**: Load/add/resolve paragraph-level comments with text highlighting
- **Media Browse**: Filtered file picker for image/video dialogs

## File Management (Files/index.php)

Separate JavaScript in the template handles:
- Folder navigation with breadcrumbs
- File upload (button + drag & drop)
- File settings (display mode, visibility per role)
- Folder create/delete/move
- File delete/move
