<?php /** @var \App\Model\Entity\User $user */ ?>
<div class="form-page">
    <h2><?= __('Create user') ?></h2>
    <?= $this->Flash->render() ?>
    <form method="post">
        <?php if ($csrfToken = $this->request->getAttribute('csrfToken')): ?>
            <input type="hidden" name="_csrfToken" value="<?= h($csrfToken) ?>">
        <?php endif; ?>
        <div class="mb-3"><label class="form-label"><?= __('Username') ?></label><input type="text" name="username"
            class="form-control" required></div>
        <div class="mb-3"><label class="form-label"><?= __('Name') ?></label><input type="text" name="fullname"
            class="form-control" required></div>
        <div class="mb-3"><label class="form-label"><?= __('Email') ?></label><input type="email" name="email"
            class="form-control" required></div>
        <div class="mb-3"><label class="form-label"><?= __('Password') ?></label><input type="password"
            name="password" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Confirm Password</label><input type="password"
            name="passwordverify" class="form-control" required></div>
        <div class="mb-3"><label class="form-label"><?= __('Roles') ?></label><select name="role"
            class="form-select"><option value="user"><?= __('User') ?></option><option value="admin"><?= __('Admin')
                ?></option></select></div>
        <div class="mb-3"><label class="form-label"><?= __('Salutation') ?></label><select name="gender"
            class="form-select"><option value="male"><?= __('Mr') ?></option><option value="female"><?= __('Ms')
                ?></option></select></div>
        <input type="hidden" name="status" value="active">
        <button type="submit" class="btn btn-primary">Create</button>
        <a href="/user" class="btn btn-secondary ms-2">Cancel</a>
    </form>
</div>
