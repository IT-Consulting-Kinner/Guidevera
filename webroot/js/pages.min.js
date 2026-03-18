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

    function clearTooltips() {
        try { jQuery('.ui-tooltip').remove(); jQuery('[title]').tooltip('destroy'); } catch(e) {}
        jQuery('.tooltip.bs-tooltip-auto, .tooltip.bs-tooltip-top, .tooltip.bs-tooltip-bottom, .tooltip.bs-tooltip-left, .tooltip.bs-tooltip-right, div[class*="bs-tooltip"]').remove();
    }

    var strCsrfToken = window.strCsrfToken || '';

    // ── Role helpers ──
    function isContributor() { return state.userRole === 'contributor' || state.userRole === 'admin'; }
    function isAdmin() { return state.userRole === 'admin'; }

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
    var browseInterval;          // setInterval ID for browse button polling

    // ── Init ──
    jQuery(document).ready(function() {
        config = window.pageConfig || {};
        state.isAuth = config.isAuth || false;
        state.userRole = config.userRole || 'editor';
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
        initMentions();
        initSmartLinks();
        initInlineComments();

        // ── Global delegated event handler for data-action attributes ──
        jQuery(document).on('click', '[data-action]', function(e) {
            var $el = jQuery(this);
            // Hide any open jQuery UI tooltip immediately on click
            clearTooltips();
            var action = $el.data('action');
            var arg = $el.data('arg');
            var arg2 = $el.data('arg2');
            switch (action) {
                // Page navigation
                case 'postPageShow': case 'post_page_show': e.preventDefault(); post_page_show(parseInt(arg)); break;
                case 'treeView': treeView($el[0]); break;
                case 'editPage': case 'post_page_edit': post_page_edit(parseInt(arg)); break;
                // Editor toolbar
                case 'page_close': e.stopImmediatePropagation(); page_close(); break;
                case 'page_save': e.stopImmediatePropagation(); e.preventDefault(); page_save(); break;
                case 'page_delete': e.stopImmediatePropagation(); page_delete(); break;
                case 'confirmDelete': e.stopImmediatePropagation(); if(confirm(t.pages_confirm_delete || 'Delete this page?')) deletePage(parseInt(arg), parseInt(arg2) || 0); break;
                case 'page_link': e.stopImmediatePropagation(); page_link(); break;
                case 'page_print': case 'printPage': e.stopImmediatePropagation(); page_print ? page_print() : jQuery('#print_page').submit(); break;
                case 'pageStatus': case 'page_status': e.stopImmediatePropagation(); if (typeof page_status === 'function') { page_status(arg); } else { setPageStatus(state.currentId, arg); } break;
                case 'openLinkDialog': e.stopImmediatePropagation(); jQuery('#modal_link').dialog('open'); break;
                // Sidebar
                case 'showSidebar': show_sidebar(arg); break;
                case 'createPage': post_page_create(new_page); break;
                case 'doSearch': e.preventDefault(); post_get_search(); break;
                case 'toggleLinks': toggle_links(this); break;
                // Show view features
                case 'showRevisions': showRevisions(parseInt(arg)); break;
                case 'loadRevision': loadRevision(parseInt(arg)); break;
                case 'restoreRevision': restoreRevision(parseInt(arg)); break;
                case 'closeRevisionDetail': jQuery('#revisionDetail').dialog('close'); showRevisions(parseInt(arg)); break;
                case 'compareSelected': compareSelected(); break;
                case 'toggleDiffView': toggleDiffView(); break;
                case 'exportMarkdown': exportMarkdown(parseInt(arg)); break;
                case 'exportPdf': exportPdf(parseInt(arg)); break;
                case 'sendFeedback': sendFeedback(parseInt(arg)); break;
                case 'submitFeedback': submitFeedback(parseInt(arg), parseInt(arg2)); break;
                case 'addComment': addComment(parseInt(arg)); break;
                case 'toggleSubscribe': toggleSubscribe(parseInt(arg)); break;
                case 'acknowledgePage': acknowledgePage(parseInt(arg)); break;
                case 'addInlineCommentFromSelection': e.stopImmediatePropagation(); addInlineCommentFromSelection(parseInt(arg)); break;
                case 'resolveInlineComment': resolveInlineComment(parseInt(arg), parseInt(arg2)); break;
                case 'replyInlineComment': e.stopImmediatePropagation(); replyInlineComment(parseInt(arg), parseInt(arg2)); break;
                // Reviews
                case 'assignReviewer': assignReviewer(parseInt(arg)); break;
                // Import
                case 'openImportDialog': e.stopImmediatePropagation(); openImportDialog(); break;
                case 'doImport': e.stopImmediatePropagation(); doImport(); break;
                // Index
                case 'index_expand': index_expand(); break;
                case 'index_retract': index_retract(); break;
                // Modal/dialog close
                case 'closeImportModal': jQuery('#importModal').hide(); break;
                case 'closeMediaLibrary': jQuery('#mediaLibraryModal').hide(); break;
            }
        });
        // Handle change events for data-action (select elements)
        jQuery(document).on('change', '[data-action="switchLocale"]', function() {
            switchEditLocale(parseInt(jQuery(this).data('arg')), this.value);
        });
        // Handle enter key for data-action-enter
        jQuery(document).on('keypress', '[data-action-enter]', function(e) {
            if (e.key === 'Enter') {
                var fn = jQuery(this).data('action-enter');
                var arg = jQuery(this).data('arg');
                if (fn === 'addComment') addComment(parseInt(arg));
            }
        });
        // Track changes for data-track-changes
        jQuery(document).on('input change', '[data-track-changes]', function() {
            if (window.pageState) pageState.hasChanges = true;
        });

        // Expose functions for external use (SSR templates, layout handlers)
        window.post_page_show = showPage;
        window.post_page_edit = editPage;
        window.post_page_create = createPage;
        window.new_page = addRootNode;
        window.post_get_search = doSearch;
        window.show_sidebar = showSidebar;
        window.tree_view = treeView;
        window.toggle_links = toggleLinks;
        window.index_retract = indexRetract;
        window.index_expand = indexExpand;
        window.post_get_index = loadIndex;
        window.resize_page_view = resizePageView;
        window.message_leave_edit = function() { show_error(t.leave_edit); };
    });

    jQuery(window).resize(function() { resizePageView(); });

    // ── API helpers ──
    function api(url, data, onSuccess, onError) {
        jQuery.post(url, data || {}, function(d, s) {
            if (d && d.error === 'not_authenticated') {
                window.location.href = '/user/login';
                return;
            }
            if (s === 'success' && d && !d.hasOwnProperty('error')) {
                if (onSuccess) onSuccess(d);
            } else {
                if (onError) onError(d);
            }
        }, 'json').fail(function(xhr) {
            if (xhr && xhr.status === 403) {
                window.location.href = '/user/login';
                return;
            }
            if (onError) onError(null);
        });
    }

    // ── SSR init (first page load) ──
    function initSsrPage() {
        try {
            jQuery('#modal_link').dialog({ autoOpen:false, modal:true, closeOnEscape:true, resizable:false, width:600 }).show();
            loadLinkDialog(config.pageUrl);
        } catch(e) {}
        if (!('ontouchstart' in window)) { try { jQuery('[title]').not('[title=""]').not('.ui-dialog-titlebar *').tooltip(); } catch(e) {} }
        resizePageView();

        var id = config.currentId;
        if (!id) return;
        var pub = config.publicConfig || {};

        if (state.isAuth && pub.enableComments) loadComments(id);

        // Load tags into #pageTags if not already SSR-rendered
        if (!jQuery('#pageTags').children().length) {
            api('/pages/tags', { page_id: id }, function(td) {
                if (td.tags && td.tags.length) {
                    var h = '<div style="display:flex;flex-wrap:wrap;gap:0.3rem;padding-top:0.5rem;border-top:1px solid var(--border-light)">';
                    td.tags.forEach(function(tag) {
                        h += '<span style="padding:0.15rem 0.5rem;background:var(--bg-hover);border-radius:10px;font-size:0.75rem;color:var(--text-secondary)">' + escHtml(tag) + '</span>';
                    });
                    jQuery('#pageTags').html(h + '</div>');
                }
            });
        }

        // Load related pages into #relatedPages if not already SSR-rendered
        if (!jQuery('#relatedPages').children().length) {
            api('/pages/related', { page_id: id }, function(rd) {
                if (rd.related && rd.related.length) {
                    var h = '<div style="padding-top:0.75rem;border-top:1px solid var(--border-light)"><span style="font-size:0.8rem;font-weight:600;color:var(--text-secondary)">' + (t.related_pages || 'Related pages') + ':</span> ';
                    rd.related.forEach(function(r, i) {
                        if (i > 0) h += ', ';
                        h += '<a href="#" data-action="post_page_show" data-arg="' + r.id + '" style="font-size:0.8rem;color:var(--text-link)">' + escHtml(r.title) + '</a>';
                    });
                    jQuery('#relatedPages').html(h + '</div>');
                }
            });
        }

        if (state.isAuth && pub.enableSubscriptions && config.pageStatus === 'active') {
            api('/pages/subscription_status', { page_id: id }, function(sd) {
                var icon = sd.subscribed ? 'fa-bell-slash' : 'fa-bell';
                var title = sd.subscribed ? (t.unsubscribe || 'Unsubscribe') : (t.subscribe_btn || 'Subscribe');
                var btn = '<span data-action="toggleSubscribe" data-arg="' + id + '" title="' + title + '" class="fas ' + icon + ' border p-2 m-2" style="border-color:var(--border-color)!important;cursor:pointer;border-radius:var(--radius-sm)"></span>';
                jQuery('#content_actions .col-auto:first').append(btn);
            });
        }

        if (state.isAuth && pub.enableAcknowledgements && config.pageStatus === 'active') {
            api('/pages/ack_status', { page_id: id, locale: config.currentLocale || config.defaultLocale || 'en' }, function(ad) {
                if (!ad.acknowledged) {
                    jQuery('#ackBanner').remove();
                    jQuery('#content_pane').append('<div id="ackBanner" style="margin-top:1.5rem;padding:1rem;background:var(--bg-hover);border:1px solid var(--border-color);border-radius:var(--radius);text-align:center;color:var(--text-primary)"><span class="fas fa-check-circle" style="color:var(--brand-primary);margin-right:0.5rem"></span><button data-action="acknowledgePage" data-arg="' + id + '" style="padding:0.3rem 1rem;background:var(--brand-primary);color:#fff;border:none;border-radius:var(--radius-sm);cursor:pointer">' + (t.acknowledge_btn || 'I have read and understood this page') + '</button></div>');
                }
            });
        }

        // Inline comments — only shown in edit mode (see initEditView)
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
            if (isContributor()) jQuery('#new_page').show();
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
                initSortable();
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
        $clone.find('span').first().css('color', 'var(--text-secondary)').removeAttr('onclick').off('click.treeview');
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
            $sp.css('color', 'var(--brand-accent)').off('click.treeview').on('click.treeview', function() { treeView(this); });
        }
        if ($t.not(':has(ul)').length) $t.append('<ul></ul>');
        if (!state.showNavRoot) jQuery('#page_navigation').find('ul').first().css('margin', '0');

        var $clone = $newLi.clone();
        if (!state.isAuth && status === 'inactive') $clone.addClass('hidden');
        $clone.attr('id', 'list_' + id);
        if (state.showNavIcons) $clone.find('span').first().addClass('far fa-file-alt').data('icon', 'document');
        $clone.find('span').first().css('color', 'var(--text-primary)');
        setLinkHandler($clone, id, title + (views !== undefined ? ' [' + views + ']' : ''), status);
        $clone.appendTo($t.find('ul').first());

        // Ensure parent folder is expanded (not collapsed)
        $t.find('ul').first().removeClass('mjs-nestedSortable-collapsed');
    }

    function setLinkHandler($clone, id, title, status) {
        var $a = $clone.find('a').first();
        $a.attr('name', 'a_' + id);
        if (state.isAuth) {
            $a.attr('href', '#').attr('data-action', 'postPageShow').attr('data-arg', id);
        } else {
            var slug = title.replace(/[^a-zA-Z0-9\-_ ]/g, '').replace(/\s+/g, '-');
            $a.attr('href', '/pages/' + id + '/' + encodeURIComponent(slug));
        }
        if (status === 'inactive') $a.addClass('inactive');
        $a.text(title);
    }

    function highlightCurrent() {
        jQuery('#sidebar a').removeClass('selected');
        jQuery('a[name=a_' + state.currentId + ']').addClass('selected');
    }

    // ── Show page ──
    function showPage(id, exitEdit) {
        if (state.mode === 'edit' && !exitEdit) {
            show_error(t.leave_edit || 'Please close the editor first.');
            return;
        }
        state.mode = 'show';
        state.hasChanges = false;
        api('/pages/show', { id: id, locale: config.currentLocale || config.defaultLocale || 'en' }, function(d) {
            state.currentId = id;
            state.errorCount = 0;
            highlightCurrent();
            clearTooltips(); jQuery('#page').html(renderShowView(d));
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
            <div id="content_actions" style="background:var(--bg-toolbar)"><div class="col-auto">`;
        if (state.isAuth) html += `<span data-action="post_page_edit" data-arg="${d.id}" title="${t.pages_edit || 'Edit'}" class="fas fa-edit border p-2 m-2" style="border-color:var(--border-color)!important"></span>`;
        if (state.isAuth && isContributor()) html += `<span data-action="confirmDelete" data-arg="${d.id}" data-arg2="${d.parentId || 0}" title="${t.pages_delete || 'Delete'}" class="far fa-trash-alt border p-2 m-2" style="border-color:var(--border-color)!important"></span>`;
        if (state.isAuth && isContributor()) html += `<span data-action="pageStatus" data-arg="inactive" title="${t.pages_unpublish || 'Unpublish'}" class="page_active far fa-eye border p-2 m-2" style="${d.status === 'active' ? '' : 'display:none;'}border-color:var(--border-color)!important"></span><span data-action="pageStatus" data-arg="active" title="${t.pages_publish || 'Publish'}" class="page_inactive far fa-eye-slash border p-2 m-2" style="${d.status !== 'active' ? '' : 'display:none;'}border-color:var(--border-color)!important"></span>`;
        if (pub.enablePrint && d.status === 'active') html += `<span data-action="printPage" title="${t.pages_print || 'Print'}" class="fas fa-print border p-2 m-2" style="border-color:var(--border-color)!important"></span>`;
        if (pub.enableMarkdownExport && d.title && d.status === 'active') html += `<span data-action="exportMarkdown" data-arg="${d.id}" title="Export Markdown" class="fab fa-markdown border p-2 m-2" style="border-color:var(--border-color)!important"></span>`;
        if (pub.enablePdfExport && d.status === 'active') html += `<span data-action="exportPdf" data-arg="${d.id}" title="Export PDF" class="fas fa-file-pdf border p-2 m-2" style="border-color:var(--border-color)!important"></span>`;
        if (!state.isAuth && pub.showLinkButton !== false) html += `<span data-action="openLinkDialog" title="${t.pages_copy_link || 'Link'}" class="fas fa-link border p-2 m-2" style="border-color:var(--border-color)!important"></span>`;
        html += '</div>';

        if (state.isAuth || pub.showAuthorDetails !== false) {
            html += `<div id="page_info" style="padding:0.4rem 0.75rem;font-size:0.85rem">
                <table style="border-collapse:collapse"><tr><td style="padding:0 0.75rem 0 0;white-space:nowrap">${t.pages_created || 'Created'}:</td><td style="padding:0 0.75rem 0 0;white-space:nowrap">${escHtml(d.created || '')}</td><td>${escHtml(d.createdBy || '')}</td></tr>
                <tr><td style="padding:0 0.75rem 0 0;white-space:nowrap">${t.pages_modified || 'Modified'}:</td><td style="padding:0 0.75rem 0 0;white-space:nowrap">${escHtml(d.modified || '')}</td><td>${escHtml(d.modifiedBy || '')}</td></tr></table>
                </div>`;
        }
        html += '</div>';

        // Breadcrumbs
        if (d.breadcrumbs && d.breadcrumbs.length > 1 && config.publicConfig && config.publicConfig.enableBreadcrumbs) {
            html += '<div class="breadcrumbs" style="padding:0.5rem 1rem;font-size:0.8rem;color:var(--text-secondary);border-bottom:1px solid var(--border-light)">';
            d.breadcrumbs.forEach(function(bc, i) {
                if (i > 0) html += ' <span style="margin:0 0.25rem">›</span> ';
                if (i < d.breadcrumbs.length - 1) html += `<a href="#" data-action="post_page_show" data-arg="${bc.id}" style="color:var(--text-secondary);text-decoration:none">${escHtml(bc.title)}</a>`;
                else html += `<span style="color:var(--text-primary)">${escHtml(bc.title)}</span>`;
            });
            html += '</div>';
        }

        html += `<div id="content_pane" class="h-100">
            <h3 id="page_title"${d.status === 'inactive' ? ' class="inactive"' : ''}>${escHtml(d.title || t.pages_new_unnamed || '[New page]')}</h3>`;
        if (d.status === 'active' || state.isAuth) {
            html += d.content || '';
        } else {
            html += `<p style="color:var(--color-error-text)">${t.pages_not_published || 'Not published.'}</p>`;
        }

        // Prev/Next navigation
        if (d.nav && config.publicConfig && config.publicConfig.enablePrevNext) {
            var nav = d.nav;
            if (nav.previousId || nav.nextId) {
                html += '<div style="display:flex;justify-content:space-between;margin-top:2rem;padding-top:1rem;border-top:1px solid var(--border-color)">';
                if (nav.previousId) html += `<a href="#" data-action="post_page_show" data-arg="${nav.previousId}" style="color:var(--text-link);text-decoration:none;font-size:0.9rem"><span class="fas fa-arrow-left"></span> ${escHtml(nav.previousTitle)}</a>`;
                else html += '<span></span>';
                if (nav.nextId) html += `<a href="#" data-action="post_page_show" data-arg="${nav.nextId}" style="color:var(--text-link);text-decoration:none;font-size:0.9rem">${escHtml(nav.nextTitle)} <span class="fas fa-arrow-right"></span></a>`;
                html += '</div>';
            }
        }

        // Comments section (internal, for editors)
        if (state.isAuth && config.publicConfig && config.publicConfig.enableComments) {
            html += `<div id="pageComments" style="margin-top:2rem;padding-top:1rem;border-top:1px solid var(--border-color)">
                <h4 style="font-size:0.9rem;color:var(--text-secondary);margin-bottom:0.75rem"><span class="fas fa-comments"></span> ${t.comments_title || 'Internal Comments'}</h4>
                <div id="commentsList" style="margin-bottom:0.75rem"></div>
                <div style="display:flex;gap:0.5rem">
                <input type="text" id="commentInput" placeholder="${t.comment_placeholder || 'Add a comment... Use @username to mention'}" style="flex:1;padding:0.4rem 0.75rem;border:1px solid var(--border-color);border-radius:var(--radius-sm);font-size:0.85rem" data-action-enter="addComment" data-arg="${d.id}">
                <button data-action="addComment" data-arg="${d.id}" style="padding:0.4rem 0.75rem;background:var(--brand-primary);color:#fff;border:none;border-radius:var(--radius-sm);font-size:0.85rem;cursor:pointer">${t.comment_send || 'Send'}</button>
                </div></div>`;
        }

        // Feedback section
        if (d.feedback && d.status === 'active' && config.publicConfig && config.publicConfig.enableFeedback) {
            html += `<div class="feedback-section" style="margin-top:2rem;padding-top:1rem;border-top:1px solid var(--border-color)">
                <div style="display:flex;align-items:center;gap:1rem;margin-bottom:0.75rem">
                <span style="font-size:0.85rem;color:var(--text-secondary)">${t.feedback_helpful || 'Was this page helpful?'}</span>
                <span class="fas fa-thumbs-up" style="color:var(--text-muted)"></span>&nbsp;${escHtml(String(d.feedback.up || 0))}
                <span class="fas fa-thumbs-down" style="color:var(--text-muted)"></span>&nbsp;${escHtml(String(d.feedback.down || 0))}
                </div>`;
            if (!d.feedback.userVoted) {
                html += `<div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.75rem">
                <button data-action="submitFeedback" data-arg="${d.id}" data-arg2="1" class="toolbar-btn feedback-btn" data-rating="1" title="${t.feedback_yes || 'Yes'}" style="width:auto;padding:0.25rem 0.75rem;font-size:0.8rem"><span class="fas fa-thumbs-up"></span>&nbsp;${t.feedback_yes || 'Yes'}</button>
                <button data-action="submitFeedback" data-arg="${d.id}" data-arg2="-1" class="toolbar-btn feedback-btn" data-rating="-1" title="${t.feedback_no || 'No'}" style="width:auto;padding:0.25rem 0.75rem;font-size:0.8rem"><span class="fas fa-thumbs-down"></span>&nbsp;${t.feedback_no || 'No'}</button>
                </div>
                <div id="feedbackForm" style="display:none;margin-bottom:1rem">
                <textarea id="feedbackComment" style="width:100%;padding:0.5rem;border:1px solid var(--border-color);border-radius:var(--radius-sm);font-size:0.85rem" rows="3" placeholder="${t.feedback_comment_placeholder || 'Optional comment...'}"></textarea><br>
                <button data-action="sendFeedback" data-arg="${d.id}" class="toolbar-btn" style="width:auto;padding:0.25rem 0.75rem;margin-top:0.5rem;background:var(--brand-primary);color:#fff;border-radius:var(--radius-sm)">${t.feedback_submit || 'Submit'}</button>
                </div>`;
            }
            html += ``;
            if (d.feedback.comments && d.feedback.comments.length > 0) {
                html += '<div style="margin-top:1rem">';
                d.feedback.comments.forEach(function(c) {
                    html += `<div style="padding:0.5rem;margin-bottom:0.5rem;background:var(--bg-body);border-radius:var(--radius-sm);font-size:0.85rem">
                        <span class="fas fa-thumbs-${c.rating > 0 ? 'up' : 'down'}" style="color:var(--text-muted)"></span>
                        ${escHtml(c.comment)} <small style="color:var(--text-muted)">— ${escHtml(c.created || '')}</small></div>`;
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
        if (!('ontouchstart' in window)) { try { jQuery('[title]').not('[title=""]').not('.ui-dialog-titlebar *').tooltip(); } catch(e) {} }
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
                    h += `<a href="#" data-action="post_page_show" data-arg="${r.id}" style="font-size:0.8rem;color:var(--text-link)">${escHtml(r.title)}</a>`;
                });
                jQuery('#relatedPages').html(h + '</div>');
            }
        });

        // Subscribe button (only for active pages)
        if (state.isAuth && d.status === 'active' && config.publicConfig && config.publicConfig.enableSubscriptions) {
            api('/pages/subscription_status', { page_id: d.id }, function(sd) {
                var icon = sd.subscribed ? 'fa-bell-slash' : 'fa-bell';
                var title = sd.subscribed ? (t.unsubscribe || 'Unsubscribe') : (t.subscribe_btn || 'Subscribe');
                var btn = `<span data-action="toggleSubscribe" data-arg="${d.id}" title="` + title + `" class="fas ` + icon + ` border p-2 m-2" style="border-color:var(--border-color)!important;cursor:pointer;border-radius:var(--radius-sm)"></span>`;
                jQuery('#content_actions .col-auto:first').append(btn);
            });
        }

        // Acknowledge button (only for active pages)
        if (state.isAuth && d.status === 'active' && config.publicConfig && config.publicConfig.enableAcknowledgements) {
            api('/pages/ack_status', { page_id: d.id, locale: config.currentLocale || config.defaultLocale || 'en' }, function(ad) {
                if (!ad.acknowledged) {
                    jQuery('#ackBanner').remove();
                    jQuery('#content_pane').append(`<div id="ackBanner" style="margin-top:1.5rem;padding:1rem;background:var(--bg-hover);border:1px solid var(--border-color);border-radius:var(--radius);text-align:center;color:var(--text-primary)">
                        <span class="fas fa-check-circle" style="color:var(--brand-primary);margin-right:0.5rem"></span><button data-action="acknowledgePage" data-arg="${d.id}" style="padding:0.3rem 1rem;background:var(--brand-primary);color:#fff;border:none;border-radius:var(--radius-sm);cursor:pointer">${t.acknowledge_btn || 'I have read and understood this page'}</button></div>`);
                }
            });
        }

        // Inline comments — only shown in edit mode (see initEditView)

        resizePageView();
    }

    // ── Edit page ──
    function editPage(id) {
        if (!id) return;
        state.mode = 'edit';
        api('/pages/edit', { id: id, locale: config.currentLocale || config.defaultLocale || 'en' }, function(d) {
            state.currentId = id;
            highlightCurrent();
            clearTooltips(); jQuery('#page').html(renderEditView(d));
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
            <div id="content_actions" style="background:var(--bg-toolbar)"><div class="col-auto">
            <span data-action="page_close" title="${t.pages_exit_edit || 'Close'}" class="far fa-window-close border p-2 m-2" style="border-color:var(--border-color)!important"></span>`;
        if (pub.enablePrint && d.status === 'active') html += `<span data-action="page_print" title="${t.pages_print || 'Print'}" class="fas fa-print border p-2 m-2" style="border-color:var(--border-color)!important"></span>`;
        html += `<span data-action="page_save" title="${t.pages_save || 'Save'}" class="far fa-save border p-2 m-2" style="border-color:var(--border-color)!important"></span>`;
        if (isContributor()) html += `<span style="border:none!important;width:1rem;display:inline-block"></span>`;
        var canRevisions = pub.enableRevisions && (isContributor() || !pub.enableWorkflow);
        if (canRevisions) html += `<span data-action="showRevisions" data-arg="${d.id}" title="${t.revisions_title || 'History'}" class="fas fa-history border p-2 m-2" style="border-color:var(--border-color)!important"></span>`;
        if (isContributor() && pub.enableImport) html += `<span data-action="openImportDialog" title="Import" class="fas fa-file-import border p-2 m-2" style="border-color:var(--border-color)!important"></span>`;
        if (state.isAuth && pub.enableInlineComments) html += `<span id="btnInlineComment" data-action="addInlineCommentFromSelection" data-arg="${d.id}" title="Add inline comment" class="fas fa-comment-dots border p-2 m-2" style="border-color:var(--border-color)!important;opacity:0.35;pointer-events:none"></span>`;
        // Link button not shown in edit mode (only for non-authenticated users in show mode)
        html += `</div>
            <div class="col-auto px-0"><div class="container py-2"><div class="row">
            <div style="font-size:0.85rem">
            <table style="border-collapse:collapse"><tr><td style="padding:0 0.75rem 0 0;white-space:nowrap">${t.pages_created || 'Created'}:</td><td style="padding:0 0.75rem 0 0;white-space:nowrap">${escHtml(d.created || '')}</td><td>${escHtml(d.createdBy || '')}</td></tr>
            <tr><td style="padding:0 0.75rem 0 0;white-space:nowrap">${t.pages_modified || 'Modified'}:</td><td style="padding:0 0.75rem 0 0;white-space:nowrap">${escHtml(d.modified || '')}</td><td>${escHtml(d.modifiedBy || '')}</td></tr></table>
            </div>
            </div></div></div></div>
            <div id="content_wrapper_edit">`;

        // Locale switcher (only when translations enabled and multiple locales configured)
        var locales = d.availableLocales ? d.availableLocales.filter(function(v, i, a) { return a.indexOf(v) === i; }) : [];
        if (locales.length > 1 && config.publicConfig && config.publicConfig.enableTranslations) {
            var defaultLocale = config.defaultLocale || 'en';
            // Sort: default locale first, rest alphabetically
            locales.sort(function(a, b) {
                if (a === defaultLocale) return -1;
                if (b === defaultLocale) return 1;
                return a.localeCompare(b);
            });
            html += `<div style="display:flex;align-items:center;gap:0.5rem">
                <span style="font-size:0.8rem;font-weight:600;color:var(--text-secondary)">${t.edit_locale || 'Language'}:</span>
                <select id="localeSwitch" data-action="switchLocale" data-arg="${d.id}" style="padding:0.25rem 0.5rem;border:1px solid var(--border-color);border-radius:var(--radius-sm);font-size:0.85rem">`;
            locales.forEach(function(loc) {
                var sel = (loc === (d.locale || defaultLocale)) ? ' selected' : '';
                var mark = (d.translatedLocales && d.translatedLocales.indexOf(loc) >= 0) ? ' ✓' : '';
                var defLabel = (loc === defaultLocale) ? ' (default)' : '';
                html += `<option value="${escAttr(loc)}"${sel}>${escHtml(loc.toUpperCase())}${escHtml(defLabel)}${escHtml(mark)}</option>`;
            });
            html += '</select></div>';
        }

        html += `<span>${t.pages_title || 'Title'}</span>
            <input class="form-control${d.status === 'inactive' ? ' inactive' : ''}" maxlength="255" data-track-changes="true" id="title" name="title" type="text" value="${escAttr(d.title || '')}">
            <span>${t.pages_description || 'Description'}</span>
            <textarea class="form-control" maxlength="160" id="description" name="description" data-track-changes="true" placeholder="${t.pages_placeholder_desc || ''}">${escHtml(d.description || '')}</textarea>
            <span>${t.pages_keywords || 'Keywords'}</span>
            <textarea class="form-control" maxlength="255" id="keywords" name="keywords" data-track-changes="true" placeholder="${t.pages_placeholder_kw || ''}">${escHtml(d.keywords || '')}</textarea>
            <span>${t.tags_label || 'Tags'}</span> <small style="color:var(--text-muted)">(${t.tags_hint || 'comma-separated'})</small>
            <input class="form-control" maxlength="500" id="pageTags" name="tags" type="text" value="" placeholder="security, setup, faq" data-track-changes="true">`;
        // Workflow status (contributor+ AND enableReviewProcess)
        if (state.isAuth && pub.enableWorkflow && (config.userRole === 'admin' || config.userRole === 'contributor')) {
            html += `<span>${t.workflow_status || 'Workflow'}</span>
                <select id="workflowStatus" class="form-control" style="padding:0.3rem 0.5rem;border:1px solid var(--border-color);border-radius:var(--radius-sm);font-size:0.85rem">
                <option value="draft"${(d.workflowStatus || 'published') === 'draft' ? ' selected' : ''}>${t.workflow_draft || 'Draft'}</option>
                <option value="review"${(d.workflowStatus || '') === 'review' ? ' selected' : ''}>${t.workflow_review || 'In Review'}</option>
                <option value="published"${(d.workflowStatus || 'published') === 'published' ? ' selected' : ''}>${t.workflow_published || 'Published'}</option>
                <option value="archived"${(d.workflowStatus || '') === 'archived' ? ' selected' : ''}>${t.workflow_archived || 'Archived'}</option>
                </select>`;
        }
        // Scheduled Publishing fields (contributor+)
        if (isContributor() && pub.enableScheduledPublishing) {
            html += `<div style="display:flex;gap:1rem;margin-top:0.5rem">
                <div style="flex:1"><span style="display:block;margin-bottom:0.15rem;font-weight:600;font-size:0.85rem">Publish at</span>
                <input type="datetime-local" id="publishAt" class="form-control" value="${escAttr(d.publishAt || '')}" data-track-changes="true"></div>
                <div style="flex:1"><span style="display:block;margin-bottom:0.15rem;font-weight:600;font-size:0.85rem">Expire at</span>
                <input type="datetime-local" id="expireAt" class="form-control" value="${escAttr(d.expireAt || '')}" data-track-changes="true"></div>
                </div>`;
        }
        html += `<span>${t.pages_content || 'Content'}</span>
            <textarea id="content" name="content" style="display:none"></textarea>
            <div id="editor" style="display:none"></div>
            </div></form>
            <div id="content_pane"></div>`;
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
        // Set content via Summernote API to avoid raw HTML injection into DOM
        jQuery('#editor').summernote('code', d.content || '<p><br></p>');
        // Reset hasChanges — summernote('code', ...) triggers onChange which sets it to true
        state.hasChanges = false;

        initBrowseButton();
        resizePageView();

        // Auto-load Reviews panel (if workflow enabled + contributor)
        var pub = config.publicConfig || {};

        // Toggle inline comment button based on text selection in editor
        if (pub.enableInlineComments) {
            document.addEventListener('selectionchange', function() {
                var btn = document.getElementById('btnInlineComment');
                if (!btn) return;
                var sel = window.getSelection();
                var hasSelection = sel && sel.toString().trim().length > 0;
                // Only enable if selection is inside the editor
                if (hasSelection && sel.anchorNode) {
                    var inEditor = jQuery(sel.anchorNode).closest('.note-editable').length > 0;
                    hasSelection = inEditor;
                }
                btn.style.opacity = hasSelection ? '1' : '0.35';
                btn.style.pointerEvents = hasSelection ? 'auto' : 'none';
            });
        }

        if (isContributor() && pub.enableWorkflow) {
            openReviewPanel(d.id);
        }
        // Auto-load Acknowledgements panel (if enabled + contributor)
        if (isContributor() && pub.enableAcknowledgements) {
            openAckReport(d.id);
        }
        // Auto-load Inline Comments (if enabled)
        if (state.isAuth && pub.enableInlineComments && window.loadInlineComments) {
            loadInlineComments(d.id);
        }

        // Edit-mode helper functions (global for onclick)
        window.page_close = function() {
            if (!state.hasChanges || confirm(t.unsaved_changes)) {
                state.mode = 'show'; state.hasChanges = false;
                showPage(state.currentId, true);
            }
        };
        window.page_link = function() { jQuery('#modal_link').dialog('open'); };
        window.page_print = function() { jQuery('#print_page').submit(); };
        window.page_save = function() { savePage(d.id); };
        window.page_status = function(s) { setPageStatus(d.id, s); };
        window.page_delete = function() { if(confirm(t.pages_confirm_delete)) deletePage(d.id, d.parentId || 0); };
    }

    function initBrowseButton() {
        // Link + Image + Video dialogs: poll for visible inputs, inject browse buttons
        if (browseInterval) clearInterval(browseInterval);
        browseInterval = setInterval(function() {
            var $linkUrl = jQuery('.note-link-url:visible');
            if ($linkUrl.length && !$linkUrl.data('hasBrowse')) {
                $linkUrl.data('hasBrowse', true);
                var $btn = jQuery('<button type="button" class="btn btn-outline-secondary btn-sm" style="margin-top:0.5rem;display:block"><span class="fas fa-folder-open"></span> Browse files & pages</button>');
                $linkUrl.after($btn);
                $btn.on('click', function(e) { e.preventDefault(); e.stopPropagation(); openFileBrowser('link', $linkUrl); });
            }
            var $imgUrl = jQuery('.note-image-url:visible');
            if ($imgUrl.length && !$imgUrl.data('hasBrowse')) {
                $imgUrl.data('hasBrowse', true);
                var $btn = jQuery('<button type="button" class="btn btn-outline-secondary btn-sm" style="margin-top:0.5rem;display:block"><span class="fas fa-folder-open"></span> Browse images</button>');
                $imgUrl.after($btn);
                $btn.on('click', function(e) { e.preventDefault(); e.stopPropagation(); openFileBrowser('image', $imgUrl); });
            }
            var $vidUrl = jQuery('.note-video-url:visible');
            if ($vidUrl.length && !$vidUrl.data('hasBrowse')) {
                $vidUrl.data('hasBrowse', true);
                var $btn = jQuery('<button type="button" class="btn btn-outline-secondary btn-sm" style="margin-top:0.5rem;display:block"><span class="fas fa-folder-open"></span> Browse videos</button>');
                $vidUrl.after($btn);
                $btn.on('click', function(e) { e.preventDefault(); e.stopPropagation(); openFileBrowser('video', $vidUrl); });
            }
        }, 300);
    }

    /**
     * Open full File Manager in an iframe overlay for browsing/uploading files.
     * For 'link' mode, also shows a pages list panel.
     */
    var _browseTargetInput = null;
    function openFileBrowser(mode, $targetInput) {
        _browseTargetInput = $targetInput;
        jQuery('#fileBrowserModal').remove();

        var width = mode === 'link' ? '900px' : '750px';
        var html = '<div id="fileBrowserModal" style="position:fixed;top:0;left:0;right:0;bottom:0;z-index:11000;background:rgba(0,0,0,0.5)">' +
            '<div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:var(--bg-surface);border-radius:var(--radius);width:' + width + ';max-width:95vw;height:75vh;display:flex;flex-direction:column;overflow:hidden">' +
            '<div style="padding:10px 15px;border-bottom:1px solid var(--border-color);font-weight:bold;display:flex;justify-content:space-between;align-items:center">' +
            '<span><span class="fas fa-folder-open"></span> ' + (mode === 'link' ? 'Browse files & pages' : mode === 'image' ? 'Browse images' : 'Browse videos') + '</span>' +
            '<button type="button" class="btn btn-sm btn-outline-secondary" id="fileBrowserClose">&times;</button></div>' +
            '<div style="flex:1;display:flex;overflow:hidden">';

        // File Manager iframe
        html += '<div style="flex:2;display:flex;flex-direction:column;overflow:hidden">' +
            '<div style="padding:8px 15px;font-weight:bold;background:var(--bg-toolbar);border-bottom:1px solid var(--border-color)"><span class="fas fa-folder-open"></span> Files</div>' +
            '<iframe src="/file?browse=' + encodeURIComponent(mode) + '" style="flex:1;width:100%;border:none"></iframe></div>';

        // Pages panel (only for link mode)
        if (mode === 'link') {
            html += '<div style="flex:1;display:flex;flex-direction:column;border-left:2px solid var(--border-color);overflow:hidden">' +
                '<div style="padding:8px 15px;font-weight:bold;background:var(--bg-toolbar);border-bottom:1px solid var(--border-color)"><span class="far fa-file-alt"></span> Pages</div>' +
                '<div id="fileBrowserPages" style="flex:1;overflow-y:auto;padding:0"><div style="padding:10px;color:var(--text-muted)">Loading...</div></div></div>';
        }

        html += '</div></div></div>';
        jQuery('body').append(html);

        // Close button
        jQuery('#fileBrowserClose').on('click', function() { jQuery('#fileBrowserModal').remove(); _browseTargetInput = null; });
        // Click backdrop to close
        jQuery('#fileBrowserModal').on('click', function(e) { if (e.target === this) { jQuery(this).remove(); _browseTargetInput = null; } });

        // Load pages list for link mode
        if (mode === 'link') {
            api('/pages/browse', {}, function(d) {
                var $list = jQuery('#fileBrowserPages').empty();
                if (d.pages && d.pages.length) {
                    d.pages.forEach(function(p) {
                        var $li = jQuery('<div style="padding:6px 15px;cursor:pointer;border-bottom:1px solid var(--border-light);font-size:0.85rem">').html('<span class="far fa-file-alt" style="margin-right:0.3rem"></span>' + escHtml(p.title));
                        $li.on('click', function() {
                            var slug = p.title.replace(/[^a-zA-Z0-9\-_ ]/g, '').replace(/\s+/g, '-');
                            _browseTargetInput.val('/pages/' + p.id + '/' + slug).trigger('input').trigger('change');
                            jQuery('#fileBrowserModal').remove();
                            _browseTargetInput = null;
                        });
                        $list.append($li);
                    });
                } else {
                    $list.html('<div style="padding:10px;color:var(--text-muted)">No pages</div>');
                }
            });
        }
    }

    // Listen for postMessage from File Manager iframe
    window.addEventListener('message', function(e) {
        if (e.data && e.data.type === 'fileSelected' && _browseTargetInput) {
            _browseTargetInput.val(e.data.url).trigger('input').trigger('change');
            jQuery('#fileBrowserModal').remove();
            _browseTargetInput = null;
        }
    });

    // ── CRUD operations ──
    function savePage(id) {
        jQuery('#content').val(jQuery('#editor').summernote('code'));
        var data = jQuery('#pageform').serializeArray();
        if (state.editLocale) data.push({ name: 'locale', value: state.editLocale });
        // Scheduled publishing fields
        var publishAt = jQuery('#publishAt').val();
        var expireAt = jQuery('#expireAt').val();
        if (publishAt !== undefined) data.push({ name: 'publish_at', value: publishAt || '' });
        if (expireAt !== undefined) data.push({ name: 'expire_at', value: expireAt || '' });
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
            state.currentId = 0;
            clearTooltips(); jQuery('#page').html('');
            loadTree('show', parentId || state.rootId || 0);
            show_success(t.pages_deleted);
        }, function(d) {
            if (d && d.error === 'has_child') show_error(t.pages_error_has_children);
            else show_error(t.pages_error_delete);
        });
    }

    function setPageStatus(id, newStatus) {
        api('/pages/set_status', { id: id, status: newStatus }, function() {
            if (newStatus === 'active') {
                jQuery('.page_inactive').hide(); jQuery('.page_active').show();
                jQuery('#page_title').removeClass('inactive');
                jQuery('a[name=a_'+id+']').removeClass('inactive');
                show_success(t.pages_published);
            }
            if (newStatus === 'inactive') {
                jQuery('.page_active').hide(); jQuery('.page_inactive').show();
                jQuery('#page_title').addClass('inactive');
                jQuery('a[name=a_'+id+']').addClass('inactive');
                show_success(t.pages_unpublished);
            }
            loadTree();
        }, function() { show_error(t.pages_error_status); });
    }

    function createPage(cb, target) {
        if (state.mode === 'edit') { show_error(t.leave_edit || 'Please close the editor first.'); return; }
        api('/pages/create', {}, function(d) {
            if (d.intId) {
                if (cb === addRootNode || cb.name === 'addRootNode') {
                    cb(d.intId, '', 'inactive');
                    showPage(d.intId, true);
                } else {
                    cb(d.intId, target, '', 'inactive');
                }
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
        if (state.mode === 'edit') { show_error(t.leave_edit || 'Please close the editor first.'); return; }
        var $form = jQuery('#sidebar_searchform');
        if ($form.length) {
            var data = $form.serializeArray();
            api('/pages/search', data, function(d) {
                renderSearchResults(d);
            }, function() { show_error(t.pages_error_search); });
        } else {
            jQuery('#sidebar_search').html('<div><form id="sidebar_searchform"><input type="text" id="search" name="search" value=""> <input type="submit" value="' + (t.pages_search_btn || 'Search') + '" data-action="doSearch"></form></div>');
        }
    }

    function renderSearchResults(d) {
        var html = `<div><form id="sidebar_searchform"><input type="text" id="search" name="search" value="${escAttr(d.search || '')}">
            <input type="submit" value="${t.pages_search_btn || 'Search'}" data-action="doSearch"></form>`;
        if (d.searchMode === 'like' && d.results && d.results.length > 0) {
            html += `<div style="padding:0.25rem 0.5rem;font-size:0.7rem;color:var(--text-muted);text-align:center">${t.search_basic_mode || 'Basic search'}</div>`;
        }
        if (d.results && d.results.length > 0) {
            html += `<div style="padding:0.25rem 0.5rem;font-size:0.7rem;color:var(--text-muted)">${d.results.length} ${t.search_results_count || 'results'}</div>`;
            d.results.forEach(function(p) {
                var cls = p.status === 'inactive' ? ' class="inactive"' : '';
                html += `<div style="margin-bottom:0.5rem"><span class="far fa-file-alt"></span> <a${cls} name="a_${p.id}" href="#" data-action="post_page_show" data-arg="${p.id}" style="font-weight:600">${escHtml(p.title)}</a>`;
                if (p.snippet) html += `<div style="font-size:0.75rem;color:var(--text-secondary);margin-left:1.2rem;line-height:1.3;overflow:hidden;max-height:2.6em">${escHtml(p.snippet)}</div>`;
                if (p.modified) html += `<span style="font-size:0.65rem;color:var(--text-muted);margin-left:1.2rem">${escHtml(p.modified)}</span>`;
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
            html += `<div class="hide_links" id="index_retract"><a href="#" data-action="index_retract">${t.pages_collapse_all || 'Collapse'}</a></div>
                <div id="index_expand"><a href="#" data-action="index_expand">${t.pages_expand_all || 'Expand'}</a></div><ul>`;
            Object.keys(d.indexes).forEach(function(kw) {
                html += `<li class="list_page_hide"><a href="#" data-action="toggleLinks">${escHtml(kw)}</a><div class="index_pages hide_links">`;
                d.indexes[kw].forEach(function(e) {
                    var cls = e.status === 'inactive' ? ' class="inactive"' : '';
                    html += `<div style="white-space:nowrap;"><span class="far fa-file-alt"></span> <a${cls} name="a_${e.page_id}" href="#" data-action="post_page_show" data-arg="${e.page_id}">${escHtml(e.title)}</a></div>`;
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
        try { jQuery('#page_navigation').nestedSortable('destroy'); } catch(e) {}
        jQuery('#page_navigation').nestedSortable({
            forcePlaceholderSize: true, helper: 'clone', opacity: .6,
            placeholder: 'placeholder', handle: 'div', items: 'li:not(.unsortable)',
            protectRoot: true, rtl: (config.textDir === 'rtl'),
            startCollapsed: false, listType: 'ul', expandOnHover: 500,
            isTree: true, tolerance: 'pointer', toleranceElement: '> div', tabSize: 29,
            isAllowed: function() {
                if (state.mode === 'edit') { show_error(t.leave_edit || 'Please close the editor first.'); return false; }
                return true;
            },
            update: function() {
                jQuery('ul').not(':has(li)').remove();
                jQuery('li').not(':has(ul)').each(function() {
                    jQuery(this).find('span').first().removeClass('fas fa-folder-open').addClass('far fa-file-alt').css('color', 'var(--text-primary)').data('icon', 'document');
                });
                jQuery('li').has('ul').each(function() {
                    if (jQuery(this).find('span').first().data('icon') === 'document')
                        jQuery(this).find('span').first().removeClass('far fa-file-alt').addClass('fas fa-folder-open').css('color', 'var(--brand-accent)').data('icon', 'folder-open');
                });
                updateOrder(jQuery('#page_navigation').nestedSortable('serialize'));
            }
        });
    }

    function initContextMenu() {
        if (!state.isAuth) return;
        if (isContributor()) {
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
    }

    function createItemTemplate() {
        $newLi = jQuery('#new_li').clone();
        $newLi.find('ul').remove();
        $newLi.attr('id', 'list_new');
        $newLi.find('div').first().removeClass('hasmenu').addClass('hasmenu');
        $newLi.find('span').first().off('click.treeview').on('click.treeview', function() { treeView(this); });
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
            html += `<button data-action="compareSelected" class="toolbar-btn" style="width:auto;padding:0.25rem 0.75rem;font-size:0.8rem"><span class="fas fa-columns"></span>&nbsp;${t.revision_compare || 'Compare selected'}</button>`;
            html += '</div><div style="max-height:55vh;overflow-y:auto">';
            if (d.revisions && d.revisions.length > 0) {
                d.revisions.forEach(function(r, i) {
                    html += `<div style="padding:0.5rem;margin-bottom:0.5rem;background:var(--bg-body);border-radius:var(--radius-sm);display:flex;align-items:center;gap:0.5rem">
                        <input type="checkbox" class="rev-compare" value="${r.id}" style="flex-shrink:0">
                        <div style="flex:1;cursor:pointer" data-action="loadRevision" data-arg="${r.id}">
                        <strong>${escHtml(r.titlePreview)}</strong><br>
                        <small style="color:var(--text-muted)"><span class="fas fa-user" style="margin-right:0.2rem"></span>${escHtml(r.createdBy)} — ${escHtml(r.created || '')}</small>`;
                    if (r.note) html += `<br><small style="color:var(--text-secondary)"><span class="fas fa-comment" style="margin-right:0.2rem"></span>${escHtml(r.note)}</small>`;
                    html += `</div><span class="fas fa-eye" style="color:var(--text-muted);cursor:pointer" data-action="loadRevision" data-arg="${r.id}"></span></div>`;
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
                    <div><strong>${escHtml(rev1.title)}</strong><br><small style="color:var(--text-muted)">${escHtml(rev1.created || '')} — ${escHtml(rev1.createdBy)}</small></div>
                    <div style="text-align:right"><strong>${escHtml(rev2.title)}</strong><br><small style="color:var(--text-muted)">${escHtml(rev2.created || '')} — ${escHtml(rev2.createdBy)}</small></div>
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
            html += '<div><strong>' + escHtml(d.title) + '</strong><br><small style="color:var(--text-muted)">' + escHtml(d.created || '') + ' — ' + escHtml(d.createdBy) + '</small></div>';
            html += '<div style="display:flex;gap:0.5rem">';
            html += '<button data-action="toggleDiffView" class="toolbar-btn" style="width:auto;padding:0.25rem 0.75rem;font-size:0.8rem" title="' + (t.revision_diff || 'Compare') + '"><span class="fas fa-columns"></span>&nbsp;Diff</button>';
            html += '<button data-action="restoreRevision" data-arg="' + d.id + '" class="toolbar-btn" style="width:auto;padding:0.25rem 0.75rem;background:var(--brand-primary);color:#fff;border-radius:var(--radius-sm)">' + (t.revision_restore || 'Restore') + '</button>';
            html += '</div></div>';
            // Full content view
            html += '<div id="revContentView" style="border:1px solid var(--border-color);border-radius:var(--radius);padding:1rem;max-height:50vh;overflow-y:auto">' + d.content + '</div>';
            // Diff view (hidden initially)
            html += '<div id="revDiffView" style="display:none;max-height:50vh;overflow-y:auto"></div>';
            html += '<div style="text-align:right;margin-top:1rem"><button data-action="closeRevisionDetail" data-arg="' + d.page_id + '" class="toolbar-btn" style="width:auto;padding:0.25rem 0.75rem">' + (t.revision_back || 'Back to list') + '</button></div></div>';
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
        var doc = new DOMParser().parseFromString(html, 'text/html');
        return (doc.body.textContent || '').replace(/\r/g, '').trim();
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
        api('/pages/acknowledge', { page_id: pageId, locale: config.currentLocale || config.defaultLocale || 'en' }, function() {
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
                    <strong>${escHtml(c.user)}</strong> <small style="color:var(--text-muted)">${escHtml(c.created || '')}</small>
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
        var f = document.createElement('form');
        f.method = 'POST'; f.action = '/pages/export_md'; f.target = '_blank';
        var i = document.createElement('input'); i.type = 'hidden'; i.name = 'id'; i.value = pageId; f.appendChild(i);
        var c = document.createElement('input'); c.type = 'hidden'; c.name = '_csrfToken'; c.value = strCsrfToken || ''; f.appendChild(c);
        document.body.appendChild(f); f.submit(); document.body.removeChild(f);
    };
    window.exportPdf = function(pageId) {
        var f = document.createElement('form');
        f.method = 'POST'; f.action = '/pages/export_pdf'; f.target = '_blank';
        var i = document.createElement('input'); i.type = 'hidden'; i.name = 'id'; i.value = pageId; f.appendChild(i);
        var c = document.createElement('input'); c.type = 'hidden'; c.name = '_csrfToken'; c.value = strCsrfToken || ''; f.appendChild(c);
        document.body.appendChild(f); f.submit(); document.body.removeChild(f);
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
            clearTooltips(); jQuery('#page').html(renderEditView(d));
            initEditView(d);
        }, function() { show_error(t.pages_error_edit); });
    };

    // Expose state for edit-mode inline handlers (onkeyup etc.)
    window.pageState = state;

    // ══════════════════════════════════════════════════════
    // @Mentions — autocomplete for usernames in comments
    // ══════════════════════════════════════════════════════
    function initMentions() {
        if (!(config.publicConfig || {}).enableMentions) return;
        jQuery(document).on('input', '#commentInput', function() {
            var val = jQuery(this).val();
            var match = val.match(/@(\w{0,})$/);
            if (!match) { jQuery('#mentionDropdown').remove(); return; }
            api('/user/search_users', { q: match[1] || '' }, function(d) {
                jQuery('#mentionDropdown').remove();
                if (!d.users || !d.users.length) return;
                var $dd = jQuery('<ul id="mentionDropdown" style="position:absolute;z-index:9999;background:var(--bg-surface);border:1px solid var(--border-color);border-radius:var(--radius-sm);list-style:none;margin:0;padding:0.25rem 0;box-shadow:var(--shadow-md);max-height:150px;overflow-y:auto"></ul>');
                d.users.forEach(function(u) {
                    $dd.append(jQuery('<li style="padding:0.3rem 0.75rem;cursor:pointer;font-size:0.85rem">').text('@' + u.username + ' (' + u.fullname + ')').on('click', function() {
                        var input = jQuery('#commentInput');
                        input.val(input.val().replace(/@\w+$/, '@' + u.username + ' '));
                        jQuery('#mentionDropdown').remove();
                        input.focus();
                    }));
                });
                var $input = jQuery('#commentInput');
                var pos = $input.offset();
                $dd.css({ top: pos.top + $input.outerHeight(), left: pos.left });
                jQuery('body').append($dd);
            });
        });
        jQuery(document).on('click', function(e) { if (!jQuery(e.target).is('#commentInput')) jQuery('#mentionDropdown').remove(); });
    }

    // ══════════════════════════════════════════════════════
    // Scheduled Publishing UI (publish_at / expire_at fields in edit view)
    // ══════════════════════════════════════════════════════
    // Fields are rendered in renderEditView when enableScheduledPublishing is true.
    // The save() function picks them up automatically.

    // ══════════════════════════════════════════════════════
    // Review Process UI (assign reviewer, approve/reject)
    // ══════════════════════════════════════════════════════
    window.openReviewPanel = function(pageId) {
        var pub = config.publicConfig || {};
        if (!pub.enableWorkflow) return;
        api('/pages/page_reviews', { page_id: pageId }, function(d) {
            jQuery('#reviewPanel').remove();
            var html = '<div id="reviewPanel" style="margin-top:1.5rem;padding:1rem;border:1px solid var(--border-color);border-radius:var(--radius);background:var(--bg-body)">';
            html += '<h4 style="font-size:0.9rem;margin-bottom:0.75rem;color:var(--text-secondary)"><span class="fas fa-clipboard-check" style="margin-right:0.3rem"></span> Reviews</h4>';
            if (d.reviews && d.reviews.length) {
                d.reviews.forEach(function(r) {
                    html += '<div style="padding:0.35rem 0;border-bottom:1px solid var(--border-light);font-size:0.85rem;display:flex;justify-content:space-between">';
                    html += '<span>' + escHtml(r.reviewerName) + ' — <strong>' + escHtml(r.status) + '</strong></span>';
                    html += '<span style="color:var(--text-muted);font-size:0.75rem">' + escHtml(r.created || '') + '</span></div>';
                });
            } else {
                html += '<p style="color:var(--text-muted);font-size:0.85rem">No reviews yet.</p>';
            }
            if (isContributor()) {
                html += '<div style="margin-top:0.75rem;display:flex;gap:0.5rem;align-items:center">';
                html += '<input type="text" id="reviewerUsername" class="form-control form-control-sm" placeholder="Reviewer username" style="max-width:200px">';
                html += '<button class="btn btn-sm btn-outline-primary" data-action="assignReviewer" data-arg="' + pageId + '">Assign</button></div>';
            }
            html += '</div>';
            jQuery('#content_pane').append(html);
        });
    };

    window.assignReviewer = function(pageId) {
        var username = jQuery('#reviewerUsername').val().trim();
        if (!username) return;
        api('/pages/assign_reviewer', { page_id: pageId, reviewer_username: username }, function(d) {
            if (d.success !== false) { show_success('Reviewer assigned.'); openReviewPanel(pageId); }
            else show_error(d.error || 'Failed to assign reviewer.');
        }, function() { show_error('Failed to assign reviewer.'); });
    };

    // ══════════════════════════════════════════════════════
    // Media Library — browse uploaded media with usage info
    // ══════════════════════════════════════════════════════
    window.openMediaLibrary = function() {
        api('/media', {}, function(d) {
            jQuery('#mediaLibraryModal').remove();
            var html = '<div id="mediaLibraryModal" style="display:block;position:fixed;top:0;left:0;right:0;bottom:0;z-index:10000;background:rgba(0,0,0,0.5)">' +
                '<div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:var(--bg-surface);color:var(--text-primary);border-radius:var(--radius);width:800px;max-width:90vw;height:70vh;display:flex;flex-direction:column">' +
                '<div style="padding:0.75rem 1rem;border-bottom:1px solid var(--border-color);font-weight:bold;display:flex;justify-content:space-between;align-items:center">' +
                '<span><span class="fas fa-photo-video" style="margin-right:0.3rem"></span> Media Library</span>' +
                '<button type="button" class="btn btn-sm btn-outline-secondary" data-action="closeMediaLibrary">&times;</button></div>' +
                '<div style="flex:1;overflow-y:auto;padding:1rem">';
            if (d.files && d.files.length) {
                html += '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:1rem">';
                d.files.forEach(function(f) {
                    var isImage = /\.(jpg|jpeg|png|gif|webp|svg)$/i.test(f.name);
                    html += '<div style="border:1px solid var(--border-color);border-radius:var(--radius-sm);overflow:hidden">';
                    if (isImage) {
                        html += '<div style="height:120px;overflow:hidden;background:var(--bg-body);display:flex;align-items:center;justify-content:center"><img src="' + escAttr(f.url) + '" style="max-width:100%;max-height:120px;object-fit:contain"></div>';
                    } else {
                        html += '<div style="height:120px;display:flex;align-items:center;justify-content:center;background:var(--bg-body)"><span class="fas fa-file" style="font-size:2rem;color:var(--text-muted)"></span></div>';
                    }
                    html += '<div style="padding:0.5rem;font-size:0.8rem">';
                    html += '<div style="font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="' + escAttr(f.name) + '">' + escHtml(f.name) + '</div>';
                    html += '<div style="color:var(--text-muted)">' + (f.size || '') + '</div>';
                    if (f.usedIn && f.usedIn.length) {
                        html += '<div style="color:var(--text-secondary);font-size:0.7rem">Used in ' + f.usedIn.length + ' page(s)</div>';
                    } else {
                        html += '<div style="color:var(--color-error-text);font-size:0.7rem">Unused</div>';
                    }
                    html += '</div></div>';
                });
                html += '</div>';
            } else {
                html += '<p style="text-align:center;color:var(--text-muted)">No media files.</p>';
            }
            html += '</div></div></div>';
            jQuery('body').append(html);
        });
    };

    // ══════════════════════════════════════════════════════
    // Acknowledgement Report
    // ══════════════════════════════════════════════════════
    window.openAckReport = function(pageId) {
        api('/pages/ack_report', { page_id: pageId || 0 }, function(d) {
            jQuery('#ackReportPanel').remove();
            var html = '<div id="ackReportPanel" style="margin-top:1.5rem;padding:1rem;border:1px solid var(--border-color);border-radius:var(--radius);background:var(--bg-body)">';
            html += '<h4 style="font-size:0.9rem;margin-bottom:0.75rem;color:var(--text-secondary)"><span class="fas fa-check-double" style="margin-right:0.3rem"></span> Acknowledgements</h4>';
            if (d.acknowledgements && d.acknowledgements.length) {
                d.acknowledgements.forEach(function(a) {
                    var validIcon = a.valid
                        ? '<span class="fas fa-check-circle" style="color:var(--brand-primary);margin-right:0.3rem" title="Valid"></span>'
                        : '<span class="fas fa-exclamation-triangle" style="color:var(--color-error-text);margin-right:0.3rem" title="Invalidated — page changed after acknowledgement"></span>';
                    html += '<div style="padding:0.35rem 0;border-bottom:1px solid var(--border-light);font-size:0.85rem;display:flex;justify-content:space-between;align-items:center">';
                    html += '<span>' + validIcon + '<strong>' + escHtml(a.userName) + '</strong> — ' + escHtml(a.pageTitle) + '</span>';
                    html += '<span style="color:var(--text-muted);font-size:0.75rem">' + escHtml(a.confirmedAt || '') + '</span></div>';
                });
            } else {
                html += '<p style="color:var(--text-muted);font-size:0.85rem">No acknowledgements yet.</p>';
            }
            html += '</div>';
            jQuery('#content_pane').append(html);
        });
    };

    // ══════════════════════════════════════════════════════
    // Smart Links — autocomplete for internal links in editor
    // ══════════════════════════════════════════════════════
    function initSmartLinks() {
        if (!(config.publicConfig || {}).enableSmartLinks) return;

        // Override Summernote link dialog: add autocomplete field
        jQuery(document).on('shown.bs.modal show.bs.modal', '.note-modal', function() {
            var $body = jQuery(this).find('.note-modal-body, .modal-body').first();
            if ($body.find('#smartLinkSearch').length) return;
            var $urlInput = $body.find('.note-input[data-event="insertLink"], input[aria-label="URL"], .note-link-url').first();
            if (!$urlInput.length) $urlInput = $body.find('input[type="text"]').first();
            if (!$urlInput.length) return;

            var searchHtml = '<div id="smartLinkSearch" style="margin-bottom:0.75rem">' +
                '<label style="display:block;font-size:0.8rem;font-weight:600;color:var(--text-secondary);margin-bottom:0.25rem">' +
                '<span class="fas fa-search" style="margin-right:0.3rem"></span>' + (t.pages_search_btn || 'Search pages') + '</label>' +
                '<input type="text" id="smartLinkInput" class="note-input" placeholder="' + (t.pages_search_btn || 'Type to search pages...') + '" style="width:100%">' +
                '<ul id="smartLinkResults" style="list-style:none;margin:0.25rem 0 0;padding:0;max-height:150px;overflow-y:auto"></ul></div>';
            $urlInput.closest('.note-form-group, .form-group, div').first().before(searchHtml);

            var debounce;
            jQuery('#smartLinkInput').on('input', function() {
                var q = jQuery(this).val();
                clearTimeout(debounce);
                if (q.length < 2) { jQuery('#smartLinkResults').empty(); return; }
                debounce = setTimeout(function() {
                    api('/pages/link_suggest', { q: q }, function(d) {
                        var $list = jQuery('#smartLinkResults').empty();
                        if (d.pages) d.pages.forEach(function(p) {
                            $list.append(jQuery('<li style="padding:0.3rem 0.5rem;cursor:pointer;border-bottom:1px solid var(--border-light);font-size:0.85rem">').text(p.title).on('click', function() {
                                $urlInput.val(p.url).trigger('input').trigger('change');
                                jQuery('#smartLinkResults').empty();
                                jQuery('#smartLinkInput').val('');
                            }));
                        });
                    });
                }, 250);
            });
        });
    }

    // ══════════════════════════════════════════════════════
    // Import — UI for importing HTML/Markdown files
    // ══════════════════════════════════════════════════════
    window.openImportDialog = function() {
        if (jQuery('#importModal').length) { jQuery('#importModal').show(); return; }
        jQuery('body').append(
            '<div id="importModal" style="display:block;position:fixed;top:0;left:0;right:0;bottom:0;z-index:10000;background:rgba(0,0,0,0.5)">' +
            '<div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:var(--bg-surface);color:var(--text-primary);border-radius:var(--radius);width:600px;max-width:90vw;padding:0;display:flex;flex-direction:column">' +
            '<div style="padding:0.75rem 1rem;border-bottom:1px solid var(--border-color);font-weight:bold;display:flex;justify-content:space-between;align-items:center">' +
            '<span><span class="fas fa-file-import" style="margin-right:0.3rem"></span> Import</span>' +
            '<button type="button" class="btn btn-sm btn-outline-secondary" data-action="closeImportModal">&times;</button></div>' +
            '<div style="padding:1.25rem">' +
            '<div style="margin-bottom:1rem"><label style="display:block;font-size:0.8rem;font-weight:600;color:var(--text-secondary);margin-bottom:0.25rem">Title</label>' +
            '<input type="text" id="importTitle" class="form-control" placeholder="Page title"></div>' +
            '<div style="margin-bottom:1rem"><label style="display:block;font-size:0.8rem;font-weight:600;color:var(--text-secondary);margin-bottom:0.25rem">Format</label>' +
            '<select id="importFormat" class="form-select"><option value="html">HTML</option><option value="markdown">Markdown</option></select></div>' +
            '<div style="margin-bottom:1rem"><label style="display:block;font-size:0.8rem;font-weight:600;color:var(--text-secondary);margin-bottom:0.25rem">File</label>' +
            '<input type="file" id="importFile" class="form-control" accept=".html,.htm,.md,.markdown,.txt"></div>' +
            '<div style="margin-bottom:1rem"><label style="display:block;font-size:0.8rem;font-weight:600;color:var(--text-secondary);margin-bottom:0.25rem">Or paste content</label>' +
            '<textarea id="importContent" class="form-control" rows="6" placeholder="Paste HTML or Markdown here..."></textarea></div>' +
            '<div style="text-align:right">' +
            '<button class="btn btn-secondary btn-sm" data-action="closeImportModal" style="margin-right:0.5rem">Cancel</button>' +
            '<button class="btn btn-primary btn-sm" data-action="doImport">Import</button></div>' +
            '</div></div></div>'
        );
    };

    window.doImport = function() {
        var file = document.getElementById('importFile').files[0];
        var fd = new FormData();
        fd.append('title', jQuery('#importTitle').val());
        fd.append('format', jQuery('#importFormat').val());
        if (file) {
            fd.append('file', file);
        } else {
            fd.append('content', jQuery('#importContent').val());
        }
        jQuery.ajax({
            url: '/pages/import', type: 'POST', data: fd,
            processData: false, contentType: false,
            headers: { 'X-CSRF-Token': strCsrfToken },
            success: function(d) {
                if (d && d.id) {
                    jQuery('#importModal').hide();
                    show_success('Page imported: ' + (d.title || ''));
                    loadTree();
                    showPage(d.id, true);
                } else {
                    var errMsg = {
                        'no_content': t.import_no_content || 'Please provide content or upload a file.',
                        'invalid_format': t.import_invalid_format || 'Unsupported import format.',
                        'save_failed': t.import_save_failed || 'Could not save the imported page.',
                        'feature_disabled': t.import_disabled || 'Import is not enabled.'
                    };
                    show_error(d && d.error ? (errMsg[d.error] || d.error) : (t.import_failed || 'Import failed.'));
                }
            },
            error: function() { show_error('Import failed'); }
        });
    };

    // ══════════════════════════════════════════════════════
    // Inline Comments — margin annotations on page content
    // ══════════════════════════════════════════════════════
    function initInlineComments() {
        if (!(config.publicConfig || {}).enableInlineComments) return;
        if (!state.isAuth) return;

        // Load and display inline comments for a page
        window.loadInlineComments = function(pageId) {
            api('/pages/inline_comments', { page_id: pageId }, function(d) {
                jQuery('#inlineCommentsPanel').remove();
                if (!d.inlineComments || !d.inlineComments.length) return;

                var all = d.inlineComments;
                // Separate root comments (no parent) and replies
                var roots = all.filter(function(c) { return !c.parentId && !c.resolved; });
                var replies = {};
                all.forEach(function(c) {
                    if (c.parentId) {
                        if (!replies[c.parentId]) replies[c.parentId] = [];
                        replies[c.parentId].push(c);
                    }
                });
                if (!roots.length) return;

                function renderComment(c, depth) {
                    var indent = depth > 0 ? 'margin-left:' + (depth * 1.5) + 'rem;border-left-color:var(--text-muted)' : '';
                    var h = '<div class="inline-comment" data-comment-id="' + c.id + '" data-anchor="' + escAttr(c.anchor) + '" style="padding:0.5rem 0.75rem;margin-bottom:0.5rem;background:var(--bg-body);border-left:3px solid var(--brand-primary);border-radius:0 var(--radius-sm) var(--radius-sm) 0;font-size:0.85rem;' + indent + '">' +
                        '<div style="display:flex;justify-content:space-between;align-items:center">' +
                        '<span style="font-weight:600;color:var(--text-primary)">' + escHtml(c.user) + '</span>' +
                        '<span style="font-size:0.7rem;color:var(--text-muted)">' + escHtml(c.created || '') + '</span></div>' +
                        '<div style="color:var(--text-primary);margin-top:0.25rem">' + escHtml(c.comment) + '</div>';
                    if (c.anchor && depth === 0) {
                        h += '<div style="font-size:0.7rem;color:var(--text-muted);margin-top:0.25rem">' +
                            '<span class="fas fa-quote-left" style="margin-right:0.2rem"></span>' + escHtml(c.anchor.substring(0, 80)) + (c.anchor.length > 80 ? '...' : '') + '</div>';
                    }
                    h += '<div style="margin-top:0.35rem;display:flex;gap:0.4rem">';
                    h += '<button data-action="replyInlineComment" data-arg="' + c.id + '" data-arg2="' + pageId + '" style="font-size:0.7rem;padding:0.15rem 0.5rem;background:var(--bg-hover);border:1px solid var(--border-color);border-radius:var(--radius-sm);cursor:pointer;color:var(--text-secondary)"><span class="fas fa-reply"></span> Reply</button>';
                    if (!c.resolved && depth === 0) {
                        h += '<button data-action="resolveInlineComment" data-arg="' + c.id + '" data-arg2="' + state.currentId + '" style="font-size:0.7rem;padding:0.15rem 0.5rem;background:var(--bg-hover);border:1px solid var(--border-color);border-radius:var(--radius-sm);cursor:pointer;color:var(--text-secondary)"><span class="fas fa-check"></span> Resolve</button>';
                    }
                    h += '</div></div>';

                    // Render replies (threaded, incrementing depth)
                    if (replies[c.id]) {
                        replies[c.id].forEach(function(r) {
                            h += renderComment(r, depth + 1);
                        });
                    }
                    return h;
                }

                var html = '<div id="inlineCommentsPanel" style="margin-top:1.5rem;padding-top:1rem;border-top:1px solid var(--border-color)">' +
                    '<h4 style="font-size:0.9rem;color:var(--text-secondary);margin-bottom:0.75rem"><span class="fas fa-comment-dots" style="margin-right:0.3rem"></span> Inline Comments</h4>';
                roots.forEach(function(c) { html += renderComment(c, 0); });
                html += '</div>';
                jQuery('#content_pane').append(html);

                // Apply tooltips to <mark data-comment-id> tags already in editor content
                applyInlineCommentTooltips(d.inlineComments);
            });
        };

        // ── Inline comment: highlight + tooltip helpers ──

        // Add tooltips to existing <mark data-comment-id> tags in the editor
        function applyInlineCommentTooltips(comments) {
            var $editable = jQuery('.note-editable');
            if (!$editable.length) return;
            $editable.find('mark[data-comment-id]').each(function() {
                var $m = jQuery(this);
                var cid = $m.attr('data-comment-id');
                var c = comments.find(function(x) { return String(x.id) === String(cid); });
                if (c) {
                    $m.attr('title', c.user + ': ' + c.comment);
                }
            });
        }

        // Add inline comment: wrap selection with <mark> in editor, then save to backend
        var _lastRange = null;
        var _lastAnchor = '';
        jQuery(document).on('mousedown', '[data-action="addInlineCommentFromSelection"]', function() {
            _lastRange = null;
            _lastAnchor = '';
            var editable = jQuery('.note-editable')[0];
            if (!editable) return;
            try {
                var sel = editable.ownerDocument.defaultView.getSelection();
                if (sel && sel.rangeCount > 0 && sel.toString().trim()) {
                    _lastRange = sel.getRangeAt(0).cloneRange();
                    _lastAnchor = sel.toString().trim();
                }
            } catch(e) {}
        });

        window.addInlineCommentFromSelection = function(pageId) {
            if (!_lastRange || !_lastAnchor) {
                show_error(t.inline_comment_select || 'Please select text in the editor first.');
                return;
            }
            var anchor = _lastAnchor;
            var range = _lastRange;
            _lastRange = null;
            _lastAnchor = '';

            var comment = prompt('Comment on "' + anchor.substring(0, 50) + (anchor.length > 50 ? '...' : '') + '":');
            if (!comment) return;

            api('/pages/add_inline_comment', { page_id: pageId, anchor: anchor.substring(0, 100), comment: comment }, function(d) {
                if (d.id) {
                    // Wrap selection with <mark> in editor at the exact position
                    try {
                        var mark = document.createElement('mark');
                        mark.setAttribute('data-comment-id', d.id);
                        mark.setAttribute('title', comment);
                        
                        
                        
                        range.surroundContents(mark);
                    } catch(e) {
                        // surroundContents fails if selection crosses element boundaries
                        // Fallback: just insert mark around the text
                        try {
                            var frag = range.extractContents();
                            var mark = document.createElement('mark');
                            mark.setAttribute('data-comment-id', d.id);
                            mark.setAttribute('title', comment);
                            
                            
                            
                            mark.appendChild(frag);
                            range.insertNode(mark);
                        } catch(e2) {}
                    }
                    // Silent save: persist <mark> tags without creating a revision
                    var editorContent = jQuery('#editor').summernote('code');
                    var savedHasChanges = state.hasChanges;
                    api('/pages/save_content_silent', { id: pageId, content: editorContent }, function() {
                        state.hasChanges = savedHasChanges; // restore — silent save doesn't count as user edit
                    }, function() {});
                    show_success('Inline comment added.');
                    loadInlineComments(pageId);
                }
            }, function() { show_error('Failed to add inline comment.'); });
        };

        window.resolveInlineComment = function(commentId, pageId) {
            api('/pages/resolve_inline_comment', { id: commentId }, function(d) {
                if (d.resolved) {
                    // Remove <mark> from editor content
                    jQuery('.note-editable mark[data-comment-id="' + commentId + '"]').each(function() {
                        jQuery(this).replaceWith(jQuery(this).html());
                    });
                    // Silent save: persist mark removal without creating a revision
                    var editorContent = jQuery('#editor').summernote('code');
                    var savedHasChanges = state.hasChanges;
                    api('/pages/save_content_silent', { id: pageId, content: editorContent }, function() {
                        state.hasChanges = savedHasChanges;
                    }, function() {});
                    show_success('Comment resolved.');
                    loadInlineComments(pageId);
                }
            }, function() { show_error('Failed to resolve comment.'); });
        };

        window.replyInlineComment = function(parentId, pageId) {
            var reply = prompt('Reply:');
            if (!reply) return;
            api('/pages/add_inline_comment', { page_id: pageId, parent_id: parentId, anchor: '', comment: reply }, function(d) {
                if (d.id) {
                    show_success('Reply added.');
                    loadInlineComments(pageId);
                }
            }, function() { show_error('Failed to add reply.'); });
        }
    }

    // ── Expose functions globally for SSR templates and delegated handlers ──
    window.post_page_show = showPage;
    window.post_page_edit = editPage;
    window.post_page_create = createPage;
    window.new_page = addRootNode;
    window.post_get_search = doSearch;
    window.show_sidebar = showSidebar;
    window.tree_view = treeView;
    window.toggle_links = toggleLinks;
    window.index_retract = indexRetract;
    window.index_expand = indexExpand;
    window.post_get_index = loadIndex;
    window.resize_page_view = resizePageView;
    window.message_leave_edit = function() { show_error(t.leave_edit); };

})();
