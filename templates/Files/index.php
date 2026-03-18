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
                <button class="btn btn-outline-danger btn-sm" id="btnBulkDelete" type="button" style="display:none">
                    <span class="fas fa-trash-alt"></span> <?= __('Delete selected') ?>
                </button>
                <button class="btn btn-outline-secondary btn-sm" id="btnCreateFolder" type="button">
                    <span class="fas fa-folder-plus"></span> <?= __('New folder') ?>
                </button>
            <?php endif; ?>
            <?php if ($isAuth): ?>
                <label class="btn btn-primary btn-sm" for="file_upload" style="margin:0;cursor:pointer">
                    <span class="fas fa-upload"></span> <?= __('Upload') ?>
                </label>
                <input type="file" id="file_upload" style="display:none" multiple>
            <?php endif; ?>
        </div>
    </div>

    <div id="fileBreadcrumb" style="margin-bottom:0.75rem;font-size:0.85rem"></div>

    <div class="row fw-bold border-bottom pb-1 mb-1" style="font-size:0.85rem">
        <?php if ($isContributor): ?>
            <div class="col-auto" style="width:2rem">
                <input type="checkbox" id="selectAll">
            </div>
        <?php endif; ?>
        <div class="<?= $isContributor ? 'col' : 'col-5' ?>"><?= __('Name') ?></div>
        <div class="col-1 text-end"><?= __('Size') ?></div>
        <div class="col-2 text-center"><?= __('Date') ?></div>
        <div class="col-1 text-end"><?= __('Downloads') ?></div>
        <div class="col-1"><?= __('Usage') ?></div>
        <?php if ($isContributor): ?>
            <div class="col-1 text-center"><?= __('Actions') ?></div>
        <?php endif; ?>
    </div>

    <div id="fileListBody" style="min-height:100px"></div>
</div>

