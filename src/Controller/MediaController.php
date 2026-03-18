<?php

declare(strict_types=1);

namespace App\Controller;

use Cake\Core\Configure;
use Cake\Log\Log;

/**
 * Media Controller — central media library with usage tracking.
 */
class MediaController extends AppController
{
    public function index(): ?\Cake\Http\Response
    {
        $this->autoRender = false;
        if (!$this->hasRole(self::ROLE_EDITOR)) {
            return $this->requireRole(self::ROLE_EDITOR) ? null : $this->response;
        }
        $mediaDir = ROOT . DS . 'storage' . DS . 'media' . DS;
        if (!is_dir($mediaDir)) {
            return $this->jsonSuccess(['files' => []]);
        }

        // Load all content for usage scan
        $pages = $this->fetchTable('Pages')->find()->select(['id', 'title',
            'content'])->where(['deleted_at IS' => null])->all();

        // Build filename→ID lookup from media_files table for correct download URLs
        $mediaFiles = $this->fetchTable('MediaFiles')->find()
            ->select(['id', 'filename', 'original_name'])->all();
        $fileIdMap = [];
        foreach ($mediaFiles as $mf) {
            $fileIdMap[$mf->filename] = [
                'id' => $mf->id,
                'original_name' => $mf->original_name,
            ];
        }

        $files = [];
        foreach (scandir($mediaDir) as $file) {
            if ($file[0] === '.' || is_dir($mediaDir . $file)) {
                continue;
            }
            $path = $mediaDir . $file;

            // Find which pages reference this file
            $usedIn = [];
            foreach ($pages as $p) {
                if (strpos($p->content ?? '', $file) !== false) {
                    $usedIn[] = ['id' => $p->id, 'title' => $p->title];
                }
            }

            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);

            // Use ID-based URL if file is registered; null for unregistered files
            if (isset($fileIdMap[$file])) {
                $url = '/downloads/' . $fileIdMap[$file]['id']
                    . '/' . rawurlencode($fileIdMap[$file]['original_name']);
            } else {
                // File exists on disk but not in media_files — no valid download URL
                $url = null;
            }

            $files[] = [
                'name' => $file,
                'size' => filesize($path),
                'sizeFormatted' => $this->formatSize(filesize($path)),
                'type' => $ext,
                'isImage' => $isImage,
                'uploaded' => date('d.m.Y H:i', filemtime($path)),
                'usedIn' => $usedIn,
                'usageCount' => count($usedIn),
                'url' => $url,
            ];
        }

        // Sort: unused first, then by name
        usort($files, function ($a, $b) {
            return $a['usageCount'] <=> $b['usageCount'] ?: strcmp($a['name'], $b['name']);
        });

        return $this->jsonSuccess(['files' => $files, 'total' => count($files), 'unused' =>
            count(array_filter($files, fn($f) => $f['usageCount'] === 0))]);
    }

    public function replace(): ?\Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        if (!$this->hasRole(self::ROLE_CONTRIBUTOR)) {
            return $this->requireRole(self::ROLE_CONTRIBUTOR) ? null : $this->response;
        }

        $oldName = $this->request->getData('old_name', '');
        $file = $this->request->getUploadedFile('file');
        if (empty($oldName) || !$file) {
            return $this->jsonError('invalid_data');
        }

        $error = \App\Service\UploadService::validate($file);
        if ($error) {
            return $this->jsonError($error);
        }

        $mediaDir = ROOT . DS . 'storage' . DS . 'media' . DS;
        $oldPath = $mediaDir . basename($oldName);
        if (!file_exists($oldPath)) {
            return $this->jsonError('file_not_found');
        }

        // Prevent file type swapping (e.g. replacing image.png with malicious.php)
        $oldExt = strtolower(pathinfo($oldName, PATHINFO_EXTENSION));
        $newExt = strtolower(pathinfo($file->getClientFilename(), PATHINFO_EXTENSION));
        if ($oldExt !== $newExt) {
            return $this->jsonError('file_type_mismatch');
        }

        // Verify MIME type matches original
        $oldMime = (new \finfo(FILEINFO_MIME_TYPE))->file($oldPath);
        $tmpPath = $file->getStream()->getMetadata('uri');
        $newMime = ($tmpPath && file_exists($tmpPath))
            ? (new \finfo(FILEINFO_MIME_TYPE))->file($tmpPath)
            : $file->getClientMediaType();
        // Allow within same MIME category (e.g. image/png → image/jpeg)
        $oldCategory = explode('/', $oldMime)[0] ?? '';
        $newCategory = explode('/', $newMime)[0] ?? '';
        if ($oldCategory !== $newCategory) {
            return $this->jsonError('file_type_mismatch');
        }

        // Replace file, keep same name → all links stay intact
        $file->moveTo($oldPath);
        $this->audit('media_replace', 'media', 0, "Replaced: {$oldName}");
        return $this->jsonSuccess(['success' => true, 'filename' => $oldName]);
    }

    private function formatSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }
}
