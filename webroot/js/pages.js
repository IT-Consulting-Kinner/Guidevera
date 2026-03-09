/**
 * Pages Module
 *
 * Client-side JavaScript for the page tree navigation, content display,
 * and WYSIWYG editing. This is the main application script that handles
 * all user interactions on the pages view.
 *
 * ## Architecture
 *
 * - **IIFE Pattern**: All code is wrapped in an Immediately Invoked Function
 *   Expression to avoid global namespace pollution.
 * - **Central State Object**: A single `state` object tracks the application
 *   state (current mode, selected page, edit status) instead of scattered
 *   global variables.
 * - **Client-Side Rendering**: All AJAX endpoints return JSON data. The
 *   client renders HTML from these responses via `renderShowView()`,
 *   `renderEditView()`, `renderSearchResults()`, and `renderIndex()`.
 * - **SSR Hydration**: On initial page load, the server provides both
 *   pre-rendered HTML (for SEO/no-JS) and a JSON tree representation.
 *   The JS takes over from the SSR state seamlessly.
 *
 * ## API Contract
 *
 * All API calls use jQuery.post() with automatic CSRF token injection
 * (configured in the layout via $.ajaxSetup). Responses are JSON objects.
 * Success responses contain data fields directly; error responses contain
 * an `error` key.
 *
 * ## Dependencies
 *
 * - jQuery 3.5+
 * - jQuery UI (dialog, tooltip, sortable)
 * - jquery.mjs.nestedSortable (tree drag-and-drop)
 * - jquery.ui-contextmenu (right-click menu)
 * - Summernote Lite (WYSIWYG editor)
 * - Translation object `t` (defined in layout, provides i18n strings)
 * - show_error() / show_success() (defined in layout, notification system)
 *
 * ## Global Exports
 *
 * Functions exposed on `window` for use in onclick handlers:
 * post_page_show, post_page_edit, post_page_create, post_get_search,
 * show_sidebar, tree_view, toggle_links, index_retract, index_expand,
 * post_get_index, resize_page_view, message_leave_edit
 *
 * Edit-mode functions (set during initEditView):
 * page_close, page_link, page_print, page_save, page_status, page_delete
 *
 * State object: window.pageState (for onkeyup handlers in edit form)
 *
 * @file pages.js
 * @requires jQuery
 * @requires jQuery UI
 * @requires nestedSortable
 * @requires Summernote
 */
