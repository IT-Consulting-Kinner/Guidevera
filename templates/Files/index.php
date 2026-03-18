<?php
$isAuth = !empty($auth['id']);
$isContributor = in_array($auth['role'] ?? '', ['contributor', 'admin'], true);
$nonce = $this->request->getAttribute('cspNonce') ?? '';
?>
<div id="fileManagerRoot" class="container-fluid" style="padding:1.25rem 2rem">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
        <h2 style="margin:0"><?= __('Manage files') ?></h2>
        <div style="display:flex;gap:0.5rem;align-items:center">
            <?php if ($isContributor): ?>
            <button class="btn btn-outline-danger btn-sm" id="btnBulkDelete" style="display:none" onclick="bulkDelete()"><span class="fas fa-trash-alt"></span> <?= __('Delete selected') ?></button>
            <button class="btn btn-outline-secondary btn-sm" onclick="createFolder()"><span class="fas fa-folder-plus"></span> <?= __('New folder') ?></button>
            <?php endif; ?>
            <?php if ($isAuth): ?>
            <label class="btn btn-primary btn-sm" style="margin:0;cursor:pointer"><span class="fas fa-upload"></span> <?= __('Upload') ?><input type="file" id="file_upload" style="display:none" multiple onchange="uploadFiles(this.files)"></label>
            <?php endif; ?>
        </div>
    </div>

    <div id="fileBreadcrumb" style="margin-bottom:0.75rem;font-size:0.85rem"></div>

    <div class="row fw-bold border-bottom pb-1 mb-1" style="font-size:0.85rem">
        <?php if ($isContributor): ?><div class="col-auto" style="width:2rem"><input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)"></div><?php endif; ?>
        <div class="<?= $isContributor ? 'col' : 'col-5' ?>"><?= __('Name') ?></div>
        <div class="col-1 text-end"><?= __('Size') ?></div>
        <div class="col-2 text-center"><?= __('Date') ?></div>
        <div class="col-1 text-end"><?= __('Downloads') ?></div>
        <div class="col-1"><?= __('Usage') ?></div>
        <?php if ($isContributor): ?><div class="col-1 text-center"><?= __('Actions') ?></div><?php endif; ?>
    </div>
    <div id="fileListBody" style="min-height:100px"></div>
</div>

<script nonce="<?= $nonce ?>">
var currentFolder = null;
var isContributor = <?= $isContributor ? 'true' : 'false' ?>;
var createFolderBusy = false;
var deleteFolderBusy = false;
var deleteFileBusy = false;
var bulkDeleteBusy = false;

function loadFiles(folderId) {
    currentFolder = folderId === undefined ? null : folderId;
    jQuery.post('/file/list', { folder_id: currentFolder || '' }, function(d) {
        if (!d || d.error) {
            show_error('Failed to load files.');
            return;
        }
        renderBreadcrumb(d.breadcrumb || []);
        renderFileList(d.folders || [], d.files || []);
        updateBulkButton();
    }, 'json');
}

function renderBreadcrumb(crumbs) {
    var html = '<a href="javascript:" onclick="loadFiles(null)" style="color:var(--text-link);text-decoration:none"><span class="fas fa-home"></span> Root</a>';
    crumbs.forEach(function(c) {
        html += ' <span style="margin:0 0.3rem;color:var(--text-muted)">/</span> ';
        html += '<a href="javascript:" onclick="loadFiles(' + c.id + ')" style="color:var(--text-link);text-decoration:none">' + escHtml(c.name) + '</a>';
    });
    jQuery('#fileBreadcrumb').html(html);
}

