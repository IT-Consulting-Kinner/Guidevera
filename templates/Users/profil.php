<?php /** @var \App\Model\Entity\User $user */ ?>
<div class="form-page">
    <h2><?= __('Edit profile') ?></h2>
    <?= $this->Flash->render() ?>
    <form method="post">
        <?php if ($csrfToken = $this->request->getAttribute('csrfToken')): ?>
            <input type="hidden" name="_csrfToken" value="<?= h($csrfToken) ?>">
        <?php endif; ?>
        <input type="hidden" name="subaction" value="change_user">
        <div class="mb-3">
            <label class="form-label"><?= __('Name') ?></label>
            <input type="text" name="fullname" value="<?= h($user->fullname) ?>" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label"><?= __('Email') ?></label>
            <input type="email" name="email" value="<?= h($user->email) ?>" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label"><?= __('Salutation') ?></label>
            <select name="gender" class="form-select">
                <option value="male" <?= ($user->gender ?? '') === 'male' ? 'selected' : '' ?>><?= __('Mr') ?></option>
                <option value="female" <?= ($user->gender ?? '') === 'female' ? 'selected' : '' ?>><?= __('Ms') ?></option>
            </select>
        </div>
        <?php if (\Cake\Core\Configure::read('Manual.enableMentions') ?? false): ?>
        <div class="mb-3 form-check">
            <input type="checkbox" name="notify_mentions" id="notify_mentions" class="form-check-input" value="1" <?= ($user->notify_mentions ?? 1) ? 'checked' : '' ?>>
            <label class="form-check-label" for="notify_mentions"><?= __('Notify me when mentioned in comments (@username)') ?></label>
        </div>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary"><?= __('Save') ?></button>
    </form>
    <hr>
    <h4><?= __('Change password') ?></h4>
    <form method="post">
        <?php if ($csrfToken = $this->request->getAttribute('csrfToken')): ?>
            <input type="hidden" name="_csrfToken" value="<?= h($csrfToken) ?>">
        <?php endif; ?>
        <input type="hidden" name="subaction" value="change_password">
        <div class="mb-3">
            <label class="form-label"><?= __('New password') ?></label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label"><?= __('Verify new password') ?></label>
            <input type="password" name="passwordverify" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-warning"><?= __('Change password') ?></button>
    </form>
</div>
