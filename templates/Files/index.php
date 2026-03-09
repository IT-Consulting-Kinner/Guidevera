<?php
/**
 * File Management View
 * Port of view.file.tpl with drag-and-drop upload
 *
 * @var \App\View\AppView $this
 * @var array $files
 * @var array $auth
 */
$isAuth = !empty($auth['id']);
?>
<style>
    #filelist_body {
        min-height: 100px;
        transition: background 0.2s;
    }
    #filelist_body.dragover {
        background: #e8f4fd;
        border: 2px dashed #007bff;
    }
</style>
<div class="container mt-4">
    <h2><?= __('Manage files') ?></h2>
    <?php if ($isAuth): ?>
    <div class="mb-3">
        <input type="file" id="file_upload" class="form-control d-inline-block" style="max-width:400px">
        <button class="btn btn-primary btn-sm" onclick="upload_file()">Upload</button>
    </div>
    <?php endif; ?>
    <div class="row fw-bold border-bottom pb-1 mb-1">
        <div class="col-5"><?= __('Filename') ?></div>
        <div class="col-2 text-end"><?= __('Size') ?></div>
        <div class="col-2 text-center"><?= __('Date') ?></div>
        <div class="col-1 text-end"><?= __('Downloads') ?></div>
        <?php if ($isAuth): ?><div class="col-2 text-center"><?= __('Actions') ?></div><?php endif; ?>
    </div>
    <div id="filelist_body">
        <?php if (empty($files)): ?>
            <div id="drop_hint" class="text-center text-muted p-4"><?= __('No files available. Drag files here to upload.') ?></div>
        <?php else: ?>
            <?php foreach ($files as $f): ?>
            <div class="row py-1 border-bottom" id="file_<?= md5($f['name']) ?>">
                <div class="col-5"><a href="/downloads/<?= h($f['name']) ?>"><?= h($f['name']) ?></a></div>
                <div class="col-2 text-end"><?= $f['size'] ?></div>
                <div class="col-2 text-center"><?= $f['date'] ?></div>
                <div class="col-1 text-end"><?= $f['views'] ?></div>
                <?php if ($isAuth): ?>
                <div class="col-2 text-center">
                    <button class="btn btn-sm btn-outline-danger" onclick="file_delete('<?= h($f['name']) ?>')"><span class="fas fa-trash-alt"></span></button>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<script>
function upload_file() {
    var input = document.getElementById('file_upload');
    if (!input.files.length) { show_error(t.file_error_no_file); return; }
    do_upload(input.files[0]);
}

function do_upload(objFileItem) {
    var fd = new FormData();
    fd.append('file', objFileItem);
    jQuery.ajax({
        url: '/file/upload', type: 'POST', data: fd,
        processData: false, contentType: false,
        headers: { 'X-CSRF-Token': strCsrfToken },
        success: function(d) {
            if (d && d.success) {
                // Add file row dynamically
                jQuery('#drop_hint').remove();
                var strName = d.filename || objFileItem.name;
                var strEscName = jQuery('<span>').text(strName).html();
                var strRow = '<div class="row py-1 border-bottom" id="file_' + d.hash + '">'
                    + '<div class="col-5"><a href="/downloads/' + encodeURIComponent(strName) + '">' + strEscName + '</a></div>'
                    + '<div class="col-2 text-end">' + (d.size || '') + '</div>'
                    + '<div class="col-2 text-center">' + (d.date || '') + '</div>'
                    + '<div class="col-1 text-end">0</div>'
                    + '<div class="col-2 text-center"><button class="btn btn-sm btn-outline-danger" onclick="file_delete(\'' + strEscName.replace(/'/g, "\\'") + '\')"><span class="fas fa-trash-alt"></span></button></div>'
                    + '</div>';
                jQuery('#filelist_body').append(strRow);
                show_success(t.file_uploaded);
            } else {
                show_error(d ? d.error : 'Upload failed');
            }
        },
        error: function() { show_error(t.file_error_upload); }
    });
}

function file_delete(strFilename) {
    if (!confirm(t.file_confirm_delete)) return;
    jQuery.post('/file/delete', { filename: strFilename }, function(d) {
        if (d && d.success) {
            // Find and remove the row
            jQuery('#filelist_body .row').each(function() {
                var $a = jQuery(this).find('a');
                if ($a.text() === strFilename) {
                    jQuery(this).fadeOut(300, function() {
                        jQuery(this).remove();
                        if (jQuery('#filelist_body .row').length == 0) {
                            jQuery('#filelist_body').html('<div id="drop_hint" class="text-center text-muted p-4"><?= __('No files available. Drag files here to upload.') ?></div>');
                        }
                    });
                }
            });
            show_success(t.file_deleted);
        } else {
            show_error(d ? d.error : 'Delete failed');
        }
    }, 'json');
}

// Drag-and-drop upload
jQuery(document).ready(function() {
    var $dropzone = jQuery('#filelist_body');

    $dropzone.on('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).addClass('dragover');
    });

    $dropzone.on('dragleave', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('dragover');
    });

    $dropzone.on('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('dragover');
        var arrFiles = e.originalEvent.dataTransfer.files;
        for (var i = 0; i < arrFiles.length; i++) {
            do_upload(arrFiles[i]);
        }
    });
});
</script>