function renderFileList(folders, files) {
    var html = '';
    var nameCol = isContributor ? 'col' : 'col-5';

    if (currentFolder !== null) {
        html += '<div class="row py-1 border-bottom align-items-center drop-target" data-drop-folder="__PARENT__" style="cursor:pointer" onclick="goUp()">';
        if (isContributor) html += '<div class="col-auto" style="width:2rem"></div>';
        html += '<div class="' + nameCol + '"><span class="fas fa-level-up-alt" style="color:var(--text-muted);margin-right:0.5rem"></span> ..</div><div class="col-1"></div><div class="col-2"></div><div class="col-1"></div><div class="col-1"></div>';
        if (isContributor) html += '<div class="col-1"></div>';
        html += '</div>';
    }

    folders.forEach(function(f) {
        html += '<div class="row py-1 border-bottom align-items-center drop-target" id="folder_' + f.id + '" draggable="true" data-drag-type="folder" data-drag-id="' + f.id + '" data-drop-folder="' + f.id + '">';
        if (isContributor) html += '<div class="col-auto" style="width:2rem"><input type="checkbox" class="item-cb" data-type="folder" data-id="' + f.id + '" onclick="updateBulkButton()"></div>';
        html += '<div class="' + nameCol + '" style="cursor:pointer" onclick="loadFiles(' + f.id + ')"><span class="fas fa-folder" style="color:var(--brand-primary);margin-right:0.5rem"></span> <strong>' + escHtml(f.name) + '</strong></div>';
        html += '<div class="col-1"></div><div class="col-2 text-center" style="font-size:0.8rem;color:var(--text-muted)">' + f.created + '</div>';
        html += '<div class="col-1"></div><div class="col-1"></div>';
        if (isContributor) html += '<div class="col-1 text-center"><button class="btn btn-sm btn-outline-secondary" onclick="event.stopPropagation();renameFolder(' + f.id + ',\'' + escHtml(f.name).replace(/'/g, "\\'") + '\')" title="Rename"><span class="fas fa-pen"></span></button> <button class="btn btn-sm btn-outline-danger" onclick="event.stopPropagation();deleteSingleFolder(' + f.id + ')" title="Delete"><span class="fas fa-trash-alt"></span></button></div>';
        html += '</div>';
    });

    files.forEach(function(f) {
        var isImage = /\.(jpg|jpeg|png|gif|webp|svg)$/i.test(f.name);
        var icon = isImage ? 'fa-image' : 'fa-file';
        var hasUsage = f.usedIn && f.usedIn.length > 0;
        var usageHtml = hasUsage
            ? '<span style="color:var(--text-secondary);font-size:0.75rem" title="' + f.usedIn.map(function(u) { return u.title; }).join(', ') + '">' + f.usedIn.length + '</span>'
            : '<span style="color:var(--color-error-text);font-size:0.75rem">0</span>';
        var modeIcon = f.displayMode === 'inline' ? 'fa-eye' : 'fa-download';

        html += '<div class="row py-1 border-bottom align-items-center" id="file_' + f.id + '" draggable="true" data-drag-type="file" data-drag-id="' + f.id + '">';
        if (isContributor) html += '<div class="col-auto" style="width:2rem"><input type="checkbox" class="item-cb" data-type="file" data-id="' + f.id + '" data-usage="' + (hasUsage ? 1 : 0) + '" onclick="updateBulkButton()"></div>';
        var linkTarget = f.displayMode === 'inline' ? ' target="_blank" rel="noopener"' : '';
        html += '<div class="' + nameCol + '"><span class="fas ' + icon + '" style="color:var(--text-muted);margin-right:0.5rem"></span><a href="' + f.url + '"' + linkTarget + ' style="color:var(--text-link);text-decoration:none">' + escHtml(f.name) + '</a> <span class="fas ' + modeIcon + '" style="font-size:0.6rem;color:var(--text-muted);margin-left:0.3rem" title="' + f.displayMode + '"></span></div>';
        html += '<div class="col-1 text-end" style="font-size:0.8rem">' + f.size + '</div>';
        html += '<div class="col-2 text-center" style="font-size:0.8rem;color:var(--text-muted)">' + f.created + '</div>';
        html += '<div class="col-1 text-end" style="font-size:0.8rem">' + f.downloads + '</div>';
        html += '<div class="col-1">' + usageHtml + '</div>';
        if (isContributor) {
            html += '<div class="col-1 text-center">';
            html += '<button class="btn btn-sm btn-outline-secondary" onclick="renameFile(' + f.id + ',\'' + escHtml(f.name).replace(/'/g, "\\'") + '\')" title="Rename"><span class="fas fa-pen"></span></button> ';
            html += '<button class="btn btn-sm btn-outline-info" onclick="openFileSettings(' + f.id + ')" title="Settings"><span class="fas fa-cog"></span></button>';
            if (!hasUsage) html += ' <button class="btn btn-sm btn-outline-danger" onclick="deleteSingleFile(' + f.id + ')" title="Delete"><span class="fas fa-trash-alt"></span></button>';
            html += '</div>';
        }
        html += '</div>';

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
        html += '<button class="btn btn-sm btn-primary" onclick="saveFileSettings(' + f.id + ')">Save</button>';
        html += '</div></div></div>';
    });

    if (!folders.length && !files.length) {
        var msg = currentFolder === null
            ? '<?= __('No files yet. Click Upload or drag files and folders here.') ?>'
            : '<?= __('Empty folder. Drag files here or click Upload.') ?>';
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

function escHtml(s) {
    return jQuery('<span>').text(s == null ? '' : String(s)).html();
}

function toggleSelectAll(el) {
    var checked;

    if (el && typeof el.checked !== 'undefined') {
        checked = el.checked;
    } else {
        checked = jQuery('#selectAll').is(':checked');
    }

    jQuery('.item-cb').prop('checked', checked);
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

function bulkDelete() {
    if (bulkDeleteBusy) return;

    var sel = getSelected();
    var total = sel.files.length + sel.folders.length;
    if (!total || !confirm('Delete ' + total + ' selected item(s)? Files in use will be skipped.')) return;

    bulkDeleteBusy = true;

    var done = 0;
    var errors = [];
    var pendingCalls = 0;

    function finishOne() {
        pendingCalls--;
        if (pendingCalls <= 0) {
            bulkDeleteBusy = false;
            if (errors.length) show_error(errors.join(', '));
            else show_success(done + ' item(s) deleted.');
            loadFiles(currentFolder);
        }
    }

    if (sel.files.length) {
        pendingCalls++;
        jQuery.post('/file/delete', { ids: sel.files }, function(d) {
            done += d.deleted || 0;
            if (d.blocked && d.blocked.length) errors.push(d.blocked.length + ' file(s) in use');
        }, 'json').always(finishOne);
    }

    if (sel.folders.length) {
        pendingCalls++;
        jQuery.post('/file/delete_folder', { ids: sel.folders }, function(d) {
            done += d.deleted || 0;
            if (d.blocked && d.blocked.length) errors.push(d.blocked.length + ' folder(s) contain used files');
        }, 'json').always(finishOne);
    }

    if (!pendingCalls) {
        bulkDeleteBusy = false;
    }
}

function deleteSingleFile(id) {
    if (deleteFileBusy) return;

    if (!confirm(t.file_confirm_delete || 'Delete?')) {
        return;
    }

    deleteFileBusy = true;

    jQuery.post('/file/delete', { id: id }, function(d) {
        if (d.blocked && d.blocked.length) {
            show_error('File in use — cannot delete.');
        } else {
            show_success(t.file_deleted || 'Deleted.');
            loadFiles(currentFolder);
        }
    }, 'json').always(function() {
        deleteFileBusy = false;
    });
}

function deleteSingleFolder(id) {
    if (deleteFolderBusy) return;

    if (!confirm('Delete folder? Folders with used files will be skipped.')) {
        return;
    }

    deleteFolderBusy = true;

    jQuery.post('/file/delete_folder', { id: id }, function(d) {
        if (d.blocked && d.blocked.length) {
            show_error('Folder contains used files — cannot delete.');
        } else {
            show_success('Deleted.');
            loadFiles(currentFolder);
        }
    }, 'json').always(function() {
        deleteFolderBusy = false;
    });
}

function uploadEntries(items, parentFolderId) {
    var pending = 0;

    function done() {
        if (--pending <= 0) loadFiles(currentFolder);
    }

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
                url: '/file/upload',
                type: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                headers: { 'X-CSRF-Token': strCsrfToken },
                success: function() { done(); },
                error: function() {
                    show_error('Upload failed: ' + file.name);
                    done();
                }
            });
        }, function() { done(); });
    } else if (entry.isDirectory) {
        jQuery.post('/file/create_folder', { name: entry.name, parent_id: parentFolderId || '' }, function(d) {
            if (!d || !d.id) {
                show_error('Failed to create folder: ' + entry.name);
                done();
                return;
            }

            var newFolderId = d.id;
            var reader = entry.createReader();
            readAllEntries(reader, function(entries) {
                if (entries.length === 0) {
                    done();
                    return;
                }
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
            if (entries.length === 0) {
                callback(all);
                return;
            }
            all = all.concat(Array.from(entries));
            read();
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
                url: '/file/upload',
                type: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                headers: { 'X-CSRF-Token': strCsrfToken },
                success: function(d) {
                    if (d && d.id) {
                        show_success(t.file_uploaded || 'Uploaded.');
                        loadFiles(currentFolder);
                    } else {
                        show_error(d && d.error === 'name_conflict' ? 'A file with this name already exists in this folder.' : (d ? d.error : 'Upload failed'));
                    }
                },
                error: function() {
                    show_error(t.file_error_upload || 'Upload failed.');
                }
            });
        })(fileList[i]);
    }

    document.getElementById('file_upload').value = '';
}

