<?php
$isAuth = !empty($auth['id']);
$isContributor = in_array($auth['role'] ?? '', ['contributor', 'admin'], true);
$nonce = $this->request->getAttribute('cspNonce') ?? '';
$browseMode = $browseMode ?? '';
?>
<div class="container-fluid" style="padding:1.25rem 2rem">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
        <h2 style="margin:0"><?= $browseMode ? __('Select file') : __('Manage files') ?></h2>
        <div style="display:flex;gap:0.5rem;align-items:center">
            <?php if ($isContributor): ?>
            <?php if (!$browseMode): ?><button class="btn btn-outline-danger btn-sm" id="btnBulkDelete" style="display:none" data-action="bulkDelete"><span class="fas fa-trash-alt"></span> <?= __('Delete selected') ?></button><?php endif; ?>
            <button class="btn btn-outline-secondary btn-sm" data-action="createFolder"><span class="fas fa-folder-plus"></span> <?= __('New folder') ?></button>
            <?php endif; ?>
            <?php if ($isAuth): ?>
            <label class="btn btn-primary btn-sm" style="margin:0;cursor:pointer"><span class="fas fa-upload"></span> <?= __('Upload') ?><input type="file" id="file_upload" style="display:none" multiple data-action-change="uploadFiles"></label>
            <?php endif; ?>
        </div>
    </div>

    <div id="fileBreadcrumb" style="margin-bottom:0.75rem;font-size:0.85rem"></div>

    <?php if ($browseMode): ?>
    <div class="row fw-bold border-bottom pb-1 mb-1" style="font-size:0.85rem">
        <div class="col"><?= __('Name') ?></div>
        <div class="col-1 text-end"><?= __('Size') ?></div>
        <div class="col-2 text-center"><?= __('Select') ?></div>
    </div>
    <?php else: ?>
    <div class="row fw-bold border-bottom pb-1 mb-1" style="font-size:0.85rem">
        <?php if ($isContributor): ?><div class="col-auto" style="width:2rem"><input type="checkbox" id="selectAll" data-action="toggleSelectAll"></div><?php endif; ?>
        <div class="<?= $isContributor ? 'col' : 'col-5' ?>"><?= __('Name') ?></div>
        <div class="col-1 text-end"><?= __('Size') ?></div>
        <div class="col-2 text-center"><?= __('Date') ?></div>
        <div class="col-1 text-end"><?= __('Downloads') ?></div>
        <div class="col-1"><?= __('Usage') ?></div>
        <?php if ($isContributor): ?><div class="col-1 text-center"><?= __('Actions') ?></div><?php endif; ?>
    </div>
    <?php endif; ?>
    <div id="fileListBody" style="min-height:100px"></div>
</div>

<script nonce="<?= $nonce ?>">
var currentFolder = null;
var browseMode = '<?= h($browseMode) ?>';
var browseFilter = function(f) {
    if (!browseMode) return true;
    if (browseMode === 'image') return /^image\//i.test(f.mime);
    if (browseMode === 'video') return /^video\//i.test(f.mime) || /\.(mp4|webm|ogg|ogv)$/i.test(f.name);
    return true; // 'link' mode shows all files
};
var isContributor = <?= $isContributor ? 'true' : 'false' ?>;

function loadFiles(folderId) {
    currentFolder = folderId === undefined ? null : folderId;
    jQuery.post('/file/list', { folder_id: currentFolder || '' }, function(d) {
        if (!d || d.error) { show_error('Failed to load files.'); return; }
        renderBreadcrumb(d.breadcrumb || []);
        renderFileList(d.folders || [], d.files || []);
        updateBulkButton();
    }, 'json');
}