<script nonce="<?= h($nonce) ?>">
(function() {
    let currentFolder = null;
    const isContributor = <?= $isContributor ? 'true' : 'false' ?>;

    let createFolderBusy = false;
    let deleteFolderBusy = false;
    let deleteFileBusy = false;
    let bulkDeleteBusy = false;

    function escHtml(s) {
        return jQuery('<span>').text(s == null ? '' : String(s)).html();
    }

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
        let html = '';
        html += '<button type="button" class="btn btn-link p-0 text-decoration-none breadcrumb-link" data-folder-id="">';
        html += '<span class="fas fa-home"></span> Root';
        html += '</button>';

        crumbs.forEach(function(c) {
            html += ' <span style="margin:0 0.3rem;color:var(--text-muted)">/</span> ';
            html += '<button type="button" class="btn btn-link p-0 text-decoration-none breadcrumb-link" data-folder-id="' + c.id + '">';
            html += escHtml(c.name);
            html += '</button>';
        });

        jQuery('#fileBreadcrumb').html(html);
    }

    function renderFileList(folders, files) {
        let html = '';
        const nameCol = isContributor ? 'col' : 'col-5';

        if (currentFolder !== null) {
            html += '<div class="row py-1 border-bottom align-items-center drop-target file-row-up" data-drop-folder="__PARENT__" style="cursor:pointer">';
            if (isContributor) html += '<div class="col-auto" style="width:2rem"></div>';
            html += '<div class="' + nameCol + '"><span class="fas fa-level-up-alt" style="color:var(--text-muted);margin-right:0.5rem"></span> ..</div>';
            html += '<div class="col-1"></div><div class="col-2"></div><div class="col-1"></div><div class="col-1"></div>';
            if (isContributor) html += '<div class="col-1"></div>';
            html += '</div>';
        }

        folders.forEach(function(f) {
            html += '<div class="row py-1 border-bottom align-items-center drop-target"';
            html += ' id="folder_' + f.id + '" draggable="true"';
            html += ' data-drag-type="folder" data-drag-id="' + f.id + '" data-drop-folder="' + f.id + '">';

            if (isContributor) {
                html += '<div class="col-auto" style="width:2rem">';
                html += '<input type="checkbox" class="item-cb" data-type="folder" data-id="' + f.id + '">';
                html += '</div>';
            }

            html += '<div class="' + nameCol + '">';
            html += '<button type="button" class="btn btn-link p-0 text-decoration-none open-folder-btn" data-folder-id="' + f.id + '" style="color:inherit">';
            html += '<span class="fas fa-folder" style="color:var(--brand-primary);margin-right:0.5rem"></span> <strong>' + escHtml(f.name) + '</strong>';
            html += '</button>';
            html += '</div>';

            html += '<div class="col-1"></div>';
            html += '<div class="col-2 text-center" style="font-size:0.8rem;color:var(--text-muted)">' + escHtml(f.created) + '</div>';
            html += '<div class="col-1"></div><div class="col-1"></div>';

            if (isContributor) {
                html += '<div class="col-1 text-center">';
                html += '<button type="button" class="btn btn-sm btn-outline-secondary rename-folder-btn" data-folder-id="' + f.id + '" data-folder-name="' + escHtml(f.name) + '" title="Rename"><span class="fas fa-pen"></span></button> ';
                html += '<button type="button" class="btn btn-sm btn-outline-danger delete-folder-btn" data-folder-id="' + f.id + '" title="Delete"><span class="fas fa-trash-alt"></span></button>';
                html += '</div>';
            }

            html += '</div>';
        });

        files.forEach(function(f) {
            const isImage = /\.(jpg|jpeg|png|gif|webp|svg)$/i.test(f.name);
            const icon = isImage ? 'fa-image' : 'fa-file';
            const hasUsage = f.usedIn && f.usedIn.length > 0;
            const usageHtml = hasUsage
                ? '<span style="color:var(--text-secondary);font-size:0.75rem" title="' + escHtml(f.usedIn.map(function(u){ return u.title; }).join(', ')) + '">' + f.usedIn.length + '</span>'
                : '<span style="color:var(--color-error-text);font-size:0.75rem">0</span>';
            const modeIcon = f.displayMode === 'inline' ? 'fa-eye' : 'fa-download';
            const linkTarget = f.displayMode === 'inline' ? ' target="_blank" rel="noopener"' : '';

            html += '<div class="row py-1 border-bottom align-items-center" id="file_' + f.id + '" draggable="true" data-drag-type="file" data-drag-id="' + f.id + '">';

            if (isContributor) {
                html += '<div class="col-auto" style="width:2rem">';
                html += '<input type="checkbox" class="item-cb" data-type="file" data-id="' + f.id + '" data-usage="' + (hasUsage ? 1 : 0) + '">';
                html += '</div>';
            }

            html += '<div class="' + nameCol + '">';
            html += '<span class="fas ' + icon + '" style="color:var(--text-muted);margin-right:0.5rem"></span>';
            html += '<a href="' + escHtml(f.url) + '"' + linkTarget + ' style="color:var(--text-link);text-decoration:none">' + escHtml(f.name) + '</a>';
            html += ' <span class="fas ' + modeIcon + '" style="font-size:0.6rem;color:var(--text-muted);margin-left:0.3rem" title="' + escHtml(f.displayMode) + '"></span>';
            html += '</div>';

            html += '<div class="col-1 text-end" style="font-size:0.8rem">' + escHtml(f.size) + '</div>';
            html += '<div class="col-2 text-center" style="font-size:0.8rem;color:var(--text-muted)">' + escHtml(f.created) + '</div>';
            html += '<div class="col-1 text-end" style="font-size:0.8rem">' + escHtml(f.downloads) + '</div>';
            html += '<div class="col-1">' + usageHtml + '</div>';

            if (isContributor) {
                html += '<div class="col-1 text-center">';
                html += '<button type="button" class="btn btn-sm btn-outline-secondary rename-file-btn" data-file-id="' + f.id + '" data-file-name="' + escHtml(f.name) + '" title="Rename"><span class="fas fa-pen"></span></button> ';
                html += '<button type="button" class="btn btn-sm btn-outline-info file-settings-btn" data-file-id="' + f.id + '" title="Settings"><span class="fas fa-cog"></span></button>';
                if (!hasUsage) {
                    html += ' <button type="button" class="btn btn-sm btn-outline-danger delete-file-btn" data-file-id="' + f.id + '" title="Delete"><span class="fas fa-trash-alt"></span></button>';
                }
                html += '</div>';
            }

            html += '</div>';

            html += '<div id="settings_' + f.id + '" style="display:none" class="row py-2 border-bottom">';
            html += '<div class="col-12" style="padding:0.5rem 1rem;background:var(--bg-body);border-radius:var(--radius-sm)">';
            html += '<div style="display:flex;gap:1.5rem;align-items:center;flex-wrap:wrap;font-size:0.8rem">';
            html += '<label style="font-weight:600">Display:</label>';
            html += '<select id="mode_' + f.id + '" class="form-select form-select-sm" style="width:auto">';
            html += '<option value="download"' + (f.displayMode === 'download' ? ' selected' : '') + '>Download</option>';
            html += '<option value="inline"' + (f.displayMode === 'inline' ? ' selected' : '') + '>Inline</option>';
            html += '</select>';

            html += '<label style="font-weight:600;margin-left:1rem">Visible:</label>';
            html += '<label><input type="checkbox" id="vg_' + f.id + '"' + (f.visibleGuest ? ' checked' : '') + '> Guest</label>';
            html += '<label><input type="checkbox" id="ve_' + f.id + '"' + (f.visibleEditor ? ' checked' : '') + '> Editor</label>';
            html += '<label><input type="checkbox" id="vc_' + f.id + '"' + (f.visibleContributor ? ' checked' : '') + '> Contr.</label>';
            html += '<label><input type="checkbox" id="va_' + f.id + '"' + (f.visibleAdmin ? ' checked' : '') + '> Admin</label>';
            html += '<button type="button" class="btn btn-sm btn-primary save-file-settings-btn" data-file-id="' + f.id + '">Save</button>';
            html += '</div></div></div>';
        });

        if (!folders.length && !files.length) {
            const msg = currentFolder === null
                ? '<?= __('No files yet. Click Upload or drag files and folders here.') ?>'
                : '<?= __('Empty folder. Drag files here or click Upload.') ?>';

            html += '<div class="drop-zone-empty drop-target" data-drop-folder="' + (currentFolder || '') + '" style="padding:3rem 2rem;text-align:center;color:var(--text-muted);border:2px dashed var(--border-color);border-radius:var(--radius);margin:1rem 0;cursor:default">';
            html += '<span class="fas fa-cloud-upload-alt" style="font-size:2rem;display:block;margin-bottom:0.5rem"></span>' + msg;
            html += '</div>';
        }

        jQuery('#fileListBody').html(html);
        jQuery('#selectAll').prop('checked', false);
    }

    function updateBulkButton() {
        const count = jQuery('.item-cb:checked').length;
        jQuery('#btnBulkDelete').toggle(count > 0);
    }

    function toggleSelectAll() {
        const checked = jQuery('#selectAll').is(':checked');
        jQuery('.item-cb').prop('checked', checked);
        updateBulkButton();
    }

    function getSelected() {
        const files = [];
        const folders = [];

        jQuery('.item-cb:checked').each(function() {
            if (jQuery(this).data('type') === 'file') {
                files.push(jQuery(this).data('id'));
            } else {
                folders.push(jQuery(this).data('id'));
            }
        });

        return { files: files, folders: folders };
    }

    function goUp() {
        const $bc = jQuery('#fileBreadcrumb .breadcrumb-link');
        if ($bc.length >= 2) {
            jQuery($bc[$bc.length - 2]).trigger('click');
        } else {
            loadFiles(null);
        }
    }

    function bulkDelete() {
        if (bulkDeleteBusy) return;

        const sel = getSelected();
        const total = sel.files.length + sel.folders.length;

        if (!total || !confirm('Delete ' + total + ' selected item(s)? Files in use will be skipped.')) {
            return;
        }

        bulkDeleteBusy = true;

        let done = 0;
        const errors = [];
        let pendingCalls = 0;

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

    function createFolder() {
        if (createFolderBusy) return;
        createFolderBusy = true;

        let name = prompt('<?= __('Folder name') ?>:');
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

    function renameFolder(id, currentName) {
        const newName = prompt('Rename folder:', currentName);
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

    function renameFile(id, currentName) {
        const newName = prompt('Rename file:', currentName);
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

    function uploadFiles(fileList) {
        for (let i = 0; i < fileList.length; i++) {
            (function(file) {
                const fd = new FormData();
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
                            show_error(d && d.error === 'name_conflict'
                                ? 'A file with this name already exists in this folder.'
                                : (d ? d.error : 'Upload failed'));
                        }
                    },
                    error: function() {
                        show_error(t.file_error_upload || 'Upload failed.');
                    }
                });
            })(fileList[i]);
        }

        const input = document.getElementById('file_upload');
        if (input) input.value = '';
    }

    function uploadEntries(items, parentFolderId) {
        let pending = 0;

        function done() {
            if (--pending <= 0) loadFiles(currentFolder);
        }

        for (let i = 0; i < items.length; i++) {
            const entry = items[i].webkitGetAsEntry ? items[i].webkitGetAsEntry() : null;
            if (!entry) continue;
            pending++;
            processEntry(entry, parentFolderId, done);
        }

        if (pending === 0) loadFiles(currentFolder);
    }

    function processEntry(entry, parentFolderId, done) {
        if (entry.isFile) {
            entry.file(function(file) {
                const fd = new FormData();
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
            jQuery.post('/file/create_folder', {
                name: entry.name,
                parent_id: parentFolderId || ''
            }, function(d) {
                if (!d || !d.id) {
                    show_error('Failed to create folder: ' + entry.name);
                    done();
                    return;
                }

                const newFolderId = d.id;
                const reader = entry.createReader();

                readAllEntries(reader, function(entries) {
                    if (entries.length === 0) {
                        done();
                        return;
                    }

                    let sub = entries.length;
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
        let all = [];

        (function read() {
            reader.readEntries(function(entries) {
                if (entries.length === 0) {
                    callback(all);
                    return;
                }
                all = all.concat(Array.from(entries));
                read();
            }, function() {
                callback(all);
            });
        })();
    }

    jQuery(function() {
        loadFiles(null);

        const $doc = jQuery(document);
        const $body = jQuery('#fileListBody');

        $doc.off('.guideveraFiles');
        $body.off('.guideveraFiles');

        $doc.on('click.guideveraFiles', '#btnCreateFolder', function() {
            createFolder();
        });

        $doc.on('click.guideveraFiles', '#btnBulkDelete', function() {
            bulkDelete();
        });

        $doc.on('change.guideveraFiles', '#file_upload', function() {
            if (this.files && this.files.length) {
                uploadFiles(this.files);
            }
        });

        $doc.on('change.guideveraFiles', '#selectAll', function() {
            toggleSelectAll();
        });

        $doc.on('change.guideveraFiles', '.item-cb', function() {
            updateBulkButton();
        });

        $doc.on('click.guideveraFiles', '.breadcrumb-link', function() {
            const id = jQuery(this).data('folder-id');
            loadFiles(id === '' ? null : parseInt(id, 10));
        });

        $doc.on('click.guideveraFiles', '.file-row-up', function() {
            goUp();
        });

        $doc.on('click.guideveraFiles', '.open-folder-btn', function() {
            const id = parseInt(jQuery(this).data('folder-id'), 10);
            loadFiles(id);
        });

        $doc.on('click.guideveraFiles', '.rename-folder-btn', function() {
            const id = parseInt(jQuery(this).data('folder-id'), 10);
            const name = jQuery(this).data('folder-name');
            renameFolder(id, name);
        });

        $doc.on('click.guideveraFiles', '.delete-folder-btn', function() {
            const id = parseInt(jQuery(this).data('folder-id'), 10);
            deleteSingleFolder(id);
        });

        $doc.on('click.guideveraFiles', '.rename-file-btn', function() {
            const id = parseInt(jQuery(this).data('file-id'), 10);
            const name = jQuery(this).data('file-name');
            renameFile(id, name);
        });

        $doc.on('click.guideveraFiles', '.delete-file-btn', function() {
            const id = parseInt(jQuery(this).data('file-id'), 10);
            deleteSingleFile(id);
        });

        $doc.on('click.guideveraFiles', '.file-settings-btn', function() {
            const id = parseInt(jQuery(this).data('file-id'), 10);
            openFileSettings(id);
        });

        $doc.on('click.guideveraFiles', '.save-file-settings-btn', function() {
            const id = parseInt(jQuery(this).data('file-id'), 10);
            saveFileSettings(id);
        });

        $body.on('dragover.guideveraFiles', function(e) {
            if (e.originalEvent.dataTransfer.types.indexOf('Files') >= 0 &&
                !e.originalEvent.dataTransfer.getData('text/plain')) {
                e.preventDefault();
                jQuery(this).css('background', 'var(--brand-accent-light)');
            }
        });

        $body.on('dragleave.guideveraFiles', function() {
            jQuery(this).css('background', '');
        });

        $body.on('drop.guideveraFiles', function(e) {
            jQuery(this).css('background', '');

            const dt = e.originalEvent.dataTransfer;
            if (dt.getData('text/plain')) return;

            e.preventDefault();

            const items = dt.items;
            if (items && items.length) {
                let hasDir = false;
                for (let i = 0; i < items.length; i++) {
                    const entry = items[i].webkitGetAsEntry ? items[i].webkitGetAsEntry() : null;
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
            const sel = getSelected();
            const type = jQuery(this).data('drag-type');
            const id = jQuery(this).data('drag-id');
            let payload;

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
            e.stopPropagation();

            jQuery(this).css('background', '').css('outline', '');

            const raw = e.originalEvent.dataTransfer.getData('text/plain');
            if (!raw) return;

            let targetFolder = jQuery(this).data('drop-folder');

            if (targetFolder === '__PARENT__') {
                const $bc = jQuery('#fileBreadcrumb .breadcrumb-link');
                if ($bc.length >= 2) {
                    targetFolder = jQuery($bc[$bc.length - 2]).data('folder-id') || '';
                } else {
                    targetFolder = '';
                }
            }

            try {
                const sel = JSON.parse(raw);
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

            const parts = raw.split(':');
            if (parts.length !== 2) return;

            const dragType = parts[0];
            const dragId = parseInt(parts[1], 10);

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
})();
</script>
