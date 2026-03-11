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

            $files[] = [
                'name' => $file,
                'size' => filesize($path),
                'sizeFormatted' => $this->formatSize(filesize($path)),
                'type' => $ext,
                'isImage' => $isImage,
                'uploaded' => date('d.m.Y H:i', filemtime($path)),
                'usedIn' => $usedIn,
                'usageCount' => count($usedIn),
                'url' => '/downloads/' . $file,
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
