# JavaScript Architecture (pages.js)

## Module Structure

The entire module is wrapped in an IIFE (Immediately Invoked Function Expression) to prevent global namespace pollution:

```javascript
(function() {
    'use strict';
    // All code here
    // Only window.xxx exports are globally accessible
})();
```

## State Management

A central `state` object replaces the previous pattern of scattered global variables:

```javascript
var state = {
    mode: 'show',          // 'show' | 'edit' — current interaction mode
    currentId: 0,          // ID of the currently displayed page
    rootId: 0,             // ID of the root page in the tree
    hasChanges: false,      // Whether the editor has unsaved changes
    isAuth: false,          // Whether the user is authenticated
    showNavIcons: false,    // Whether to show folder/document icons
    showNavRoot: false,     // Whether to show the root node
    ssrRendered: false,     // Whether SSR navigation is being used
    errorCount: 0,          // Counter for cascading error prevention
    isRetracted: false,     // Whether the keyword index is collapsed
    lastSearch: '',         // Last search query
};
```

The state is exposed as `window.pageState` so that inline `onkeyup` handlers in the edit form can set `pageState.hasChanges = true`.

## Function Map

### Initialization
| Function | Purpose |
|----------|---------|
| `jQuery(document).ready()` | Entry point — reads pageConfig, inits tree/sortable/context |
| `createItemTemplate()` | Clones the hidden `<li>` template for tree node creation |
| `initSsrPage()` | Initializes dialog/tooltip on the SSR-rendered first page |
| `initSortable()` | Configures nestedSortable for drag-and-drop (auth only) |
| `initContextMenu()` | Configures right-click "Insert page" menu (auth only) |

### Tree Management
| Function | Purpose |
|----------|---------|
| `loadTree(method, selectId, editId)` | Load or refresh the navigation tree |
| `buildTree(data, method, selectId, editId)` | Build tree DOM from JSON data |
| `addRootNode(id, title, status)` | Add the root node to the tree |
| `addChildNode(id, target, title, status, views)` | Add a child node under a parent |
| `setLinkHandler($clone, id, title, status)` | Set href (guest) or onclick (auth) on a tree link |
| `restoreTreeState()` | Restore folder open/closed state from user preferences |
| `highlightCurrent()` | Highlight the active page link in the sidebar |
| `expandToNode(id)` | Expand all parent folders to make a page visible |
| `treeView(elem, skipSave)` | Toggle a folder open/closed |
| `saveTreeState()` | Persist tree open/closed state to server |

### Page Display
| Function | Purpose |
|----------|---------|
| `showPage(id, exitEdit)` | Load and display a page (AJAX → client render) |
| `renderShowView(d)` | Build show-mode HTML from JSON response |
| `initShowView(d)` | Initialize dialog/tooltip after show render |

### Page Editing
| Function | Purpose |
|----------|---------|
| `editPage(id)` | Load page data and switch to edit mode |
| `renderEditView(d)` | Build edit-mode HTML with form fields and Summernote |
| `initEditView(d)` | Initialize Summernote editor and toolbar handlers |
| `initBrowseButton()` | Add file/page browse button to Summernote's link dialog |
| `savePage(id)` | Serialize form and POST to /pages/save |
| `deletePage(id, parentId)` | DELETE page and navigate to parent |
| `setPageStatus(id, newStatus)` | Toggle active/inactive |
| `createPage(cb, target)` | Create new page and invoke callback |

### Sidebar Panels
| Function | Purpose |
|----------|---------|
| `showSidebar(element)` | Switch between Pages/Index/Search tabs |
| `doSearch()` | Execute search and render results |
| `renderSearchResults(d)` | Build search results HTML from JSON |
| `loadIndex()` | Load keyword index and render |
| `renderIndex(d)` | Build keyword index HTML from JSON |
| `toggleLinks(elem)` | Expand/collapse a keyword group |
| `indexRetract()` | Collapse all keyword groups |
| `indexExpand()` | Expand all keyword groups |

### Tree Operations
| Function | Purpose |
|----------|---------|
| `updateOrder(serialized)` | Send new tree order to server |
| `updateParent(id, target)` | Move page to new parent |

### Layout
| Function | Purpose |
|----------|---------|
| `resizePageView()` | Adjust sidebar/content heights to viewport |

### Utilities
| Function | Purpose |
|----------|---------|
| `api(url, data, onSuccess, onError)` | Wrapper around jQuery.post with error handling |
| `escHtml(s)` | HTML-escape a string (XSS prevention) |
| `escAttr(s)` | Attribute-escape a string |

## API Communication

All API calls go through the `api()` helper:

```javascript
function api(url, data, onSuccess, onError) {
    jQuery.post(url, data || {}, function(d, s) {
        if (s === 'success' && d && !d.hasOwnProperty('error')) {
            if (onSuccess) onSuccess(d);
        } else {
            if (onError) onError(d);
        }
    }, 'json').fail(function() {
        if (onError) onError(null);
    });
}
```

CSRF tokens are automatically injected by `$.ajaxSetup` in the layout.

## Edit Mode Guard

The `state.mode` flag prevents navigation conflicts:

- When `state.mode === 'edit'` AND `state.hasChanges === true`: `showPage()` shows a warning and returns
- When `state.mode === 'edit'` AND `state.hasChanges === false`: Navigation is allowed (user opened editor but didn't change anything)
- Drag-and-drop is blocked when `state.mode === 'edit'` via `isAllowed` callback
- On API error during edit, `state.mode` is reset to `'show'` to prevent permanent lockout
