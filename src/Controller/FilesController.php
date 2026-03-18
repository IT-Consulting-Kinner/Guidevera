<?php

declare(strict_types=1);

namespace App\Controller;

use Cake\Core\Configure;
use Cake\Log\Log;

/**
 * Files Controller — file management with ID-based links and folder structure.
 *
 * Files are referenced by ID: /downloads/{id}/{original_name}
 * Moving or renaming never breaks links.
 */
class FilesController extends AppController
{
    private string $storageDir;

    public function initialize(): void
    {
        parent::initialize();
        $this->storageDir = ROOT . DS . 'storage' . DS . 'media' . DS;
        if (!is_dir($this->storageDir)) {
            @mkdir($this->storageDir, 0750, true);
        }
    }

    /**
     * File management page (HTML).
     */
    public function index(): void
    {
        if (!$this->hasRole(self::ROLE_EDITOR)) {
            $this->redirect('/user/login');
            return;
        }
        $browseMode = $this->request->getQuery('browse', '');
        $this->set('browseMode', $browseMode);
        if ($browseMode) {
            $this->set('iframeBrowse', true);
        }
    }

    /**
     * List files + folders for a given folder (JSON).
     */
    public function listFiles(): ?\Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        if (!$this->hasRole(self::ROLE_EDITOR)) {
            return $this->jsonError('not_authenticated');
        }
        $folderId = $this->request->getData('folder_id');
        $folderId = ($folderId === null || $folderId === '') ? null : (int)$folderId;

        $folders = $this->fetchTable('MediaFolders')->find()
            ->where($folderId ? ['parent_id' => $folderId] : ['parent_id IS' => null])
            ->orderBy(['name' => 'ASC'])->all();

        $files = $this->fetchTable('MediaFiles')->find()
            ->where($folderId ? ['folder_id' => $folderId] : ['folder_id IS' => null])
            ->orderBy(['original_name' => 'ASC'])->all();

        // Build usage map: scan all page content for /downloads/{id}/
        $usageMap = $this->buildUsageMap();

        $folderList = [];
        foreach ($folders as $f) {
            $folderList[] = [
                'id' => $f->id, 'name' => $f->name,
                'created' => $f->created ? $f->created->format('d.m.Y H:i') : '',
            ];
        }

        $fileList = [];
        foreach ($files as $f) {
            $fileList[] = [
                'id' => $f->id, 'name' => $f->original_name,
                'size' => $this->formatFilesize($f->file_size),
                'sizeBytes' => $f->file_size,
                'mime' => $f->mime_type,
                'displayMode' => $f->display_mode ?? 'download',
                'visibleGuest' => (bool)($f->visible_guest ?? 1),
                'visibleEditor' => (bool)($f->visible_editor ?? 1),
                'visibleContributor' => (bool)($f->visible_contributor ?? 1),
                'visibleAdmin' => (bool)($f->visible_admin ?? 1),
                'downloads' => $f->download_count,
                'created' => $f->created ? $f->created->format('d.m.Y H:i') : '',
                'url' => '/downloads/' . $f->id . '/' . rawurlencode($f->original_name),
                'usedIn' => $usageMap[$f->id] ?? [],
            ];
        }

        // Breadcrumb path for current folder
        $breadcrumb = $this->folderBreadcrumb($folderId);

