<?php ?>
<div class="form-page">
    <h2><?= __('Change password') ?></h2>
    <?= $this->Flash->render() ?>
    <?php if (!empty($auth['change_password'])): ?>
        <div class="alert alert-info"><?= __('You must change your password before continuing.') ?></div>
    <?php endif; ?>
    <form method="post">
        <?php if ($csrfToken = $this->request->getAttribute('csrfToken')): ?>
            <input type="hidden" name="_csrfToken" value="<?= h($csrfToken) ?>">
        <?php endif; ?>
        <div class="mb-3">
            <label class="form-label"><?= __('Current password') ?></label>
            <input type="password" name="oldpassword" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label"><?= __('New password') ?></label>
            <input type="password" name="newpassword" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label"><?= __('Verify new password') ?></label>
            <input type="password" name="newpasswordverify" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary"><?= __('Change password') ?></button>
    </form>
</div>
