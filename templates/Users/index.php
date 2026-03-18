<?php
/** @var \App\Model\Entity\User[] $users */
/** @var array $auth */
$nonce = $this->request->getAttribute('cspNonce') ?? '';
?>
<div class="container-fluid" style="padding:1.25rem 2rem">
    <h2><?= __('Manage users') ?></h2>
    <div class="mb-3">
        <a href="/user/create" class="btn btn-primary btn-sm"><?= __('Create user') ?></a>
    </div>
    <div class="row fw-bold border-bottom pb-1 mb-1">
        <div class="col-1"></div>
        <div class="col-2"><?= __('Name') ?></div>
        <div class="col-2"><?= __('Username') ?></div>
        <div class="col-2"><?= __('Email') ?></div>
        <div class="col-1"><?= __('Role') ?></div>
        <div class="col-1"><?= __('Status') ?></div>
        <div class="col-3 text-center"><?= __('Actions') ?></div>
    </div>
    <?php foreach ($users as $user): ?>
    <div class="row py-1 border-bottom align-items-center" id="user_row_<?= $user->id ?>">
        <div class="col-1"><img src="/img/<?= h($user->gender ?? 'male') ?>.png" height="20" id="user_img_<?= $user->id ?>"></div>
        <div class="col-2">
            <span class="user_display_<?= $user->id ?>"><?= h($user->fullname) ?></span>
            <input type="text" class="form-control form-control-sm user_edit_<?= $user->id ?>" style="display:none" value="<?= h($user->fullname) ?>" data-field="fullname" data-id="<?= $user->id ?>">
        </div>
        <div class="col-2">
            <span class="user_display_<?= $user->id ?>"><?= h($user->username) ?></span>
            <input type="text" class="form-control form-control-sm user_edit_<?= $user->id ?>" style="display:none" value="<?= h($user->username) ?>" data-field="username" data-id="<?= $user->id ?>">
        </div>
        <div class="col-2">
            <span class="user_display_<?= $user->id ?>"><?= h($user->email) ?></span>
            <input type="email" class="form-control form-control-sm user_edit_<?= $user->id ?>" style="display:none" value="<?= h($user->email) ?>" data-field="email" data-id="<?= $user->id ?>">
        </div>
        <div class="col-1">
            <span class="user_display_<?= $user->id ?>"><?= h($user->role) ?></span>
            <select class="form-select form-select-sm user_edit_<?= $user->id ?>" style="display:none" data-field="role" data-id="<?= $user->id ?>"<?= $user->id === ($auth['id'] ?? 0) ? ' disabled' : '' ?>>
                <option value="editor" <?= $user->role === 'editor' ? 'selected' : '' ?>>Editor</option>
                <option value="contributor" <?= $user->role === 'contributor' ? 'selected' : '' ?>>Contributor</option>
                <option value="admin" <?= $user->role === 'admin' ? 'selected' : '' ?>>Admin</option>
            </select>
        </div>
        <div class="col-1">
            <span class="user_display_<?= $user->id ?>"><?= h($user->status) ?></span>
            <select class="form-select form-select-sm user_edit_<?= $user->id ?>" style="display:none" data-field="status" data-id="<?= $user->id ?>"<?= $user->id === ($auth['id'] ?? 0) ? ' disabled' : '' ?>>
                <option value="active" <?= $user->status === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $user->status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>
        <div class="col-3 text-center">
            <button class="btn btn-sm btn-outline-primary user_display_<?= $user->id ?>" data-action="user_edit" data-arg="<?= $user->id ?>"><span class="fas fa-edit"></span></button>
            <button class="btn btn-sm btn-outline-success user_edit_<?= $user->id ?>" style="display:none" data-action="user_save" data-arg="<?= $user->id ?>"><span class="fas fa-save"></span></button>
            <button class="btn btn-sm btn-outline-secondary user_edit_<?= $user->id ?>" style="display:none" data-action="user_cancel" data-arg="<?= $user->id ?>"><span class="fas fa-times"></span></button>
            <?php if ($user->id !== ($auth['id'] ?? 0)): ?>
                <button class="btn btn-sm btn-outline-danger user_display_<?= $user->id ?>" data-action="user_delete" data-arg="<?= $user->id ?>"><span class="fas fa-trash-alt"></span></button>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<script nonce="<?= $nonce ?>">
function user_edit(id) {
    jQuery('.user_display_' + id).hide();
    jQuery('.user_edit_' + id).show();
}

function user_cancel(id) {
    jQuery('.user_edit_' + id).hide();
    jQuery('.user_display_' + id).show();
}

function user_save(id) {
    var fields = jQuery('.user_edit_' + id).filter('input,select');
    var queue = Promise.resolve();
    fields.each(function() {
        var $el = jQuery(this);
        var field = $el.data('field');
        var value = $el.val();
        queue = queue.then(function() {
            return jQuery.post('/user/save', { id: id, field: field, value: value });
        });
    });
    queue.then(function(d) {
        if (d && !d.error) {
            show_success(t.user_updated);
            setTimeout(function() { location.reload(); }, 500);
        } else {
            show_error(d ? d.error : t.user_error_save);
        }
    }).catch(function() { show_error(t.user_error_save); });
}

function user_delete(id) {
    if (!confirm(t.user_confirm_delete)) return;
    jQuery.post('/user/delete', { id: id }, function(d) {
        if (d && !d.error) {
            jQuery('#user_row_' + id).fadeOut();
            show_success(t.user_deleted);
        } else {
            show_error(d ? d.error : t.user_error_delete);
        }
    }, 'json');
}
</script>