function createFolder() {
    if (createFolderBusy) return;
    createFolderBusy = true;

    var name = prompt('<?= __('Folder name') ?>:');
    name = name == null ? '' : jQuery.trim(name);

    if (!name) {
        createFolderBusy = false;
        return;
    }

    jQuery.ajax({
        url: '/file/create_folder',
        type: 'POST',
        dataType: 'json',
        data: {
            name: name,
            parent_id: currentFolder || ''
        }
    }).done(function(d) {
        if (d && d.id) {
            show_success('Folder created.');
            loadFiles(currentFolder);
        } else {
            show_error(d ? d.error : 'Failed.');
        }
    }).fail(function() {
        show_error('Failed.');
    }).always(function() {
        createFolderBusy = false;
    });
}

function renameFile(id, currentName) {
    var newName = prompt('Rename file:', currentName);
    if (!newName || newName === currentName) return;
    jQuery.post('/file/update_file', { id: id, original_name: newName }, function(d) {
        if (d && d.updated) {
            show_success('Renamed.');
            loadFiles(currentFolder);
        } else if (d && d.error === 'name_conflict') {
            show_error('A file with this name already exists in this folder.');
        } else {
            show_error(d ? d.error : 'Failed.');
        }
    }, 'json');
}

function renameFolder(id, currentName) {
    var newName = prompt('Rename folder:', currentName);
    if (!newName || newName === currentName) return;
    jQuery.post('/file/rename_folder', { id: id, name: newName }, function(d) {
        if (d && d.renamed) {
            show_success('Renamed.');
            loadFiles(currentFolder);
        } else if (d && d.error === 'name_conflict') {
            show_error('A folder with this name already exists here.');
        } else {
            show_error(d ? d.error : 'Failed.');
        }
    }, 'json');
}