(function() {
    'use strict';

    // ── Central state ──
    var state = {
        mode: 'show',           // 'show' | 'edit'
        currentId: 0,           // currently displayed page
        rootId: 0,
        hasChanges: false,
        isAuth: false,
        showNavIcons: false,
        showNavRoot: false,
        ssrRendered: false,
        errorCount: 0,
        isRetracted: false,
        lastSearch: '',
    };

    var config = {};             // window.pageConfig
    var $newLi;                  // cloned <li> template

    // ── Init ──
    jQuery(document).ready(function() {
        config = window.pageConfig || {};
        state.isAuth = config.isAuth || false;
        state.showNavIcons = config.showNavIcons || false;
        state.showNavRoot = config.showNavRoot || false;
        state.currentId = config.currentId || 0;
        state.ssrRendered = config.hasSsrNav || false;

        createItemTemplate();
        resizePageView();
        initSortable();
        initContextMenu();
        loadTree();

        if (config.textDir === 'rtl') jQuery('html').attr('dir', 'rtl');
        if (config.hasSsrPage) initSsrPage();
    });

    jQuery(window).resize(function() { resizePageView(); });

    // ── Expose functions for onclick handlers in HTML ──
    window.post_page_show = showPage;
    window.post_page_edit = editPage;
    window.post_page_create = createPage;
    window.post_get_search = doSearch;
    window.show_sidebar = showSidebar;
    window.tree_view = treeView;
    window.toggle_links = toggleLinks;
    window.index_retract = indexRetract;
    window.index_expand = indexExpand;
    window.post_get_index = loadIndex;
    window.resize_page_view = resizePageView;
    window.message_leave_edit = function() { show_error(t.leave_edit); };

    // ── API helpers ──
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

    // ── SSR init (first page load) ──
    function initSsrPage() {
        try {
            jQuery('#modal_link').dialog({ autoOpen:false, modal:true, closeOnEscape:true, resizable:false, width:600 }).show();
            loadLinkDialog(config.pageUrl);
        } catch(e) {}
        try { jQuery('[title]').not('[title=""]').not('.ui-dialog-titlebar *').tooltip(); } catch(e) {}
        resizePageView();
    }

    function loadLinkDialog(url) {
        jQuery('#modal_link').dialog({ open: function() { jQuery('#modal_link_input').val(url); }});
    }

    // ── Tree ──
    function loadTree(method, selectId, editId) {
        method = method || state.mode;
        selectId = selectId || state.currentId;
        editId = editId || 0;

        if (!state.isAuth) {
            // Guest: use SSR-rendered navigation
            if (state.ssrRendered) {
                state.currentId = selectId;
                var $first = jQuery('#page_navigation > li').first();
                if ($first.length) state.rootId = parseInt($first.attr('id').replace('list_', ''));
                jQuery('#page_navigation').show();
                highlightCurrent();
                return;
            }
            // Fallback: build from SSR tree data
            if (config.ssrTree) {
                buildTree({ arrTree: config.ssrTree }, method, selectId, editId);
                config.ssrTree = null;
                return;
            }
        } else {
            // Auth: use SSR data on first load, then AJAX
            state.ssrRendered = false;
            if (config.ssrTree) {
                buildTree({ arrTree: config.ssrTree }, method, selectId, editId);
                config.ssrTree = null;
                return;
            }
            api('/pages/get_tree', {}, function(d) {
                buildTree(d, method, selectId, editId);
            }, function() { show_error(t.pages_error_tree); });
        }
    }

    function buildTree(data, method, selectId, editId) {
        if (!data.arrTree || !data.arrTree.length) {
            jQuery('#new_page').show();
            return;
        }

        jQuery('#page_navigation').html('');
        var started = false;
        jQuery.each(data.arrTree, function(i, v) {
            if (i === 0 && !state.showNavRoot && selectId == v.id) selectId = 0;
            if (i === 0) {
                state.rootId = v.id;
                if (selectId == 0 && state.showNavRoot) selectId = v.id;
                addRootNode(v.id, v.title, v.status);
            } else {
                if (selectId == 0) selectId = v.id;
                addChildNode(v.id, v.parent_id, v.title, v.status, v.views);
            }
            if (i === data.arrTree.length - 1) {
                if (selectId == 0) selectId = v.id;
                started = true;
                if (editId == 0 && method === 'show' && state.mode !== 'edit') {
                    if (config.hasSsrPage) {
                        state.currentId = selectId;
                        highlightCurrent();
                        config.hasSsrPage = false;
                    } else {
                        showPage(selectId);
                    }
                }
                restoreTreeState();
                jQuery('#page_navigation').show();
            }
        });
    }

    function restoreTreeState() {
        var pageTree = config.pageTree || {};
        jQuery('#page_navigation li').each(function() {
            var icon = jQuery(this).find('span').first().data('icon');
            if (icon === 'folder-open') {
                var aName = jQuery(this).find('a').first().attr('name') || '';
                var nodeId = aName.replace('a_', '');
                if (!pageTree.open || !pageTree.open.hasOwnProperty(nodeId)) {
                    treeView(jQuery(this).find('span').first(), true);
                }
            }
        });
    }

    function addRootNode(id, title, status) {
        if (!title) title = t.pages_new_unnamed || '[New page]';
        jQuery('#new_page').hide();
        var $clone = $newLi.clone();
        $clone.attr('id', 'list_' + id);
        if (!state.showNavRoot) $clone.css('list-style', 'none').find('div').first().css('display', 'none');
        if (state.showNavIcons) $clone.find('span').first().addClass('fas fa-book').data('icon', 'book');
        $clone.find('span').first().css('color', 'darkslategrey').attr('onclick', '');
        setLinkHandler($clone, id, title, status);
        $clone.appendTo(jQuery('#page_navigation'));
        jQuery('#page_navigation').show();
    }

    function addChildNode(id, target, title, status, views) {
        if (!title) title = t.pages_new_unnamed || '[New page]';
        if (id === null || target === null) return;
        var $t = jQuery('#list_' + target);
        if ($t.parent().attr('id') !== 'page_navigation') {
            var $sp = $t.find('span').first();
            $sp.removeClass('far fa-file-alt');
            if (state.showNavIcons) $sp.addClass('fas fa-folder-open').data('icon', 'folder-open');
            $sp.css('color', '#ffb449').attr('onclick', 'tree_view(this)');
        }
        if ($t.not(':has(ul)').length) $t.append('<ul></ul>');
        if (!state.showNavRoot) jQuery('#page_navigation').find('ul').first().css('margin', '0');

        var $clone = $newLi.clone();
        if (!state.isAuth && status === 'inactive') $clone.addClass('hidden');
        $clone.attr('id', 'list_' + id);
        if (state.showNavIcons) $clone.find('span').first().addClass('far fa-file-alt').data('icon', 'document');
        $clone.find('span').first().css('color', '#222');
        setLinkHandler($clone, id, title + (views !== undefined ? ' [' + views + ']' : ''), status);
        $clone.appendTo($t.find('ul').first());
    }

    function setLinkHandler($clone, id, title, status) {
        var $a = $clone.find('a').first();
        $a.attr('name', 'a_' + id);
        if (state.isAuth) {
            $a.attr('href', 'javascript:').attr('onclick', 'post_page_show(' + id + ')');
        } else {
            var slug = title.replace(/[^a-zA-Z0-9\-_ ]/g, '').replace(/\s+/g, '-');
            $a.attr('href', '/pages/' + id + '/' + encodeURIComponent(slug));
        }
        if (status === 'inactive') $a.addClass('inactive');
        $a.html(title);
    }

    function highlightCurrent() {
        jQuery('#sidebar a').removeClass('selected');
        jQuery('a[name=a_' + state.currentId + ']').addClass('selected');
    }

    // ── Show page ──
    function showPage(id, exitEdit) {
        if (state.mode === 'edit' && state.hasChanges) {
            show_error(t.leave_edit);
            return;
        }
        state.mode = 'show';
        state.hasChanges = false;
        api('/pages/show', { id: id }, function(d) {
            state.currentId = id;
            state.errorCount = 0;
            highlightCurrent();
            jQuery('#page').html(renderShowView(d));
            initShowView(d);
            expandToNode(id);
        }, function() {
            if (state.errorCount === 0) {
                if (id) show_error(t.pages_error_show);
                state.errorCount = 1;
                showPage(state.rootId);
            } else {
                show_error(t.pages_error_book);
                state.errorCount = 0;
            }
        });
    }

    function renderShowView(d) {
        var pub = config.publicConfig || {};
        var html = `<form id="print_page" action="/pages/${d.id}/print/${encodeURIComponent(d.title || '')}" method="get" target="_blank"></form>
            <div id="modal_link" style="display:none" title="${t.pages_copy_link || 'Link'}"><p><input id="modal_link_input" style="width:100%;padding:0.5em" type="text" value=""></p></div>
            <div id="content_actions" class="row border-bottom bg-light justify-content-between"><div class="col-auto py-2">`;
        if (state.isAuth) html += `<span onclick="post_page_edit(${d.id})" title="${t.pages_edit || 'Edit'}" class="fas fa-edit border border-dark p-2 m-2"></span>`;
        if (pub.showLinkButton !== false) html += `<span onclick="jQuery('#modal_link').dialog('open')" title="${t.pages_copy_link || 'Link'}" class="fas fa-link border border-dark p-2 m-2"></span>`;
        if (pub.enablePrint) html += `<span onclick="jQuery('#print_page').submit()" title="${t.pages_print || 'Print'}" class="fas fa-print border border-dark p-2 m-2"></span>`;
        if (pub.enableMarkdownExport) html += `<span onclick="exportMarkdown(${d.id})" title="Export Markdown" class="fab fa-markdown border border-dark p-2 m-2"></span>`;
        if (pub.enablePdfExport) html += `<span onclick="exportPdf(${d.id})" title="Export PDF" class="fas fa-file-pdf border border-dark p-2 m-2"></span>`;
        html += '</div>';

        if (state.isAuth || pub.showAuthorDetails !== false) {
            html += `<div id="page_info" class="col-auto px-0"><div class="container py-2"><div class="row">
                <div class="col-auto"><span>${t.pages_created || 'Created'}:</span><br/><span>${t.pages_modified || 'Modified'}:</span></div>
                <div class="col-auto"><span>${d.created || ''} | ${d.createdBy || ''}</span><br/><span>${d.modified || ''} | ${d.modifiedBy || ''}</span></div>
                </div></div></div>`;
        }
        html += '</div>';

        // Breadcrumbs
        if (d.breadcrumbs && d.breadcrumbs.length > 1 && config.publicConfig && config.publicConfig.enableBreadcrumbs) {
            html += '<div class="breadcrumbs" style="padding:0.5rem 1rem;font-size:0.8rem;color:var(--text-secondary);border-bottom:1px solid var(--border-light)">';
            d.breadcrumbs.forEach(function(bc, i) {
                if (i > 0) html += ' <span style="margin:0 0.25rem">›</span> ';
                if (i < d.breadcrumbs.length - 1) html += `<a href="javascript:" onclick="post_page_show(${bc.id})" style="color:var(--text-secondary);text-decoration:none">${escHtml(bc.title)}</a>`;
                else html += `<span style="color:var(--text-primary)">${escHtml(bc.title)}</span>`;
            });
            html += '</div>';
        }

        html += `<div id="content_pane" class="p-2 pe-4 h-100">
            <h3 id="page_title"${d.status === 'inactive' ? ' class="inactive"' : ''}>${escHtml(d.title || '')}</h3>`;
        if (d.status === 'active' || state.isAuth) {
            html += d.content || '';
        } else {
            html += `<p style="color:red">${t.pages_not_published || 'Not published.'}</p>`;
        }

        // Prev/Next navigation
        if (d.nav && config.publicConfig && config.publicConfig.enablePrevNext) {
            var nav = d.nav;
            if (nav.previousId || nav.nextId) {
                html += '<div style="display:flex;justify-content:space-between;margin-top:2rem;padding-top:1rem;border-top:1px solid var(--border-color)">';
                if (nav.previousId) html += `<a href="javascript:" onclick="post_page_show(${nav.previousId})" style="color:var(--text-link);text-decoration:none;font-size:0.9rem"><span class="fas fa-arrow-left"></span> ${escHtml(nav.previousTitle)}</a>`;
                else html += '<span></span>';
                if (nav.nextId) html += `<a href="javascript:" onclick="post_page_show(${nav.nextId})" style="color:var(--text-link);text-decoration:none;font-size:0.9rem">${escHtml(nav.nextTitle)} <span class="fas fa-arrow-right"></span></a>`;
                html += '</div>';
            }
        }

        // Comments section (internal, for editors)
        if (state.isAuth && config.publicConfig && config.publicConfig.enableComments) {
            html += `<div id="pageComments" style="margin-top:2rem;padding-top:1rem;border-top:1px solid var(--border-color)">
                <h4 style="font-size:0.9rem;color:var(--text-secondary);margin-bottom:0.75rem"><span class="fas fa-comments"></span> ${t.comments_title || 'Internal Comments'}</h4>
                <div id="commentsList" style="margin-bottom:0.75rem"></div>
                <div style="display:flex;gap:0.5rem">
                <input type="text" id="commentInput" placeholder="${t.comment_placeholder || 'Add a comment... Use @username to mention'}" style="flex:1;padding:0.4rem 0.75rem;border:1px solid var(--border-color);border-radius:var(--radius-sm);font-size:0.85rem" onkeypress="if(event.key==='Enter')addComment(${d.id})">
                <button onclick="addComment(${d.id})" style="padding:0.4rem 0.75rem;background:var(--brand-primary);color:#fff;border:none;border-radius:var(--radius-sm);font-size:0.85rem;cursor:pointer">${t.comment_send || 'Send'}</button>
                </div></div>`;
        }

        // Feedback section
        if (d.feedback && config.publicConfig && config.publicConfig.enableFeedback) {
            html += `<div class="feedback-section" style="margin-top:2rem;padding-top:1rem;border-top:1px solid var(--border-color)">
                <div style="display:flex;align-items:center;gap:1rem;margin-bottom:0.75rem">
                <span style="font-size:0.85rem;color:var(--text-secondary)">${t.feedback_helpful || 'Was this page helpful?'}</span>
                <button onclick="submitFeedback(${d.id},1)" class="toolbar-btn feedback-btn" data-rating="1" title="${t.feedback_yes || 'Yes'}" style="width:auto;padding:0.25rem 0.75rem;font-size:0.8rem"><span class="fas fa-thumbs-up"></span> ${d.feedback.up || 0}</button>
                <button onclick="submitFeedback(${d.id},-1)" class="toolbar-btn feedback-btn" data-rating="-1" title="${t.feedback_no || 'No'}" style="width:auto;padding:0.25rem 0.75rem;font-size:0.8rem"><span class="fas fa-thumbs-down"></span> ${d.feedback.down || 0}</button>
                </div>
                <div id="feedbackForm" style="display:none;margin-bottom:1rem">
                <textarea id="feedbackComment" style="width:100%;max-width:500px;padding:0.5rem;border:1px solid var(--border-color);border-radius:var(--radius-sm);font-size:0.85rem" rows="3" placeholder="${t.feedback_comment_placeholder || 'Optional comment...'}"></textarea><br>
                <button onclick="sendFeedback(${d.id})" class="toolbar-btn" style="width:auto;padding:0.25rem 0.75rem;margin-top:0.5rem;background:var(--brand-primary);color:#fff;border-radius:var(--radius-sm)">${t.feedback_submit || 'Submit'}</button>
                </div>`;
            if (d.feedback.comments && d.feedback.comments.length > 0) {
                html += '<div style="margin-top:1rem">';
                d.feedback.comments.forEach(function(c) {
                    html += `<div style="padding:0.5rem;margin-bottom:0.5rem;background:var(--bg-body);border-radius:var(--radius-sm);font-size:0.85rem">
                        <span class="fas fa-thumbs-${c.rating > 0 ? 'up' : 'down'}" style="color:var(--text-muted)"></span>
                        ${escHtml(c.comment)} <small style="color:var(--text-muted)">— ${c.created}</small></div>`;
                });
                html += '</div>';
            }
            html += '</div>';
        }

        // Related pages placeholder (loaded async in initShowView)
        html += '<div id="relatedPages" style="margin-top:1.5rem"></div>';

        // Tags display
        html += '<div id="pageTags" style="margin-top:1rem"></div>';

        html += '</div>';
        return html;
    }

    function initShowView(d) {
        try {
            jQuery('#modal_link').dialog({ autoOpen:false, modal:true, closeOnEscape:true, resizable:false, width:600 }).show();
            var url = (config.baseUri || '/') + 'pages/' + d.id + '/' + encodeURIComponent(d.title || '');
            loadLinkDialog(url);
        } catch(e) {}
        try { jQuery('[title]').not('[title=""]').not('.ui-dialog-titlebar *').tooltip(); } catch(e) {}
        if (state.isAuth && config.publicConfig && config.publicConfig.enableComments) loadComments(d.id);

        // Load tags
        api('/pages/tags', { page_id: d.id }, function(td) {
            if (td.tags && td.tags.length) {
                var h = '<div style="display:flex;flex-wrap:wrap;gap:0.3rem;padding-top:0.5rem;border-top:1px solid var(--border-light)">';
                td.tags.forEach(function(tag) {
                    h += `<span style="padding:0.15rem 0.5rem;background:var(--bg-hover);border-radius:10px;font-size:0.75rem;color:var(--text-secondary)">${escHtml(tag)}</span>`;
                });
                jQuery('#pageTags').html(h + '</div>');
            }
        });

        // Load related pages
        api('/pages/related', { page_id: d.id }, function(rd) {
            if (rd.related && rd.related.length) {
                var h = `<div style="padding-top:0.75rem;border-top:1px solid var(--border-light)"><span style="font-size:0.8rem;font-weight:600;color:var(--text-secondary)">${t.related_pages || 'Related pages'}:</span> `;
                rd.related.forEach(function(r, i) {
                    if (i > 0) h += ', ';
                    h += `<a href="javascript:" onclick="post_page_show(${r.id})" style="font-size:0.8rem;color:var(--text-link)">${escHtml(r.title)}</a>`;
                });
                jQuery('#relatedPages').html(h + '</div>');
            }
        });

        // Subscribe button (if enabled)
        if (state.isAuth && config.publicConfig && config.publicConfig.enableSubscriptions) {
            api('/pages/subscription_status', { page_id: d.id }, function(sd) {
                var btn = sd.subscribed
                    ? `<button onclick="toggleSubscribe(${d.id})" style="font-size:0.75rem;padding:0.2rem 0.6rem;background:var(--brand-primary);color:#fff;border:none;border-radius:var(--radius-sm);cursor:pointer"><span class="fas fa-bell-slash"></span> ${t.unsubscribe || 'Unsubscribe'}</button>`
                    : `<button onclick="toggleSubscribe(${d.id})" style="font-size:0.75rem;padding:0.2rem 0.6rem;background:var(--bg-hover);border:1px solid var(--border-color);border-radius:var(--radius-sm);cursor:pointer"><span class="fas fa-bell"></span> ${t.subscribe_btn || 'Subscribe'}</button>`;
                jQuery('#content_actions .col-auto:first').append(' ' + btn);
            });
        }

        // Acknowledge button (if page requires it)
        if (state.isAuth && config.publicConfig && config.publicConfig.enableAcknowledgements) {
            api('/pages/ack_status', { page_id: d.id }, function(ad) {
                if (!ad.acknowledged) {
                    jQuery('#content_pane').append(`<div style="margin-top:1.5rem;padding:1rem;background:#fff3cd;border:1px solid #ffc107;border-radius:var(--radius);text-align:center">
                        <span class="fas fa-check-circle"></span> <button onclick="acknowledgePage(${d.id})" style="padding:0.3rem 1rem;background:var(--brand-primary);color:#fff;border:none;border-radius:var(--radius-sm);cursor:pointer">${t.acknowledge_btn || 'I have read and understood this page'}</button></div>`);
                }
            });
        }

        resizePageView();
    }

    // ── Edit page ──
    function editPage(id) {
        if (!id) return;
        state.mode = 'edit';
        api('/pages/edit', { id: id }, function(d) {
            state.currentId = id;
            highlightCurrent();
            jQuery('#page').html(renderEditView(d));
            initEditView(d);
        }, function() {
            state.mode = 'show';
            state.hasChanges = false;
            show_error(t.pages_error_edit);
        });
    }

    function renderEditView(d) {
        var pub = config.publicConfig || {};
        var html = `<form id="print_page" action="/pages/${d.id}/print/${encodeURIComponent(d.title || '')}" method="get" target="_blank"></form>
            <div id="modal_link" style="display:none" title="${t.pages_copy_link || 'Link'}"><p><input id="modal_link_input" style="width:100%;padding:0.5em" type="text" value=""></p></div>
            <form id="statusform"><input type="hidden" name="id" value="${d.id}"></form>
            <form id="pageform"><input type="hidden" name="id" value="${d.id}">
            <div id="content_actions" class="row border-bottom bg-light justify-content-between"><div class="col-auto py-2">
            <span onclick="page_close()" title="${t.pages_exit_edit || 'Close'}" class="far fa-window-close border border-dark p-2 m-2"></span>
            <span onclick="page_link()" title="${t.pages_copy_link || 'Link'}" class="fas fa-link border border-dark p-2 m-2"></span>`;
        if (pub.enablePrint) html += `<span onclick="page_print()" title="${t.pages_print || 'Print'}" class="fas fa-print border border-dark p-2 m-2"></span>`;
        html += `<span onclick="page_save()" title="${t.pages_save || 'Save'}" class="far fa-save border border-dark p-2 m-2"></span>
            <span style="display:none" onclick="page_status('inactive')" title="${t.pages_unpublish || 'Unpublish'}" class="page_active far fa-eye-slash border border-dark p-2 m-2"></span>
            <span style="display:none" onclick="page_status('active')" title="${t.pages_publish || 'Publish'}" class="page_inactive far fa-eye border border-dark p-2 m-2"></span>
            <span onclick="page_delete()" title="${t.pages_delete || 'Delete'}" class="far fa-trash-alt border border-dark p-2 m-2"></span>`;
        if (pub.enableRevisions) html += `<span onclick="showRevisions(${d.id})" title="${t.revisions_title || 'History'}" class="fas fa-history border border-dark p-2 m-2"></span>`;
        html += `</div>
            <div class="col-auto px-0"><div class="container py-2"><div class="row">
            <div class="col-auto"><span>${t.pages_created || 'Created'}:</span><br/><span>${t.pages_modified || 'Modified'}:</span></div>
            <div class="col-auto"><span>${d.created || ''} | ${d.createdBy || ''}</span><br/><span>${d.modified || ''} | ${d.modifiedBy || ''}</span></div>
            </div></div></div></div>
            <div id="content_wrapper_edit">`;

        // Locale switcher
        if (d.availableLocales && d.availableLocales.length > 1) {
            html += `<div style="margin-bottom:1rem;display:flex;align-items:center;gap:0.5rem">
                <span style="font-size:0.8rem;font-weight:600;color:var(--text-secondary)">${t.edit_locale || 'Language'}:</span>
                <select id="localeSwitch" onchange="switchEditLocale(${d.id},this.value)" style="padding:0.25rem 0.5rem;border:1px solid var(--border-color);border-radius:var(--radius-sm);font-size:0.85rem">`;
            d.availableLocales.forEach(function(loc) {
                var sel = (loc === (d.locale || 'en')) ? ' selected' : '';
                var mark = (d.translatedLocales && d.translatedLocales.indexOf(loc) >= 0) ? ' ✓' : '';
                html += `<option value="${loc}"${sel}>${loc.toUpperCase()}${mark}</option>`;
            });
            html += '</select></div>';
        }

        html += `<span>${t.pages_title || 'Title'}</span><br/>
            <input class="form-control" maxlength="255" onkeyup="pageState.hasChanges=true;" id="title" name="title" type="text" value="${escAttr(d.title || '')}"><br>
            <span>${t.pages_description || 'Description'}</span><br/>
            <textarea class="form-control" maxlength="160" id="description" name="description" onkeyup="pageState.hasChanges=true;" placeholder="${t.pages_placeholder_desc || ''}">${escHtml(d.description || '')}</textarea><br>
            <span>${t.pages_keywords || 'Keywords'}</span><br/>
            <textarea class="form-control" maxlength="255" id="keywords" name="keywords" onkeyup="pageState.hasChanges=true;" placeholder="${t.pages_placeholder_kw || ''}">${escHtml(d.keywords || '')}</textarea><br>
            <span>${t.tags_label || 'Tags'}</span> <small style="color:var(--text-muted)">(${t.tags_hint || 'comma-separated'})</small><br/>
            <input class="form-control" maxlength="500" id="pageTags" name="tags" type="text" value="" placeholder="security, setup, faq" onkeyup="pageState.hasChanges=true;"><br>`;
        // Workflow status (contributor+)
        if (state.isAuth && config.auth && (config.auth.role === 'admin' || config.auth.role === 'contributor')) {
            html += `<span>${t.workflow_status || 'Workflow'}</span><br/>
                <select id="workflowStatus" style="padding:0.3rem 0.5rem;border:1px solid var(--border-color);border-radius:var(--radius-sm);font-size:0.85rem;margin-bottom:0.75rem">
                <option value="draft"${(d.workflowStatus || 'published') === 'draft' ? ' selected' : ''}>${t.workflow_draft || 'Draft'}</option>
                <option value="review"${(d.workflowStatus || '') === 'review' ? ' selected' : ''}>${t.workflow_review || 'In Review'}</option>
                <option value="published"${(d.workflowStatus || 'published') === 'published' ? ' selected' : ''}>${t.workflow_published || 'Published'}</option>
                <option value="archived"${(d.workflowStatus || '') === 'archived' ? ' selected' : ''}>${t.workflow_archived || 'Archived'}</option>
                </select><br>`;
        }
        html += `<span>${t.pages_content || 'Content'}</span><br/>
            <textarea id="content" name="content" style="display:none"></textarea>
            <div id="editor" style="display:none">${d.content || '<p><br></p>'}</div><br>
            </div></form>`;
        return html;
    }

    function initEditView(d) {
        try {
            jQuery('#modal_link').dialog({ autoOpen:false, modal:true, closeOnEscape:true, resizable:false, width:600 }).show();
            loadLinkDialog((config.baseUri || '/') + 'pages/' + d.id + '/' + encodeURIComponent(d.title || ''));
        } catch(e) {}

        jQuery('#page').animate({ scrollTop: 0 }, 0);
        jQuery('.page_' + d.status).show();

        // Load tags into edit field
        api('/pages/tags', { page_id: d.id }, function(td) {
            if (td.tags) jQuery('#pageTags').val(td.tags.join(', '));
        });

        jQuery('#editor').summernote({
            lang: config.editorLang || 'en-US',
            toolbar: [
                ['font', ['fontname','fontsize']],
                ['style', ['bold','italic','underline','strikethrough','superscript','subscript']],
                ['colorstyle', ['style','color']],
                ['clear', ['clear']],
                ['para', ['ul','ol','paragraph']],
                ['height', ['height']],
                ['table', ['table']],
                ['insert', ['link','picture','video']],
                ['view', ['fullscreen','codeview','help']]
            ],
            popover: {
                image: [['image',['resizeFull','resizeHalf','resizeQuarter','resizeNone']],['float',['floatLeft','floatRight','floatNone']],['remove',['removeMedia']]],
                link: [['link',['linkDialogShow','unlink']]],
                table: [['add',['addRowDown','addRowUp','addColLeft','addColRight']],['delete',['deleteRow','deleteCol','deleteTable']]],
                air: [['color',['color']],['font',['bold','underline','clear']],['para',['ul','paragraph']],['table',['table']],['insert',['link','picture']]]
            },
            callbacks: {
                onChange: function() { state.hasChanges = true; },
                onImageUpload: function(files) {
                    // Upload image to server, insert URL into editor
                    for (var i = 0; i < files.length; i++) {
                        var formData = new FormData();
                        formData.append('file', files[i]);
                        jQuery.ajax({
                            url: '/pages/upload_media',
                            method: 'POST',
                            data: formData,
                            processData: false,
                            contentType: false,
                            headers: { 'X-CSRF-Token': strCsrfToken },
                            success: function(d) {
                                if (d && d.url) jQuery('#editor').summernote('insertImage', d.url);
                                else show_error(t.file_error_upload || 'Upload failed.');
                            },
                            error: function() { show_error(t.file_error_upload || 'Upload failed.'); }
                        });
                    }
                }
            }
        });

        initBrowseButton();
        resizePageView();

        // Edit-mode helper functions (global for onclick)
        window.page_close = function() {
            if (!state.hasChanges || confirm(t.unsaved_changes)) {
                state.mode = 'show'; state.hasChanges = false;
                showPage(state.currentId, true);
            }
        };
        window.page_link = function() { jQuery('#modal_link').dialog('open'); };
        window.page_print = function() { jQuery('#print_page').submit(); };
        window.page_save = function() { savePage(d.id); if(event)event.preventDefault(); };
        window.page_status = function(s) { setPageStatus(d.id, s); };
        window.page_delete = function() { if(confirm(t.pages_confirm_delete)) deletePage(d.id, d.parentId || 0); };
    }

    function initBrowseButton() {
        var iv = setInterval(function() {
            var $d = jQuery('.link-dialog');
            if (!$d.length) return;
            clearInterval(iv);
            var $u = $d.find('.note-link-url');
            if (!$u.length || $u.parent().hasClass('input-group')) return;
            $u.wrap('<div class="input-group"></div>');
            var $b = jQuery('<button type="button" class="btn btn-outline-secondary" title="Browse"><span class="fas fa-folder-open"></span></button>');
            $u.before($b);
            $b.on('click', function(e) { e.preventDefault(); e.stopPropagation(); openBrowseModal($u); });
        }, 300);
    }

    // ── CRUD operations ──
    function savePage(id) {
        jQuery('#content').val(jQuery('#editor').summernote('code'));
        var data = jQuery('#pageform').serializeArray();
        if (state.editLocale) data.push({ name: 'locale', value: state.editLocale });
        api('/pages/save', data, function() {
            state.hasChanges = false;
            // Save tags separately
            var tagsVal = jQuery('#pageTags').val();
            if (tagsVal !== undefined) {
                api('/pages/save_tags', { page_id: id, tags: tagsVal }, function() {}, function() {});
            }
            // Save workflow status if changed
            var ws = jQuery('#workflowStatus').val();
            if (ws) {
                api('/pages/set_workflow', { id: id, workflow_status: ws }, function() {}, function() {});
            }
            loadTree('edit', 0, id);
            loadIndex();
            show_success(t.pages_saved);
        }, function() { show_error(t.pages_error_save); });
    }

    function deletePage(id, parentId) {
        api('/pages/delete', { id: id }, function(d) {
            state.mode = 'show'; state.hasChanges = false;
            jQuery('#page').html('');
            loadTree('show', parentId);
            show_success(t.pages_deleted);
        }, function(d) {
            if (d && d.error === 'has_child') show_error(t.pages_error_has_children);
            else show_error(t.pages_error_delete);
        });
    }

    function setPageStatus(id, newStatus) {
        api('/pages/set_status', { id: id, status: newStatus }, function() {
            jQuery('.page_inactive').toggle(); jQuery('.page_active').toggle(); jQuery('#title').toggleClass('inactive');
            if (newStatus === 'active') { jQuery('a[name=a_'+id+']').removeClass('inactive'); show_success(t.pages_published); }
            if (newStatus === 'inactive') { jQuery('a[name=a_'+id+']').addClass('inactive'); show_success(t.pages_unpublished); }
            loadTree();
        }, function() { show_error(t.pages_error_status); });
    }

    function createPage(cb, target) {
        api('/pages/create', {}, function(d) {
            if (d.intId) {
                cb(d.intId, target, '');
                if (cb === addRootNode || cb.name === 'addRootNode') showPage(d.intId, true);
            }
        }, function() { show_error(t.pages_error_create); });
    }

    // ── Tree operations ──
    function updateOrder(serialized) {
        api('/pages/update_order', { strPages: serialized }, function() {
            loadTree();
            showPage(state.currentId);
        }, function() { show_error(t.pages_error_save_tree); });
    }

    function updateParent(id, target) {
        target = String(target).replace('list_', '');
        api('/pages/update_parent', { id: id, parent_id: target }, function() {
            addChildNode(id, target, '', 'inactive');
            updateOrder(jQuery('#page_navigation').nestedSortable('serialize'));
        }, function() { show_error(t.pages_error_save_tree); });
    }

    function saveTreeState() {
        if (!state.isAuth) return;
        var str = 'open[0]=1';
        jQuery('#page_navigation span.fa-folder-open').each(function() { str += '&open[' + jQuery(this).parent().find('a').first().attr('name').replace('a_', '') + ']=1'; });
        jQuery('#page_navigation span.fa-file-alt').each(function() { str += '&open[' + jQuery(this).parent().find('a').first().attr('name').replace('a_', '') + ']=1'; });
        api('/user/save_page_tree', { strElements: str });
    }

    // ── Sidebar tabs ──
    function showSidebar(element) {
        jQuery('#sidemenu div.active').removeClass('active');
        jQuery('#sidebar_content,#sidebar_index,#sidebar_search').hide();
        jQuery('#sidemenu_' + element).addClass('active');
        jQuery('#sidebar_' + element).show();
        if (element === 'content') loadTree('show', state.currentId);
        if (element === 'index') loadIndex();
        if (element === 'search') doSearch();
    }

    // ── Search ──
    function doSearch() {
        var $form = jQuery('#sidebar_searchform');
        if ($form.length) {
            var data = $form.serializeArray();
            api('/pages/search', data, function(d) {
                renderSearchResults(d);
            }, function() { show_error(t.pages_error_search); });
        } else {
            jQuery('#sidebar_search').html('<div><form id="sidebar_searchform"><input type="text" id="search" name="search" value=""> <input type="submit" value="' + (t.pages_search_btn || 'Search') + '" onclick="post_get_search();event.preventDefault();"></form></div>');
        }
    }

    function renderSearchResults(d) {
        var html = `<div><form id="sidebar_searchform"><input type="text" id="search" name="search" value="${escAttr(d.search || '')}">
            <input type="submit" value="${t.pages_search_btn || 'Search'}" onclick="post_get_search();event.preventDefault();"></form>`;
        if (d.searchMode === 'like' && d.results && d.results.length > 0) {
            html += `<div style="padding:0.25rem 0.5rem;font-size:0.7rem;color:var(--text-muted);text-align:center">${t.search_basic_mode || 'Basic search'}</div>`;
        }
        if (d.results && d.results.length > 0) {
            html += `<div style="padding:0.25rem 0.5rem;font-size:0.7rem;color:var(--text-muted)">${d.results.length} ${t.search_results_count || 'results'}</div>`;
            d.results.forEach(function(p) {
                var cls = p.status === 'inactive' ? ' class="inactive"' : '';
                html += `<div style="margin-bottom:0.5rem"><span class="far fa-file-alt"></span> <a${cls} name="a_${p.id}" href="javascript:" onclick="post_page_show(${p.id})" style="font-weight:600">${escHtml(p.title)}</a>`;
                if (p.snippet) html += `<div style="font-size:0.75rem;color:var(--text-secondary);margin-left:1.2rem;line-height:1.3;overflow:hidden;max-height:2.6em">${p.snippet}</div>`;
                if (p.modified) html += `<span style="font-size:0.65rem;color:var(--text-muted);margin-left:1.2rem">${p.modified}</span>`;
                html += '</div>';
            });
        } else {
            html += `<div style="text-align:center"><span>${t.pages_no_results || 'No results.'}</span></div>`;
        }
        html += '</div>';
        jQuery('#sidebar_search').html(html);
        var v = jQuery('#search').val(); jQuery('#search').focus().val('').val(v);
        highlightCurrent();
    }

    // ── Index ──
    function loadIndex() {
        api('/pages/index', {}, function(d) {
            renderIndex(d);
        }, function() { show_error(t.pages_error_index); });
    }

    function renderIndex(d) {
        var html = '';
        if (d.indexes && Object.keys(d.indexes).length > 0) {
            html += `<div class="hide_links" id="index_retract"><a href="javascript:" onclick="index_retract()">${t.pages_collapse_all || 'Collapse'}</a></div>
                <div id="index_expand"><a href="javascript:" onclick="index_expand()">${t.pages_expand_all || 'Expand'}</a></div><ul>`;
            Object.keys(d.indexes).forEach(function(kw) {
                html += `<li class="list_page_hide"><a href="javascript:" onclick="toggle_links(this);">${escHtml(kw)}</a><div class="index_pages hide_links">`;
                d.indexes[kw].forEach(function(e) {
                    var cls = e.status === 'inactive' ? ' class="inactive"' : '';
                    html += `<div style="white-space:nowrap;"><span class="far fa-file-alt"></span> <a${cls} name="a_${e.page_id}" href="javascript:" onclick="post_page_show(${e.page_id})">${escHtml(e.title)}</a></div>`;
                });
                html += '</div></li>';
            });
            html += '</ul>';
        } else {
            html = `<div><span>${t.pages_no_keywords || 'No keywords.'}</span></div>`;
        }
        jQuery('#sidebar_index').html(html);
        highlightCurrent();
    }

    function toggleLinks(elem) {
        var $li = jQuery(elem).closest('li');
        $li.toggleClass('list_page_hide').toggleClass('list_page_show');
        $li.children('div').toggleClass('hide_links');
        if (!jQuery('#sidebar_index li.list_page_show').length) { jQuery('#index_expand').removeClass('hide_links'); jQuery('#index_retract').addClass('hide_links'); }
        if (!jQuery('#sidebar_index li.list_page_hide').length) { jQuery('#index_expand').addClass('hide_links'); jQuery('#index_retract').removeClass('hide_links'); }
    }

    function indexRetract() {
        jQuery('#index_expand').removeClass('hide_links'); jQuery('#index_retract').addClass('hide_links');
        jQuery('#sidebar_index li.list_page_show').each(function() { jQuery(this).toggleClass('list_page_hide').toggleClass('list_page_show').children('div').toggleClass('hide_links'); });
        state.isRetracted = true;
    }

    function indexExpand() {
        jQuery('#index_expand').addClass('hide_links'); jQuery('#index_retract').removeClass('hide_links');
        jQuery('#sidebar_index li.list_page_hide').each(function() { jQuery(this).toggleClass('list_page_hide').toggleClass('list_page_show').children('div').toggleClass('hide_links'); });
        state.isRetracted = false;
    }

    // ── Tree view helpers ──
    function treeView(elem, skipSave) {
        var $e = jQuery(elem);
        if ($e.closest('li').not(':has(ul)').length) return;
        $e.closest('li').find('ul').first().toggleClass('mjs-nestedSortable-collapsed');
        var id = $e.parent().find('a').first().attr('name').replace('a_', '');
        if ($e.data('icon') === 'folder-open') {
            $e.removeClass('fa-folder-open').addClass('fa-folder').data('icon', 'folder-closed');
            if (!skipSave && config.pageTree && config.pageTree.open) config.pageTree.open[id] = 0;
        } else {
            $e.removeClass('fa-folder').addClass('fa-folder-open').data('icon', 'folder-open');
            if (!skipSave && config.pageTree && config.pageTree.open) config.pageTree.open[id] = 1;
        }
        if (!skipSave) saveTreeState();
    }

    function expandToNode(id) {
        jQuery('a[name=a_' + id + ']').parents('ul').addBack().each(function() {
            var elem = jQuery(this).parent().find('span').first();
            if (jQuery(this).hasClass('mjs-nestedSortable-collapsed')) treeView(elem, true);
        });
    }

    // ── Sortable + Context menu (auth only) ──
    function initSortable() {
        if (!state.isAuth) return;
        jQuery('#page_navigation').nestedSortable({
            forcePlaceholderSize: true, helper: 'clone', opacity: .6,
            placeholder: 'placeholder', handle: 'div', items: 'li:not(.unsortable)',
            protectRoot: true, rtl: (config.textDir === 'rtl'),
            startCollapsed: false, listType: 'ul', expandOnHover: 500,
            isTree: true, tolerance: 'pointer', toleranceElement: '> div', tabSize: 29,
            isAllowed: function() { return state.mode !== 'edit'; },
            update: function() {
                jQuery('ul').not(':has(li)').remove();
                jQuery('li').not(':has(ul)').each(function() {
                    jQuery(this).find('span').first().removeClass('fas fa-folder-open').addClass('far fa-file-alt').css('color', '#222').data('icon', 'document');
                });
                jQuery('li').has('ul').each(function() {
                    if (jQuery(this).find('span').first().data('icon') === 'document')
                        jQuery(this).find('span').first().removeClass('far fa-file-alt').addClass('fas fa-folder-open').css('color', '#ffb449').data('icon', 'folder-open');
                });
                updateOrder(jQuery('#page_navigation').nestedSortable('serialize'));
            }
        });
    }

    function initContextMenu() {
        if (!state.isAuth) return;
        jQuery('#page_navigation').contextmenu({
            delegate: '.hasmenu',
            menu: [{ title: t.pages_insert || 'Insert page', cmd: 'insert' }],
            select: function(e, ui) {
                if (state.mode === 'edit') { show_error(t.leave_edit); return; }
                if (ui.cmd === 'insert') {
                    createPage(updateParent, jQuery(ui.target).parents('li').first().attr('id'));
                }
            }
        });
    }

    function createItemTemplate() {
        $newLi = jQuery('#new_li').clone();
        $newLi.find('ul').remove();
        $newLi.attr('id', 'list_new');
        $newLi.find('div').first().removeClass('hasmenu').addClass('hasmenu');
        $newLi.find('span').first().attr('onclick', 'tree_view(this)');
    }

    // ── Browse modal ──
    function openBrowseModal($targetInput) {
        if (!jQuery('#browseModal').length) {
            jQuery('body').append(
                '<div id="browseModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;z-index:10000;background:rgba(0,0,0,0.5)">' +
                '<div id="browseModalContent" style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;border-radius:4px;width:700px;height:50vh;display:flex;flex-direction:column">' +
                '<div class="browse-header" style="padding:10px 15px;border-bottom:1px solid #ddd;font-weight:bold;display:flex;justify-content:space-between;align-items:center"><span>' + (t.pages_browse || 'Browse') + '</span><button type="button" class="btn btn-sm btn-outline-secondary" onclick="jQuery(\'#browseModal\').hide()">&times;</button></div>' +
                '<div class="browse-body" style="flex:1;display:flex;overflow:hidden">' +
                '<div style="flex:1;display:flex;flex-direction:column;overflow:hidden"><div style="padding:8px 15px;font-weight:bold;background:#f0f0f0;border-bottom:1px solid #ddd"><span class="fas fa-folder-open"></span> ' + (t.file_files || 'Files') + '</div><div style="flex:1;overflow-y:auto"><ul id="browseFileList" style="list-style:none;margin:0;padding:0"></ul></div></div>' +
                '<div style="flex:1;display:flex;flex-direction:column;overflow:hidden;border-left:2px solid #ccc"><div style="padding:8px 15px;font-weight:bold;background:#f0f0f0;border-bottom:1px solid #ddd"><span class="far fa-file-alt"></span> ' + (t.pages_label || 'Pages') + '</div><div style="flex:1;overflow-y:auto"><ul id="browsePageList" style="list-style:none;margin:0;padding:0"></ul></div></div>' +
                '</div></div></div>'
            );
        }
        jQuery('#browseModal').data('targetInput', $targetInput).show();
        jQuery('#browseFileList,#browsePageList').empty().append('<li style="padding:10px;color:#999">Loading...</li>');

        api('/file/browse', {}, function(d) {
            jQuery('#browseFileList').empty();
            if (d.files && d.files.length > 0) {
                jQuery.each(d.files, function(i, f) {
                    jQuery('#browseFileList').append(jQuery('<li style="padding:6px 15px;cursor:pointer;border-bottom:1px solid #eee">').html('<span class="fas fa-file"></span> ' + escHtml(f.name)).on('click', function() {
                        $targetInput.val('/downloads/' + encodeURIComponent(f.name)).trigger('input').trigger('change');
                        jQuery('#browseModal').hide();
                    }));
                });
            } else { jQuery('#browseFileList').append('<li style="padding:10px;color:#999">No files</li>'); }
        });

        api('/pages/browse', {}, function(d) {
            jQuery('#browsePageList').empty();
            if (d.pages && d.pages.length > 0) {
                jQuery.each(d.pages, function(i, p) {
                    jQuery('#browsePageList').append(jQuery('<li style="padding:6px 15px;cursor:pointer;border-bottom:1px solid #eee">').html('<span class="far fa-file-alt"></span> ' + escHtml(p.title)).on('click', function() {
                        var slug = p.title.replace(/[^a-zA-Z0-9\-_ ]/g, '').replace(/\s+/g, '-');
                        $targetInput.val('/pages/' + p.id + '/' + slug).trigger('input').trigger('change');
                        jQuery('#browseModal').hide();
                    }));
                });
            } else { jQuery('#browsePageList').append('<li style="padding:10px;color:#999">No pages</li>'); }
        });
    }

    // ── Layout ──
    function resizePageView() {
        var vh = jQuery(window).height();
        var th = jQuery('header').outerHeight() || 0;
        var sh = jQuery('#sidemenu').outerHeight() || 0;
        var mh = vh - th;
        jQuery('#sidebar').css('height', mh + 'px');
        jQuery('#page').css('height', mh + 'px');
        var ch = mh - sh;
        jQuery('#sidebar_content,#sidebar_index,#sidebar_search').css('height', ch + 'px');
    }

    // ── Utilities ──
    function escHtml(s) { return jQuery('<span>').text(s || '').html(); }
    function escAttr(s) { return (s || '').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/'/g,'&#39;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

    // ── Feedback ──
    var feedbackRating = 0;
    window.submitFeedback = function(pageId, rating) {
        feedbackRating = rating;
        // Highlight selected button
        jQuery('.feedback-btn').removeClass('feedback-btn--active');
        jQuery('.feedback-btn[data-rating="' + rating + '"]').addClass('feedback-btn--active');
        jQuery('#feedbackForm').slideDown(200);
    };
    window.sendFeedback = function(pageId) {
        var comment = jQuery('#feedbackComment').val() || '';
        api('/pages/feedback', { page_id: pageId, rating: feedbackRating, comment: comment }, function() {
            jQuery('.feedback-section').html('<p style="color:var(--color-success-text);font-size:0.85rem">' + (t.feedback_thanks || 'Thank you for your feedback!') + '</p>');
        }, function(d) {
            if (d && d.error === 'feedback_rate_limited') show_error(t.feedback_rate_limited || 'You have already submitted feedback for this page.');
            else show_error(t.feedback_error || 'Failed to submit feedback.');
        });
    };

    // ── Revisions ──
    window.showRevisions = function(pageId) {
        api('/pages/revisions', { id: pageId }, function(d) {
            var html = '<div style="margin-bottom:0.75rem;display:flex;gap:0.5rem">';
            html += `<button onclick="compareSelected()" class="toolbar-btn" style="width:auto;padding:0.25rem 0.75rem;font-size:0.8rem"><span class="fas fa-columns"></span> ${t.revision_compare || 'Compare selected'}</button>`;
            html += '</div><div style="max-height:55vh;overflow-y:auto">';
            if (d.revisions && d.revisions.length > 0) {
                d.revisions.forEach(function(r, i) {
                    html += `<div style="padding:0.5rem;margin-bottom:0.5rem;background:var(--bg-body);border-radius:var(--radius-sm);display:flex;align-items:center;gap:0.5rem">
                        <input type="checkbox" class="rev-compare" value="${r.id}" style="flex-shrink:0">
                        <div style="flex:1;cursor:pointer" onclick="loadRevision(${r.id})">
                        <strong>${escHtml(r.titlePreview)}</strong><br>
                        <small style="color:var(--text-muted)"><span class="fas fa-user" style="margin-right:0.2rem"></span>${escHtml(r.createdBy)} — ${r.created}</small>`;
                    if (r.note) html += `<br><small style="color:var(--text-secondary)"><span class="fas fa-comment" style="margin-right:0.2rem"></span>${escHtml(r.note)}</small>`;
                    html += '</div><span class="fas fa-eye" style="color:var(--text-muted);cursor:pointer" onclick="loadRevision(' + r.id + ')"></span></div>';
                });
            } else {
                html += '<p style="text-align:center;color:var(--text-muted)">No revisions available.</p>';
            }
            html += '</div>';
            jQuery('#revisionModal').remove();
            jQuery('body').append('<div id="revisionModal" title="' + (t.revisions_title || 'Page History') + '">' + html + '</div>');
            jQuery('#revisionModal').dialog({ modal: true, width: 650, closeOnEscape: true, resizable: true });
        });
    };

    window.compareSelected = function() {
        var checked = jQuery('.rev-compare:checked');
        if (checked.length !== 2) { show_error(t.revision_select_two || 'Select exactly two revisions to compare.'); return; }
        var id1 = checked.eq(0).val(), id2 = checked.eq(1).val();
        api('/pages/revision_show', { revision_id: id1 }, function(rev1) {
            api('/pages/revision_show', { revision_id: id2 }, function(rev2) {
                jQuery('#revisionModal').dialog('close');
                var html = `<div style="padding:1rem">
                    <div style="display:flex;justify-content:space-between;margin-bottom:1rem;font-size:0.85rem">
                    <div><strong>${escHtml(rev1.title)}</strong><br><small style="color:var(--text-muted)">${rev1.created} — ${escHtml(rev1.createdBy)}</small></div>
                    <div style="text-align:right"><strong>${escHtml(rev2.title)}</strong><br><small style="color:var(--text-muted)">${rev2.created} — ${escHtml(rev2.createdBy)}</small></div>
                    </div><div style="max-height:55vh;overflow-y:auto">`;
                // Title diff
                if (rev1.title !== rev2.title) {
                    html += `<div style="margin-bottom:1rem"><strong style="font-size:0.8rem;color:var(--text-secondary)">Title</strong>
                        <div style="font-family:var(--font-mono,monospace);font-size:0.85rem">${simpleDiff(rev1.title || '', rev2.title || '')}</div></div>`;
                }
                // Description diff
                if ((rev1.description || '') !== (rev2.description || '')) {
                    html += `<div style="margin-bottom:1rem"><strong style="font-size:0.8rem;color:var(--text-secondary)">Description</strong>
                        <div style="font-family:var(--font-mono,monospace);font-size:0.85rem">${simpleDiff(rev1.description || '', rev2.description || '')}</div></div>`;
                }
                // Content diff
                html += `<div><strong style="font-size:0.8rem;color:var(--text-secondary)">Content</strong>
                    <div style="font-family:var(--font-mono,monospace);font-size:0.85rem;line-height:1.6">${simpleDiff(stripHtml(rev1.content || ''), stripHtml(rev2.content || ''))}</div></div>`;
                html += '</div></div>';
                jQuery('#revisionCompare').remove();
                jQuery('body').append('<div id="revisionCompare" title="' + (t.revision_diff || 'Compare') + '">' + html + '</div>');
                jQuery('#revisionCompare').dialog({ modal: true, width: 750, closeOnEscape: true, resizable: true });
            });
        });
    };

    window.loadRevision = function(revId) {
        api('/pages/revision_show', { revision_id: revId }, function(d) {
            jQuery('#revisionModal').dialog('close');
            var html = '<div style="padding:1rem"><div style="margin-bottom:1rem;display:flex;justify-content:space-between;align-items:center">';
            html += '<div><strong>' + escHtml(d.title) + '</strong><br><small style="color:var(--text-muted)">' + d.created + ' — ' + escHtml(d.createdBy) + '</small></div>';
            html += '<div style="display:flex;gap:0.5rem">';
            html += '<button onclick="toggleDiffView()" class="toolbar-btn" style="width:auto;padding:0.25rem 0.75rem;font-size:0.8rem" title="' + (t.revision_diff || 'Compare') + '"><span class="fas fa-columns"></span> Diff</button>';
            html += '<button onclick="restoreRevision(' + d.id + ')" class="toolbar-btn" style="width:auto;padding:0.25rem 0.75rem;background:var(--brand-primary);color:#fff;border-radius:var(--radius-sm)">' + (t.revision_restore || 'Restore') + '</button>';
            html += '</div></div>';
            // Full content view
            html += '<div id="revContentView" style="border:1px solid var(--border-color);border-radius:var(--radius);padding:1rem;max-height:50vh;overflow-y:auto">' + d.content + '</div>';
            // Diff view (hidden initially)
            html += '<div id="revDiffView" style="display:none;max-height:50vh;overflow-y:auto"></div>';
            html += '<div style="text-align:right;margin-top:1rem"><button onclick="jQuery(\'#revisionDetail\').dialog(\'close\');showRevisions(' + d.page_id + ')" class="toolbar-btn" style="width:auto;padding:0.25rem 0.75rem">' + (t.revision_back || 'Back to list') + '</button></div></div>';
            jQuery('#revisionDetail').remove();
            jQuery('body').append('<div id="revisionDetail" title="' + (t.revision_detail || 'Revision Detail') + '">' + html + '</div>');
            jQuery('#revisionDetail').dialog({ modal: true, width: 700, closeOnEscape: true, resizable: true });

            // Store revision content for diff
            window._revisionContent = d.content;
            window._revisionPageId = d.page_id;
        });
    };

    window.toggleDiffView = function() {
        if (jQuery('#revDiffView').is(':visible')) {
            jQuery('#revDiffView').hide(); jQuery('#revContentView').show();
            return;
        }
        // Load current page content for comparison
        api('/pages/show', { id: window._revisionPageId }, function(current) {
            var oldText = stripHtml(window._revisionContent || '');
            var newText = stripHtml(current.content || '');
            var diffHtml = simpleDiff(oldText, newText);
            jQuery('#revDiffView').html(
                '<div style="padding:1rem;font-family:var(--font-mono,monospace);font-size:0.85rem;line-height:1.6">' +
                '<div style="margin-bottom:0.5rem;font-weight:600;color:var(--text-secondary)">Revision ← → Current</div>' +
                diffHtml + '</div>'
            ).show();
            jQuery('#revContentView').hide();
        });
    };

    /**
     * Simple line-based text diff. Shows added (green) and removed (red) lines.
     */
    function simpleDiff(oldText, newText) {
        var oldLines = oldText.split('\n');
        var newLines = newText.split('\n');
        var html = '';
        var maxLen = Math.max(oldLines.length, newLines.length);
        // Simple LCS-based diff
        var i = 0, j = 0;
        while (i < oldLines.length || j < newLines.length) {
            var oldLine = (i < oldLines.length) ? oldLines[i] : null;
            var newLine = (j < newLines.length) ? newLines[j] : null;
            if (oldLine === newLine) {
                html += '<div style="padding:1px 8px;color:var(--text-secondary)">' + escHtml(oldLine || '') + '</div>';
                i++; j++;
            } else if (oldLine !== null && (newLine === null || newLines.indexOf(oldLine, j) === -1)) {
                html += '<div style="padding:1px 8px;background:var(--color-error-bg);color:var(--color-error-text)">- ' + escHtml(oldLine) + '</div>';
                i++;
            } else {
                html += '<div style="padding:1px 8px;background:var(--color-success-bg);color:var(--color-success-text)">+ ' + escHtml(newLine) + '</div>';
                j++;
            }
        }
        return html || '<div style="padding:8px;color:var(--text-muted)">No differences found.</div>';
    }

    function stripHtml(html) {
        var tmp = document.createElement('div');
        tmp.innerHTML = html;
        return (tmp.textContent || tmp.innerText || '').replace(/\r/g, '').trim();
    }

    window.restoreRevision = function(revId) {
        if (!confirm(t.revision_confirm_restore || 'Restore this revision? The current content will be saved as a new revision.')) return;
        api('/pages/revision_restore', { revision_id: revId }, function() {
            jQuery('#revisionDetail').dialog('close');
            show_success(t.revision_restored || 'Revision restored.');
            showPage(state.currentId);
        }, function() { show_error(t.revision_error || 'Failed to restore revision.'); });
    };

    // ── Subscribe ──
    window.toggleSubscribe = function(pageId) {
        api('/pages/subscribe', { page_id: pageId }, function(d) {
            show_success(d.subscribed ? (t.subscribed || 'Subscribed!') : (t.unsubscribed || 'Unsubscribed.'));
            // Reload to update button
            showPage(pageId);
        });
    };

    // ── Acknowledge ──
    window.acknowledgePage = function(pageId) {
        api('/pages/acknowledge', { page_id: pageId }, function() {
            show_success(t.acknowledged || 'Acknowledged.');
            showPage(pageId);
        });
    };

    // ── Comments ──
    function loadComments(pageId) {
        api('/pages/comments', { page_id: pageId }, function(d) {
            if (!d.comments) return;
            var html = '';
            d.comments.forEach(function(c) {
                html += `<div style="padding:0.5rem;margin-bottom:0.5rem;background:var(--bg-body);border-radius:var(--radius-sm);font-size:0.85rem">
                    <strong>${escHtml(c.user)}</strong> <small style="color:var(--text-muted)">${c.created}</small>
                    <div style="margin-top:0.25rem">${escHtml(c.comment).replace(/@(\w+)/g, '<strong style="color:var(--brand-primary)">@$1</strong>')}</div>
                </div>`;
            });
            jQuery('#commentsList').html(html);
        });
    }
    window.addComment = function(pageId) {
        var text = jQuery('#commentInput').val();
        if (!text) return;
        api('/pages/comment_add', { page_id: pageId, comment: text }, function(c) {
            jQuery('#commentInput').val('');
            loadComments(pageId);
        }, function() { show_error(t.comment_error || 'Failed to add comment.'); });
    };

    // ── Export ──
    window.exportMarkdown = function(pageId) {
        window.open('/pages/export_md?id=' + pageId, '_blank');
    };
    window.exportPdf = function(pageId) {
        window.open('/pages/export_pdf?id=' + pageId, '_blank');
    };

    // ── Locale switcher for translations ──
    window.switchEditLocale = function(pageId, locale) {
        if (state.hasChanges && !confirm(t.unsaved_changes || 'Unsaved changes will be lost.')) {
            jQuery('#localeSwitch').val(state.editLocale || 'en');
            return;
        }
        state.hasChanges = false;
        state.editLocale = locale;
        api('/pages/edit', { id: pageId, locale: locale }, function(d) {
            jQuery('#page').html(renderEditView(d));
            initEditView(d);
        }, function() { show_error(t.pages_error_edit); });
    };

    // Expose state for edit-mode inline handlers (onkeyup etc.)
    window.pageState = state;

})();