function renderBreadcrumb(crumbs) {
    var html = '<a href="#" data-action="loadFiles" style="color:var(--text-link);text-decoration:none"><span class="fas fa-home"></span> Root</a>';
    crumbs.forEach(function(c) {
        html += ' <span style="margin:0 0.3rem;color:var(--text-muted)">/</span> ';
        html += '<a href="#" data-action="loadFiles" data-arg="' + c.id + '" style="color:var(--text-link);text-decoration:none">' + escHtml(c.name) + '</a>';
    });
    jQuery('#fileBreadcrumb').html(html);
}

function renderFileList(folders, files) {
    var html = '';
    var cb = isContributor ? '<div class="col-auto" style="width:2rem">' : '';
    var cbEnd = isContributor ? '</div>' : '';
    var nameCol = isContributor ? 'col' : 'col-5';

    if (currentFolder !== null) {
        html += '<div class="row py-1 border-bottom align-items-center drop-target" data-drop-folder="__PARENT__" style="cursor:pointer" data-action="goUp">';
        if (isContributor) html += '<div class="col-auto" style="width:2rem"></div>';
        html += '<div class="' + nameCol + '"><span class="fas fa-level-up-alt" style="color:var(--text-muted);margin-right:0.5rem"></span> ..</div><div class="col-1"></div><div class="col-2"></div><div class="col-1"></div><div class="col-1"></div>';
        if (isContributor) html += '<div class="col-1"></div>';
        html += '</div>';
    }

    folders.forEach(function(f) {
        html += '<div class="row py-1 border-bottom align-items-center drop-target" id="folder_' + f.id + '" draggable="true" data-drag-type="folder" data-drag-id="' + f.id + '" data-drop-folder="' + f.id + '">';
        if (isContributor) html += '<div class="col-auto" style="width:2rem"><input type="checkbox" class="item-cb" data-type="folder" data-id="' + f.id + '" data-action="updateBulkButton"></div>';
        html += '<div class="' + nameCol + '" style="cursor:pointer" data-action="loadFiles" data-arg="' + f.id + '"><span class="fas fa-folder" style="color:var(--brand-primary);margin-right:0.5rem"></span> <strong>' + escHtml(f.name) + '</strong></div>';
        html += '<div class="col-1"></div><div class="col-2 text-center" style="font-size:0.8rem;color:var(--text-muted)">' + f.created + '</div>';
        html += '<div class="col-1"></div><div class="col-1"></div>';
        if (isContributor) html += '<div class="col-1 text-center"><button class="btn btn-sm btn-outline-secondary" data-action="renameFolder" data-arg="' + f.id + '" data-arg2="' + escHtml(f.name).replace(/"/g, '&quot;') + '" title="Rename"><span class="fas fa-pen"></span></button> <button class="btn btn-sm btn-outline-danger" data-action="deleteSingleFolder" data-arg="' + f.id + '" title="Delete"><span class="fas fa-trash-alt"></span></button></div>';
        html += '</div>';
    });

    var filteredFiles = browseMode ? files.filter(browseFilter) : files;
    filteredFiles.forEach(function(f) {
        var isImage = /\.(jpg|jpeg|png|gif|webp|svg)$/i.test(f.name);
        var icon = isImage ? 'fa-image' : 'fa-file';
        var hasUsage = f.usedIn && f.usedIn.length > 0;
        var usageHtml = hasUsage
            ? '<span style="color:var(--text-secondary);font-size:0.75rem" title="' + escHtml(f.usedIn.map(function(u){ return u.title; }).join(', ')).replace(/"/g, '&quot;') + '">' + f.usedIn.length + '</span>'
            : '<span style="color:var(--color-error-text);font-size:0.75rem">0</span>';
        var modeIcon = f.displayMode === 'inline' ? 'fa-eye' : 'fa-download';

        html += '<div class="row py-1 border-bottom align-items-center" id="file_' + f.id + '" draggable="true" data-drag-type="file" data-drag-id="' + f.id + '">';
        if (!browseMode && isContributor) html += '<div class="col-auto" style="width:2rem"><input type="checkbox" class="item-cb" data-type="file" data-id="' + f.id + '" data-usage="' + (hasUsage ? 1 : 0) + '" data-action="updateBulkButton"></div>';
        if (browseMode) {
            var preview = isImage ? '<img src="' + escAttr(f.url) + '" style="width:24px;height:24px;object-fit:cover;border-radius:2px;margin-right:0.5rem">' : '<span class="fas ' + icon + '" style="color:var(--text-muted);margin-right:0.5rem"></span>';
            html += '<div class="col">' + preview + escHtml(f.name) + '</div>';
            html += '<div class="col-1 text-end" style="font-size:0.8rem">' + f.size + '</div>';
            html += '<div class="col-2 text-center"><button class="btn btn-sm btn-primary browse-select-btn" data-url="' + escAttr(f.url) + '"><span class="fas fa-check"></span> Select</button></div>';
        } else {
            var linkTarget = f.displayMode === 'inline' ? ' target="_blank" rel="noopener"' : '';
            html += '<div class="' + nameCol + '"><span class="fas ' + icon + '" style="color:var(--text-muted);margin-right:0.5rem"></span><a href="' + escAttr(f.url) + '"' + linkTarget + ' style="color:var(--text-link);text-decoration:none">' + escHtml(f.name) + '</a> <span class="fas ' + modeIcon + '" style="font-size:0.6rem;color:var(--text-muted);margin-left:0.3rem" title="' + escAttr(f.displayMode) + '"></span></div>';
            html += '<div class="col-1 text-end" style="font-size:0.8rem">' + f.size + '</div>';
            html += '<div class="col-2 text-center" style="font-size:0.8rem;color:var(--text-muted)">' + f.created + '</div>';
            html += '<div class="col-1 text-end" style="font-size:0.8rem">' + f.downloads + '</div>';
            html += '<div class="col-1">' + usageHtml + '</div>';
            if (isContributor) {
                html += '<div class="col-1 text-center">';
                html += '<button class="btn btn-sm btn-outline-secondary" data-action="renameFile" data-arg="' + f.id + '" data-arg2="' + escHtml(f.name).replace(/"/g, '&quot;') + '" title="Rename"><span class="fas fa-pen"></span></button> ';
                html += '<button class="btn btn-sm btn-outline-info" data-action="openFileSettings" data-arg="' + f.id + '" title="Settings"><span class="fas fa-cog"></span></button>';
                if (!hasUsage) html += ' <button class="btn btn-sm btn-outline-danger" data-action="deleteSingleFile" data-arg="' + f.id + '" title="Delete"><span class="fas fa-trash-alt"></span></button>';
                html += '</div>';
            }
        }
        html += '</div>';
        // Settings panel
        html += '<div id="settings_' + f.id + '" style="display:none" class="row py-2 border-bottom">';
        html += '<div class="col-12" style="padding:0.5rem 1rem;background:var(--bg-body);border-radius:var(--radius-sm)">';
        html += '<div style="display:flex;gap:1.5rem;align-items:center;flex-wrap:wrap;font-size:0.8rem">';
        html += '<label style="font-weight:600">Display:</label>';
        html += '<select id="mode_' + f.id + '" class="form-select form-select-sm" style="width:auto"><option value="download"' + (f.displayMode === 'download' ? ' selected' : '') + '>Download</option><option value="inline"' + (f.displayMode === 'inline' ? ' selected' : '') + '>Inline</option></select>';
        html += '<label style="font-weight:600;margin-left:1rem">Visible:</label>';
        html += '<label><input type="checkbox" id="vg_' + f.id + '"' + (f.visibleGuest ? ' checked' : '') + '> Guest</label>';
        html += '<label><input type="checkbox" id="ve_' + f.id + '"' + (f.visibleEditor ? ' checked' : '') + '> Editor</label>';
        html += '<label><input type="checkbox" id="vc_' + f.id + '"' + (f.visibleContributor ? ' checked' : '') + '> Contr.</label>';
        html += '<label><input type="checkbox" id="va_' + f.id + '"' + (f.visibleAdmin ? ' checked' : '') + '> Admin</label>';
        html += '<button class="btn btn-sm btn-primary" data-action="saveFileSettings" data-arg="' + f.id + '">Save</button>';
        html += '</div></div></div>';
    });

    if (!folders.length && !files.length) {
        var msg = currentFolder === null ? '<?= __('No files yet. Click Upload or drag files and folders here.') ?>' : '<?= __('Empty folder. Drag files here or click Upload.') ?>';
        html += '<div class="drop-zone-empty drop-target" data-drop-folder="' + (currentFolder || '') + '" style="padding:3rem 2rem;text-align:center;color:var(--text-muted);border:2px dashed var(--border-color);border-radius:var(--radius);margin:1rem 0;cursor:default"><span class="fas fa-cloud-upload-alt" style="font-size:2rem;display:block;margin-bottom:0.5rem"></span>' + msg + '</div>';
    }
    jQuery('#fileListBody').html(html);
    jQuery('#selectAll').prop('checked', false);
}

function goUp() {
    var bc = jQuery('#fileBreadcrumb a');
    if (bc.length >= 2) bc.eq(bc.length - 2).click();
    else loadFiles(null);
}

function escHtml(s) { return jQuery('<span>').text(s).html(); }
function escAttr(s) { return (s || '').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/'/g,'&#39;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

// ── Selection ──
function toggleSelectAll(el) {
    jQuery('.item-cb').prop('checked', el.checked);
    updateBulkButton();
}

function updateBulkButton() {
    var count = jQuery('.item-cb:checked').length;
    jQuery('#btnBulkDelete').toggle(count > 0);
}

function getSelected() {
    var files = [], folders = [];
    jQuery('.item-cb:checked').each(function() {
        if (jQuery(this).data('type') === 'file') files.push(jQuery(this).data('id'));
        else folders.push(jQuery(this).data('id'));
    });
    return { files: files, folders: folders };
}

// ── Actions ──
function bulkDelete() {
    var sel = getSelected();
    var total = sel.files.length + sel.folders.length;
    if (!total || !confirm('Delete ' + total + ' selected item(s)? Files in use will be skipped.')) return;
    var done = 0, errors = [];
    function finish() {
        if (errors.length) show_error(errors.join(', '));
        else show_success(done + ' item(s) deleted.');
        loadFiles(currentFolder);
    }
    if (sel.files.length) {
        jQuery.post('/file/delete', { ids: sel.files }, function(d) {
            done += d.deleted || 0;
            if (d.blocked && d.blocked.length) errors.push(d.blocked.length + ' file(s) in use');
            if (!sel.folders.length) finish();
        }, 'json');
    }
    if (sel.folders.length) {
        jQuery.post('/file/delete_folder', { ids: sel.folders }, function(d) {
            done += d.deleted || 0;
            if (d.blocked && d.blocked.length) errors.push(d.blocked.length + ' folder(s) contain used files');
            finish();
        }, 'json');
    }
    if (!sel.files.length && !sel.folders.length) finish();
}

function deleteSingleFile(id) {
    if (!confirm(t.file_confirm_delete || 'Delete?')) return;
    jQuery.post('/file/delete', { id: id }, function(d) {
        if (d.blocked && d.blocked.length) show_error('File in use — cannot delete.');
        else { show_success(t.file_deleted || 'Deleted.'); loadFiles(currentFolder); }
    }, 'json');
}

function deleteSingleFolder(id) {
    if (!confirm('Delete folder? Folders with used files will be skipped.')) return;
    jQuery.post('/file/delete_folder', { id: id }, function(d) {
        if (d.blocked && d.blocked.length) show_error('Folder contains used files — cannot delete.');
        else { show_success('Deleted.'); loadFiles(currentFolder); }
    }, 'json');
}

// ── Recursive directory upload via webkitGetAsEntry ──
function uploadEntries(items, parentFolderId) {
    var pending = 0;
    function done() { if (--pending <= 0) loadFiles(currentFolder); }

    for (var i = 0; i < items.length; i++) {
        var entry = items[i].webkitGetAsEntry ? items[i].webkitGetAsEntry() : null;
        if (!entry) continue;
        pending++;
        processEntry(entry, parentFolderId, done);
    }
    if (pending === 0) loadFiles(currentFolder);
}

function processEntry(entry, parentFolderId, done) {
    if (entry.isFile) {
        entry.file(function(file) {
            var fd = new FormData();
            fd.append('file', file);
            fd.append('folder_id', parentFolderId || '');
            jQuery.ajax({
                url: '/file/upload', type: 'POST', data: fd,
                processData: false, contentType: false,
                headers: { 'X-CSRF-Token': strCsrfToken },
                success: function() { done(); },
                error: function() { show_error('Upload failed: ' + file.name); done(); }
            });
        }, function() { done(); });
    } else if (entry.isDirectory) {
        // Create folder, then process its contents
        jQuery.post('/file/create_folder', { name: entry.name, parent_id: parentFolderId || '' }, function(d) {
            if (!d || !d.id) { show_error('Failed to create folder: ' + entry.name); done(); return; }
            var newFolderId = d.id;
            var reader = entry.createReader();
            readAllEntries(reader, function(entries) {
                if (entries.length === 0) { done(); return; }
                var sub = entries.length;
                entries.forEach(function(child) {
                    processEntry(child, newFolderId, function() {
                        if (--sub <= 0) done();
                    });
                });
            });
        }, 'json');
    } else {
        done();
    }
}

function readAllEntries(reader, callback) {
    var all = [];
    (function read() {
        reader.readEntries(function(entries) {
            if (entries.length === 0) { callback(all); return; }
            all = all.concat(Array.from(entries));
            read(); // readEntries returns batches of max ~100
        }, function() { callback(all); });
    })();
}

function uploadFiles(fileList) {
    for (var i = 0; i < fileList.length; i++) {
        (function(file) {
            var fd = new FormData();
            fd.append('file', file);
            fd.append('folder_id', currentFolder || '');
            jQuery.ajax({
                url: '/file/upload', type: 'POST', data: fd,
                processData: false, contentType: false,
                headers: { 'X-CSRF-Token': strCsrfToken },
                success: function(d) {
                    if (d && d.id) { show_success(t.file_uploaded || 'Uploaded.'); loadFiles(currentFolder); }
                    else show_error(d && d.error === 'name_conflict' ? 'A file with this name already exists in this folder.' : (d ? d.error : 'Upload failed'));
                },
                error: function() { show_error(t.file_error_upload || 'Upload failed.'); }
            });
        })(fileList[i]);
    }
    document.getElementById('file_upload').value = '';
}

function createFolder() {
    var name = prompt('<?= __('Folder name') ?>:');
    if (!name) return;
    jQuery.post('/file/create_folder', { name: name, parent_id: currentFolder || '' }, function(d) {
        if (d && d.id) { show_success('Folder created.'); loadFiles(currentFolder); }
        else show_error(d ? d.error : 'Failed.');
    }, 'json');
}

function renameFile(id, currentName) {
    var newName = prompt('Rename file:', currentName);
    if (!newName || newName === currentName) return;
    jQuery.post('/file/update_file', { id: id, original_name: newName }, function(d) {
        if (d && d.updated) { show_success('Renamed.'); loadFiles(currentFolder); }
        else if (d && d.error === 'name_conflict') show_error('A file with this name already exists in this folder.');
        else show_error(d ? d.error : 'Failed.');
    }, 'json');
}

function renameFolder(id, currentName) {
    var newName = prompt('Rename folder:', currentName);
    if (!newName || newName === currentName) return;
    jQuery.post('/file/rename_folder', { id: id, name: newName }, function(d) {
        if (d && d.renamed) { show_success('Renamed.'); loadFiles(currentFolder); }
        else if (d && d.error === 'name_conflict') show_error('A folder with this name already exists here.');
        else show_error(d ? d.error : 'Failed.');
    }, 'json');
}

function openFileSettings(id) { jQuery('#settings_' + id).toggle(); }

function saveFileSettings(id) {
    jQuery.post('/file/update_file', {
        id: id, display_mode: jQuery('#mode_' + id).val(),
        visible_guest: jQuery('#vg_' + id).is(':checked') ? 1 : 0,
        visible_editor: jQuery('#ve_' + id).is(':checked') ? 1 : 0,
        visible_contributor: jQuery('#vc_' + id).is(':checked') ? 1 : 0,
        visible_admin: jQuery('#va_' + id).is(':checked') ? 1 : 0
    }, function(d) {
        if (d && d.updated) { show_success('Saved.'); jQuery('#settings_' + id).hide(); loadFiles(currentFolder); }
        else show_error(d ? d.error : 'Failed.');
    }, 'json');
}

// ── Drag & drop ──
jQuery(document).ready(function() {
    loadFiles(null);
    var $body = jQuery('#fileListBody');

    // Delegated event handler for data-action
    jQuery(document).on('click', '[data-action]', function(e) {
        var action = jQuery(this).data('action');
        var arg = jQuery(this).data('arg');
        var arg2 = jQuery(this).data('arg2');
        switch (action) {
            case 'bulkDelete': bulkDelete(); break;
            case 'createFolder': createFolder(); break;
            case 'toggleSelectAll': jQuery('.item-cb').prop('checked', jQuery('#selectAll').is(':checked')); updateBulkButton(); break;
            case 'updateBulkButton': updateBulkButton(); break;
            case 'goUp': goUp(); break;
            case 'loadFiles': e.preventDefault(); loadFiles(arg ? parseInt(arg) : null); break;
            case 'deleteSingleFolder': e.stopPropagation(); deleteSingleFolder(parseInt(arg)); break;
            case 'deleteSingleFile': deleteSingleFile(parseInt(arg)); break;
            case 'openFileSettings': openFileSettings(parseInt(arg)); break;
            case 'saveFileSettings': saveFileSettings(parseInt(arg)); break;
            case 'renameFolder': e.stopPropagation(); var n = prompt('New name:', arg2 || ''); if (n) jQuery.post('/file/rename_folder', {id: arg, name: n}, function(d) { if (d && !d.error) loadFiles(currentFolder); else show_error(d ? d.error : 'Failed.'); }, 'json'); break;
            case 'renameFile': var n = prompt('New name:', arg2 || ''); if (n) jQuery.post('/file/update_file', {id: arg, original_name: n}, function(d) { if (d && !d.error) loadFiles(currentFolder); else show_error(d ? d.error : 'Failed.'); }, 'json'); break;
        }
    });
    // File upload input
    jQuery(document).on('change', '#file_upload', function() { uploadFiles(this.files); });

    // Browse mode: Select button sends file URL to parent window
    if (browseMode) {
        jQuery(document).on('click', '.browse-select-btn', function(e) {
            e.preventDefault();
            var url = jQuery(this).data('url');
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({ type: 'fileSelected', url: url, mode: browseMode }, '*');
            }
        });
    }

    // External file upload
    $body.on('dragover', function(e) {
        if (e.originalEvent.dataTransfer.types.indexOf('Files') >= 0 && !e.originalEvent.dataTransfer.getData('text/plain')) {
            e.preventDefault(); $(this).css('background', 'var(--brand-accent-light)');
        }
    });
    $body.on('dragleave', function() { $(this).css('background', ''); });
    $body.on('drop', function(e) {
        $(this).css('background', '');
        var dt = e.originalEvent.dataTransfer;
        if (dt.getData('text/plain')) return; // Internal drag, not file upload
        e.preventDefault();

        // Check for directory entries (webkitGetAsEntry API)
        var items = dt.items;
        if (items && items.length) {
            var hasDir = false;
            for (var i = 0; i < items.length; i++) {
                var entry = items[i].webkitGetAsEntry ? items[i].webkitGetAsEntry() : null;
                if (entry && entry.isDirectory) { hasDir = true; break; }
            }
            if (hasDir) {
                uploadEntries(items, currentFolder);
                return;
            }
        }
        if (dt.files.length) uploadFiles(dt.files);
    });

    // Internal drag (files + folders)
    $body.on('dragstart', '[draggable=true]', function(e) {
        // Collect all selected items or just the dragged one
        var sel = getSelected();
        var type = $(this).data('drag-type');
        var id = $(this).data('drag-id');
        var payload;
        if (sel.files.length + sel.folders.length > 1 && jQuery(this).find('.item-cb:checked').length) {
            payload = JSON.stringify(sel);
        } else {
            payload = type + ':' + id;
        }
        e.originalEvent.dataTransfer.setData('text/plain', payload);
        e.originalEvent.dataTransfer.effectAllowed = 'move';
        $(this).css('opacity', '0.4');
    });
    $body.on('dragend', '[draggable=true]', function() {
        $(this).css('opacity', '');
        jQuery('.drop-target').css('background', '').css('outline', '');
    });

    // Drop targets
    $body.on('dragover', '.drop-target', function(e) {
        e.preventDefault(); e.stopPropagation();
        $(this).css('background', 'var(--brand-accent-light)').css('outline', '2px dashed var(--brand-primary)');
    });
    $body.on('dragleave', '.drop-target', function() {
        $(this).css('background', '').css('outline', '');
    });
    $body.on('drop', '.drop-target', function(e) {
        e.preventDefault();
        $(this).css('background', '').css('outline', '');
        var raw = e.originalEvent.dataTransfer.getData('text/plain');
        if (!raw) {
            // Not an internal drag — let it bubble up to the external file handler
            return;
        }
        e.stopPropagation();

        var targetFolder = $(this).data('drop-folder');
        if (targetFolder === '__PARENT__') {
            var $bc = jQuery('#fileBreadcrumb a');
            if ($bc.length >= 2) {
                var parentArg = $bc.eq($bc.length - 2).data('arg');
                targetFolder = parentArg !== undefined ? parentArg : '';
            } else { targetFolder = ''; }
        }

        // Multi-select payload?
        try {
            var sel = JSON.parse(raw);
            if (sel.files || sel.folders) {
                if (sel.files && sel.files.length) {
                    jQuery.post('/file/move_file', { ids: sel.files, folder_id: targetFolder }, function(d) {
                        if (d.blocked && d.blocked.length) show_error(d.message);
                        else show_success('Files moved.');
                        loadFiles(currentFolder);
                    }, 'json');
                }
                if (sel.folders && sel.folders.length) {
                    jQuery.post('/file/move_folder', { ids: sel.folders, parent_id: targetFolder }, function() {
                        show_success('Folders moved.'); loadFiles(currentFolder);
                    }, 'json');
                }
                return;
            }
        } catch(ex) {}

        // Single item
        var parts = raw.split(':');
        if (parts.length !== 2) return;
        var dragType = parts[0], dragId = parseInt(parts[1]);
        if (dragType === 'file') {
            jQuery.post('/file/move_file', { id: dragId, folder_id: targetFolder }, function(d) {
                if (d.blocked && d.blocked.length) show_error(d.message);
                else if (d.moved) show_success('Moved.');
                else show_error(d ? d.error : 'Failed.');
                loadFiles(currentFolder);
            }, 'json');
        } else if (dragType === 'folder') {
            if (parseInt(targetFolder) === dragId) return;
            jQuery.post('/file/move_folder', { id: dragId, parent_id: targetFolder }, function(d) {
                if (d.blocked && d.blocked.length) show_error(d.message);
                else if (d.moved) show_success('Moved.');
                else show_error(d ? d.error : 'Failed.');
                loadFiles(currentFolder);
            }, 'json');
        }
    });
});
</script>