        return $this->jsonSuccess([
            'folders' => $folderList,
            'files' => $fileList,
            'breadcrumb' => $breadcrumb,
            'currentFolder' => $folderId,
        ]);
    }

    /**
     * Upload a file.
     */
    public function upload(): ?\Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        if (!$this->hasRole(self::ROLE_EDITOR)) {
            return $this->jsonError('not_authenticated');
        }

        $file = $this->request->getUploadedFile('file');
        if (!$file || $file->getError() !== UPLOAD_ERR_OK) {
            return $this->jsonError('upload_failed');
        }

        $maxSize = Configure::read('Manual.maxUploadSize') ?? 10485760;
        if ($file->getSize() > $maxSize) {
            return $this->jsonError('file_too_large');
        }

        // Validate via UploadService (extension, MIME, size)
        $validationError = \App\Service\UploadService::validate($file);
        if ($validationError) {
            return $this->jsonError($validationError);
        }

        $originalName = basename($file->getClientFilename());
        // Determine real MIME type via finfo, not client-reported
        $tmpPath = $file->getStream()->getMetadata('uri');
        $mime = ($tmpPath && file_exists($tmpPath))
            ? (new \finfo(FILEINFO_MIME_TYPE))->file($tmpPath)
            : ($file->getClientMediaType() ?? 'application/octet-stream');
        $folderId = $this->request->getData('folder_id');
        $folderId = ($folderId === null || $folderId === '') ? null : (int)$folderId;
        $user = $this->currentUser();

        // Check for name conflict in target folder
        $tbl = $this->fetchTable('MediaFiles');
        $conflict = $tbl->find()->where([
            'original_name' => $originalName,
            $folderId ? 'folder_id' : 'folder_id IS' => $folderId ?? null,
        ])->first();
        if ($conflict) {
            return $this->jsonError('name_conflict');
        }

        // Save DB record first to get ID
        $entity = $tbl->newEntity(['folder_id' => $folderId]);
        $entity->set('original_name', $originalName);
        $entity->set('mime_type', $mime);
        $entity->set('file_size', $file->getSize());
        $entity->set('uploaded_by', $user['id'] ?? 0);
        if (!$tbl->save($entity)) {
            return $this->jsonError('save_failed');
        }

        // Store file as {id}_{original_name}
        $storedName = $entity->id . '_' . $originalName;
        $entity->set('filename', $storedName);
        if (!$tbl->save($entity)) {
            $tbl->delete($entity);
            return $this->jsonError('save_failed');
        }

        try {
            $file->moveTo($this->storageDir . $storedName);
        } catch (\Exception $e) {
            $tbl->delete($entity);
            Log::error('File upload moveTo failed: ' . $e->getMessage());
            return $this->jsonError('upload_failed');
        }

        return $this->jsonSuccess([
            'id' => $entity->id,
            'name' => $originalName,
            'url' => '/downloads/' . $entity->id . '/' . rawurlencode($originalName),
            'size' => $this->formatFilesize((int)$file->getSize()),
        ]);
    }

    /**
     * Delete a file (contributor+).
     */
    public function delete(): ?\Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        if (!$this->hasRole(self::ROLE_CONTRIBUTOR)) {
            return $this->jsonError('not_authenticated');
        }

        $ids = $this->request->getData('ids', []);
        $singleId = (int)$this->request->getData('id', 0);
        if ($singleId) {
            $ids = [$singleId];
        }
        if (empty($ids)) {
            return $this->jsonError('invalid_id');
        }

        $tbl = $this->fetchTable('MediaFiles');
        $usageMap = $this->buildUsageMap();
        $deleted = 0;
        $blocked = [];

        foreach ($ids as $id) {
            $id = (int)$id;
            if (!empty($usageMap[$id])) {
                $file = $tbl->find()->where(['id' => $id])->first();
                $blocked[] = $file ? $file->original_name : "#{$id}";
                continue;
            }
            $file = $tbl->find()->where(['id' => $id])->first();
            if (!$file) {
                continue;
            }
            $path = $this->storageDir . $file->filename;
            if (file_exists($path)) {
                @unlink($path);
            }
            $tbl->delete($file);
            $deleted++;
        }

        if (!empty($blocked)) {
            return $this->jsonSuccess([
                'deleted' => $deleted,
                'blocked' => $blocked,
                'message' => count($blocked) . ' file(s) in use and cannot be deleted.',
            ]);
        }
        return $this->jsonSuccess(['deleted' => $deleted]);
    }

    /**
     * Download/display a file by ID. Route: /downloads/{id}/{original_name}
     */
    public function download(): void
    {
        $this->autoRender = false;
        $pass = $this->request->getParam('pass');

        if (empty($pass[0]) || !is_numeric($pass[0])) {
            $this->response = $this->response->withStatus(404);
            return;
        }

        $tbl = $this->fetchTable('MediaFiles');
        $file = $tbl->find()->where(['id' => (int)$pass[0]])->first();

        if (!$file) {
            $this->response = $this->response->withStatus(404);
            return;
        }

        // Visibility check: user's role must have visibility enabled
        $userRole = $this->isLoggedIn()
            ? ($this->request->getSession()->read('Auth.role') ?? 'guest')
            : 'guest';
        $validRoles = ['guest', 'editor', 'contributor', 'admin'];
        if (!in_array($userRole, $validRoles, true)) {
            $userRole = 'guest';
        }
        $visField = 'visible_' . $userRole;
        if ($file->has($visField) && !$file->get($visField)) {
            $this->response = $this->response->withStatus(403);
            return;
        }

        $path = $this->storageDir . $file->filename;
        if (!file_exists($path)) {
            $this->response = $this->response->withStatus(404);
            return;
        }

        // Atomic increment — no race condition
        $tbl->getConnection()->execute(
            'UPDATE media_files SET download_count = download_count + 1 WHERE id = ?',
            [$file->id]
        );

        $isInline = ($file->display_mode ?? 'download') === 'inline';
        $fileSize = filesize($path);
        $mime = $file->mime_type ?: 'application/octet-stream';
        $disposition = $isInline ? 'inline' : 'attachment';
        $encodedName = rawurlencode($file->original_name);

        // Range request support (resume downloads, video seeking)
        $rangeHeader = $this->request->getHeaderLine('Range');
        $start = 0;
        $end = $fileSize - 1;
        $statusCode = 200;

        if (!empty($rangeHeader) && preg_match('/bytes=(\d*)-(\d*)/', $rangeHeader, $m)) {
            $start = $m[1] !== '' ? (int)$m[1] : 0;
            $end = $m[2] !== '' ? min((int)$m[2], $fileSize - 1) : $fileSize - 1;
            if ($start > $end || $start >= $fileSize) {
                $this->response = $this->response
                    ->withStatus(416)
                    ->withHeader('Content-Range', "bytes */{$fileSize}");
                return;
            }
            $statusCode = 206;
        }

        $length = $end - $start + 1;
        $this->response = $this->response
            ->withStatus($statusCode)
            ->withType($mime)
            ->withHeader('Accept-Ranges', 'bytes')
            ->withHeader('Content-Length', (string)$length)
            ->withHeader(
                'Content-Disposition',
                "{$disposition}; filename=\""
                . str_replace(["\r", "\n", "\0", '"'], ['', '', '', '\\"'], $file->original_name)
                . "\"; filename*=UTF-8''{$encodedName}"
            )
            ->withHeader('Cache-Control', 'public, max-age=86400');

        if ($statusCode === 206) {
            $this->response = $this->response
                ->withHeader('Content-Range', "bytes {$start}-{$end}/{$fileSize}");
        }

        // Stream the file (or partial content)
        $stream = new \Laminas\Diactoros\CallbackStream(function () use ($path, $start, $length) {
            $fp = fopen($path, 'rb');
            if ($start > 0) {
                fseek($fp, $start);
            }
            $remaining = $length;
            while ($remaining > 0 && !feof($fp)) {
                $chunk = min(8192, $remaining);
                echo fread($fp, $chunk);
                $remaining -= $chunk;
                if (ob_get_level()) {
                    ob_flush();
                }
                flush();
            }
            fclose($fp);
        });
        $this->response = $this->response->withBody($stream);
    }

    /**
     * Browse files for Summernote link/image picker (JSON).
     */
    public function browse(): ?\Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        if (!$this->hasRole(self::ROLE_EDITOR)) {
            return $this->jsonError('not_authenticated');
        }

        $folderId = $this->request->getData('folder_id');
        $folderId = ($folderId === null || $folderId === '') ? null : (int)$folderId;

        $folders = $this->fetchTable('MediaFolders')->find()
            ->where($folderId ? ['parent_id' => $folderId] : ['parent_id IS' => null])
            ->orderBy(['name' => 'ASC'])->all();

        $files = $this->fetchTable('MediaFiles')->find()
            ->where($folderId ? ['folder_id' => $folderId] : ['folder_id IS' => null])
            ->orderBy(['original_name' => 'ASC'])->all();

        $breadcrumb = $this->folderBreadcrumb($folderId);

        $folderList = [];
        foreach ($folders as $f) {
            $folderList[] = ['id' => $f->id, 'name' => $f->name];
        }
        $fileList = [];
        foreach ($files as $f) {
            $fileList[] = [
                'id' => $f->id, 'name' => $f->original_name,
                'url' => '/downloads/' . $f->id . '/' . rawurlencode($f->original_name),
                'mime' => $f->mime_type,
            ];
        }

        return $this->jsonSuccess([
            'folders' => $folderList,
            'files' => $fileList,
            'breadcrumb' => $breadcrumb,
        ]);
    }

    /**
     * Create a folder.
     */
    public function createFolder(): ?\Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        if (!$this->hasRole(self::ROLE_CONTRIBUTOR)) {
            return $this->jsonError('not_authenticated');
        }

        $name = trim($this->request->getData('name', ''));
        $parentId = $this->request->getData('parent_id');
        $parentId = ($parentId === null || $parentId === '') ? null : (int)$parentId;
        if (empty($name)) {
            return $this->jsonError('invalid_name');
        }

        $tbl = $this->fetchTable('MediaFolders');
        $entity = $tbl->newEntity([
            'name' => mb_substr($name, 0, 255),
            'parent_id' => $parentId,
        ]);
        $entity->set('created_by', $this->currentUser()['id'] ?? 0);
        if ($tbl->save($entity)) {
            return $this->jsonSuccess(['id' => $entity->id, 'name' => $name]);
        }
        return $this->jsonError('save_failed');
    }

    /**
     * Rename a folder (check for duplicate name in same parent).
     */
    public function renameFolder(): ?\Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        if (!$this->hasRole(self::ROLE_CONTRIBUTOR)) {
            return $this->jsonError('not_authenticated');
        }

        $id = (int)$this->request->getData('id', 0);
        $newName = trim($this->request->getData('name', ''));
        if (!$id || empty($newName)) {
            return $this->jsonError('invalid_name');
        }

        $tbl = $this->fetchTable('MediaFolders');
        $folder = $tbl->find()->where(['id' => $id])->first();
        if (!$folder) {
            return $this->jsonError('not_found');
        }

        // Check for name conflict in same parent
        $conflict = $tbl->find()->where([
            'name' => $newName,
            'id !=' => $id,
            $folder->parent_id ? 'parent_id' : 'parent_id IS' => $folder->parent_id,
        ])->first();
        if ($conflict) {
            return $this->jsonError('name_conflict');
        }

        $tbl->updateAll(['name' => mb_substr($newName, 0, 255)], ['id' => $id]);
        return $this->jsonSuccess(['renamed' => true]);
    }

    /**
     * Delete a folder (must not contain used files).
     */
    public function deleteFolder(): ?\Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        if (!$this->hasRole(self::ROLE_CONTRIBUTOR)) {
            return $this->jsonError('not_authenticated');
        }

        $ids = $this->request->getData('ids', []);
        $singleId = (int)$this->request->getData('id', 0);
        if ($singleId) {
            $ids = [$singleId];
        }
        if (empty($ids)) {
            return $this->jsonError('invalid_id');
        }

        $usageMap = $this->buildUsageMap();
        $deleted = 0;
        $blocked = [];

        foreach ($ids as $id) {
            $id = (int)$id;
            if ($this->folderHasUsedFiles($id, $usageMap)) {
                $folder = $this->fetchTable('MediaFolders')->find()->where(['id' => $id])->first();
                $blocked[] = $folder ? $folder->name : "#{$id}";
                continue;
            }
            // Recursively delete folder contents + folder itself
            $this->deleteFolderRecursive($id);
            $deleted++;
        }

        if (!empty($blocked)) {
            return $this->jsonSuccess([
                'deleted' => $deleted,
                'blocked' => $blocked,
                'message' => count($blocked) . ' folder(s) contain files in use and cannot be deleted.',
            ]);
        }
        return $this->jsonSuccess(['deleted' => $deleted]);
    }

    /**
     * Move a file to a different folder.
     */
    public function moveFile(): ?\Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        if (!$this->hasRole(self::ROLE_CONTRIBUTOR)) {
            return $this->jsonError('not_authenticated');
        }

        $ids = $this->request->getData('ids', []);
        $singleId = (int)$this->request->getData('id', 0);
        if ($singleId) {
            $ids = [$singleId];
        }
        $targetFolder = $this->request->getData('folder_id');
        $targetFolder = ($targetFolder === null || $targetFolder === '') ? null : (int)$targetFolder;

        if (empty($ids)) {
            return $this->jsonError('invalid_id');
        }

        $tbl = $this->fetchTable('MediaFiles');
        $moved = 0;
        $blocked = [];

        // Get names of files already in target folder
        $existingNames = $tbl->find()
            ->select(['original_name'])
            ->where($targetFolder ? ['folder_id' => $targetFolder] : ['folder_id IS' => null])
            ->all()->extract('original_name')->toList();

        foreach ($ids as $id) {
            $id = (int)$id;
            $file = $tbl->find()->where(['id' => $id])->first();
            if (!$file) {
                continue;
            }
            // Skip if already in target folder
            if ($file->folder_id === $targetFolder) {
                continue;
            }
            if (in_array($file->original_name, $existingNames)) {
                $blocked[] = $file->original_name;
                continue;
            }
            $tbl->updateAll(['folder_id' => $targetFolder], ['id' => $id]);
            $existingNames[] = $file->original_name;
            $moved++;
        }

        if (!empty($blocked)) {
            return $this->jsonSuccess([
                'moved' => $moved,
                'blocked' => $blocked,
                'message' => 'Name conflict: ' . implode(', ', $blocked),
            ]);
        }
        return $this->jsonSuccess(['moved' => $moved]);
    }

    /**
     * Move a folder to a different parent.
     */
    public function moveFolder(): ?\Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        if (!$this->hasRole(self::ROLE_CONTRIBUTOR)) {
            return $this->jsonError('not_authenticated');
        }

        $ids = $this->request->getData('ids', []);
        $singleId = (int)$this->request->getData('id', 0);
        if ($singleId) {
            $ids = [$singleId];
        }
        $targetParent = $this->request->getData('parent_id');
        $targetParent = ($targetParent === null || $targetParent === '') ? null : (int)$targetParent;

        if (empty($ids)) {
            return $this->jsonError('invalid_id');
        }

        $moved = 0;
        foreach ($ids as $id) {
            $id = (int)$id;
            if ($id === $targetParent) {
                continue;
            }
            if ($targetParent !== null && $this->isDescendant($id, $targetParent)) {
                continue;
            }
            $moved += $this->fetchTable('MediaFolders')->updateAll(
                ['parent_id' => $targetParent],
                ['id' => $id]
            );
        }

        return $this->jsonSuccess(['moved' => $moved]);
    }

    /**
     * Update file properties (display_mode, visibility).
     */
    public function updateFile(): ?\Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        if (!$this->hasRole(self::ROLE_CONTRIBUTOR)) {
            return $this->jsonError('not_authenticated');
        }

        $id = (int)$this->request->getData('id', 0);
        if (!$id) {
            return $this->jsonError('invalid_id');
        }

        $tbl = $this->fetchTable('MediaFiles');
        $file = $tbl->find()->where(['id' => $id])->first();
        if (!$file) {
            return $this->jsonError('not_found');
        }

        // Rename
        $newName = $this->request->getData('original_name');
        if ($newName !== null) {
            $newName = trim(basename($newName));
            if (empty($newName)) {
                return $this->jsonError('invalid_name');
            }
            if ($newName !== $file->original_name) {
                // Check for name conflict in same folder
                $conflict = $tbl->find()->where([
                    'original_name' => $newName,
                    'id !=' => $id,
                    $file->folder_id ? 'folder_id' : 'folder_id IS' => $file->folder_id,
                ])->first();
                if ($conflict) {
                    return $this->jsonError('name_conflict');
                }
                $tbl->updateAll(['original_name' => $newName], ['id' => $id]);
            }
        }

        $allowed = ['display_mode', 'visible_guest', 'visible_editor', 'visible_contributor', 'visible_admin'];
        $updates = [];
        foreach ($allowed as $field) {
            $val = $this->request->getData($field);
            if ($val !== null) {
                if (str_starts_with($field, 'visible_')) {
                    $updates[$field] = (int)(bool)$val;
                } elseif ($field === 'display_mode') {
                    $updates[$field] = in_array($val, ['inline', 'attachment', 'download', 'gallery'], true) ? $val : 'inline';
                }
            }
        }
        if (!empty($updates)) {
            $tbl->updateAll($updates, ['id' => $id]);
        }
        return $this->jsonSuccess(['updated' => true]);
    }

    // ── Helpers ──

    private function isDescendant(int $folderId, int $checkId): bool
    {
        $tbl = $this->fetchTable('MediaFolders');
        $current = $checkId;
        $visited = [];
        while ($current) {
            if ($current === $folderId) {
                return true;
            }
            if (in_array($current, $visited)) {
                break;
            }
            $visited[] = $current;
            $folder = $tbl->find()->where(['id' => $current])->first();
            $current = $folder ? $folder->parent_id : null;
        }
        return false;
    }

    /**
     * Check if a folder or any of its sub-folders contain files that are in use.
     */
    private function folderHasUsedFiles(int $folderId, array $usageMap, int $depth = 0): bool
    {
        if ($depth >= 50) {
            return false;
        }
        $files = $this->fetchTable('MediaFiles')->find()
            ->select(['id'])->where(['folder_id' => $folderId])->all();
        foreach ($files as $f) {
            if (!empty($usageMap[$f->id])) {
                return true;
            }
        }
        $subfolders = $this->fetchTable('MediaFolders')->find()
            ->select(['id'])->where(['parent_id' => $folderId])->all();
        foreach ($subfolders as $sf) {
            if ($this->folderHasUsedFiles($sf->id, $usageMap, $depth + 1)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Recursively delete a folder, its files, and sub-folders.
     * Only call after verifying no used files exist (folderHasUsedFiles).
     */
    private function deleteFolderRecursive(int $folderId, int $depth = 0): void
    {
        if ($depth >= 50) {
            return;
        }
        // Delete files in this folder
        $files = $this->fetchTable('MediaFiles')->find()->where(['folder_id' => $folderId])->all();
        foreach ($files as $f) {
            $path = $this->storageDir . $f->filename;
            if (file_exists($path)) {
                @unlink($path);
            }
            $this->fetchTable('MediaFiles')->delete($f);
        }
        // Recurse into sub-folders
        $subfolders = $this->fetchTable('MediaFolders')->find()->where(['parent_id' => $folderId])->all();
        foreach ($subfolders as $sf) {
            $this->deleteFolderRecursive($sf->id, $depth + 1);
        }
        // Delete the folder itself
        $folder = $this->fetchTable('MediaFolders')->find()->where(['id' => $folderId])->first();
        if ($folder) {
            $this->fetchTable('MediaFolders')->delete($folder);
        }
    }

    private function folderBreadcrumb(?int $folderId): array
    {
        if (!$folderId) {
            return [];
        }
        $tbl = $this->fetchTable('MediaFolders');
        $crumbs = [];
        $current = $folderId;
        $maxDepth = 50;
        while ($current && $maxDepth-- > 0) {
            $folder = $tbl->find()->where(['id' => $current])->first();
            if (!$folder) {
                break;
            }
            array_unshift($crumbs, ['id' => $folder->id, 'name' => $folder->name]);
            $current = $folder->parent_id;
        }
        return $crumbs;
    }

    private function buildUsageMap(): array
    {
        $pages = $this->fetchTable('Pages')->find()
            ->select(['id', 'title', 'content'])
            ->where(['deleted_at IS' => null])->all();
        $map = [];
        foreach ($pages as $p) {
            if (preg_match_all('#/downloads/(\d+)/#', $p->content ?? '', $matches)) {
                foreach ($matches[1] as $fileId) {
                    $fid = (int)$fileId;
                    if (!isset($map[$fid])) {
                        $map[$fid] = [];
                    }
                    $map[$fid][] = ['id' => $p->id, 'title' => $p->title];
                }
            }
        }
        return $map;
    }

    private function formatFilesize(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 1) . ' GB';
        }
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }
}
