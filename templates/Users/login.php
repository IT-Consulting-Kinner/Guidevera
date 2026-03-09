<?php
/**
 * @var \App\View\AppView $this
 * @var bool $needs_setup
 * @var string|null $page_id
 */
?>
<div class="form-page">
    <h2><?= __('Login') ?></h2>
    <?= $this->Flash->render() ?>
    <?php if (!empty($needs_setup)): ?>
        <div class="alert alert-warning">
            <strong><?= __('Initial setup required') ?></strong><br>
            <?= __('No users found. Please run the following command on the server:') ?><br>
            <code style="display:block;margin:0.5rem 0;padding:0.5rem;background:var(--bg-body);border-radius:var(--radius-sm)">bin/cake install</code>
        </div>
    <?php else: ?>
    <form method="post">
        <?php if ($csrfToken = $this->request->getAttribute('csrfToken')): ?>
            <input type="hidden" name="_csrfToken" value="<?= h($csrfToken) ?>">
        <?php endif; ?>
        <input type="hidden" name="page_id" value="<?= h($page_id ?? ($this->request->getQuery('page_id') ?? '')) ?>">
        <div class="mb-3">
            <label for="username" class="form-label"><?= __('Username') ?></label>
            <input type="text" name="username" id="username" class="form-control" required autofocus>
        </div>
        <div class="mb-3">
            <label for="password" class="form-label"><?= __('Password') ?></label>
            <input type="password" name="password" id="password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary"><?= __('login') ?></button>
    </form>
    <?php endif; ?>
</div>