function openFileSettings(id) {
    jQuery('#settings_' + id).toggle();
}

function saveFileSettings(id) {
    jQuery.post('/file/update_file', {
        id: id,
        display_mode: jQuery('#mode_' + id).val(),
        visible_guest: jQuery('#vg_' + id).is(':checked') ? 1 : 0,
        visible_editor: jQuery('#ve_' + id).is(':checked') ? 1 : 0,
        visible_contributor: jQuery('#vc_' + id).is(':checked') ? 1 : 0,
        visible_admin: jQuery('#va_' + id).is(':checked') ? 1 : 0
    }, function(d) {
        if (d && d.updated) {
            show_success('Saved.');
            jQuery('#settings_' + id).hide();
            loadFiles(currentFolder);
        } else {
            show_error(d ? d.error : 'Failed.');
        }
    }, 'json');
}

jQuery(document).ready(function() {
    loadFiles(null);

    var $body = jQuery('#fileListBody');

    $body.off('.guideveraFiles');

    $body.on('dragover.guideveraFiles', function(e) {
        if (e.originalEvent.dataTransfer.types.indexOf('Files') >= 0 && !e.originalEvent.dataTransfer.getData('text/plain')) {
            e.preventDefault();
            jQuery(this).css('background', 'var(--brand-accent-light)');
        }
    });

    $body.on('dragleave.guideveraFiles', function() {
        jQuery(this).css('background', '');
    });

    $body.on('drop.guideveraFiles', function(e) {
        jQuery(this).css('background', '');
        var dt = e.originalEvent.dataTransfer;
        if (dt.getData('text/plain')) return;
        e.preventDefault();

        var items = dt.items;
        if (items && items.length) {
            var hasDir = false;
            for (var i = 0; i < items.length; i++) {
                var entry = items[i].webkitGetAsEntry ? items[i].webkitGetAsEntry() : null;
                if (entry && entry.isDirectory) {
                    hasDir = true;
                    break;
                }
            }
            if (hasDir) {
                uploadEntries(items, currentFolder);
                return;
            }
        }

        if (dt.files.length) uploadFiles(dt.files);
    });

    $body.on('dragstart.guideveraFiles', '[draggable=true]', function(e) {
        var sel = getSelected();
        var type = jQuery(this).data('drag-type');
        var id = jQuery(this).data('drag-id');
        var payload;

        if (sel.files.length + sel.folders.length > 1 && jQuery(this).find('.item-cb:checked').length) {
            payload = JSON.stringify(sel);
        } else {
            payload = type + ':' + id;
        }

        e.originalEvent.dataTransfer.setData('text/plain', payload);
        e.originalEvent.dataTransfer.effectAllowed = 'move';
        jQuery(this).css('opacity', '0.4');
    });

    $body.on('dragend.guideveraFiles', '[draggable=true]', function() {
        jQuery(this).css('opacity', '');
        jQuery('.drop-target').css('background', '').css('outline', '');
    });

    $body.on('dragover.guideveraFiles', '.drop-target', function(e) {
        e.preventDefault();
        e.stopPropagation();
        jQuery(this).css('background', 'var(--brand-accent-light)').css('outline', '2px dashed var(--brand-primary)');
    });

    $body.on('dragleave.guideveraFiles', '.drop-target', function() {
        jQuery(this).css('background', '').css('outline', '');
    });

    $body.on('drop.guideveraFiles', '.drop-target', function(e) {
        e.preventDefault();
        jQuery(this).css('background', '').css('outline', '');

        var raw = e.originalEvent.dataTransfer.getData('text/plain');
        if (!raw) {
            return;
        }

        e.stopPropagation();

        var targetFolder = jQuery(this).data('drop-folder');
        if (targetFolder === '__PARENT__') {
            var $bc = jQuery('#fileBreadcrumb a');
            if ($bc.length >= 2) {
                var oc = $bc.eq($bc.length - 2).attr('onclick') || '';
                var m = oc.match(/loadFiles\((\d+|null)\)/);
                targetFolder = m ? (m[1] === 'null' ? '' : m[1]) : '';
            } else {
                targetFolder = '';
            }
        }

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
                        show_success('Folders moved.');
                        loadFiles(currentFolder);
                    }, 'json');
                }
                return;
            }
        } catch (ex) {}

        var parts = raw.split(':');
        if (parts.length !== 2) return;

        var dragType = parts[0];
        var dragId = parseInt(parts[1], 10);

        if (dragType === 'file') {
            jQuery.post('/file/move_file', { id: dragId, folder_id: targetFolder }, function(d) {
                if (d.blocked && d.blocked.length) show_error(d.message);
                else if (d.moved) show_success('Moved.');
                else show_error(d ? d.error : 'Failed.');
                loadFiles(currentFolder);
            }, 'json');
        } else if (dragType === 'folder') {
            if (parseInt(targetFolder, 10) === dragId) return;
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