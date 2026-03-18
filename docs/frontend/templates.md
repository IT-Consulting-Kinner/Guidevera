# Templates Reference

## Layout

### layout/default.php

Main application layout. Includes:
- HTML head with CSS links (Bootstrap, Summernote, Font Awesome, app.css)
- Header bar with app name/logo, dark mode toggle, font size buttons, user dropdown
- Flash message container
- Main content area (`$this->fetch('content')`)
- JS translation strings (`var t = {...}`) from `__()` calls
- jQuery AJAX setup with CSRF token
- Script includes (jQuery, Bootstrap, Summernote, jQuery UI, init.js, pages.js)
- Cookie consent banner (if enabled)

User dropdown menu items: Dashboard, Edit Profile, Manage Users (admin), Manage Files, Manage Pages, Print Book (admin), Logout.

## Pages

### Pages/index.php

Main SPA entry point. Outputs:
- `window.pageConfig` with all configuration for pages.js
- SSR sidebar with navigation tree
- SSR page content via `element/pages/show`
- Includes pages.js

### Pages/dashboard.php

Three-column grid: Recently Edited, Recently Created, My Drafts. Stats cards row above. Full-width sections below for Pending Reviews and Search Misses (admin).

### element/pages/sidebar.php

SSR sidebar with tabs (Pages, Index, Search), page tree, and search form.

### element/pages/show.php

SSR page display: toolbar with action icons, breadcrumbs, page title, content, created/modified info. Placeholders for async-loaded features (tags, related pages, comments, feedback).

## Users

### Users/login.php

Login form with CSRF token, username/password fields, hidden page_id for redirect-after-login. Shows setup instructions if no users exist.

### Users/profil.php

Profile edit: name, email, gender, notification preferences, password change.

### Users/create.php

Admin form: username, name, email, password, role (editor/contributor/admin), gender.

### Users/index.php

User management with row/col grid layout. Inline editing (click edit → fields become inputs). Role and status dropdowns disabled for the current user.

## Files

### Files/index.php

File management SPA. JavaScript handles:
- Folder breadcrumb navigation
- File/folder listing with usage info
- Upload (button + drag & drop, multi-file)
- Create/delete/move folders
- Delete/move files
- Per-file settings panel (display mode, visibility checkboxes)
