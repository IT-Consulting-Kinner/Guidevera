<?php

/**
 * CakePHP Manual Application
 */

declare(strict_types=1);

namespace App\Controller;

/**
 * Files Controller
 *
 * Manages file uploads and downloads for the application.
 * Files are stored in the `storage/media/` directory (outside webroot)
 * and served through the `download()` action for access control.
 *
 * ## Storage
 *
 * Files are stored in `ROOT/storage/media/` with flat naming (no subdirectories).
 * Download counts are tracked in hidden sidecar files (`.filename`) that
 * contain one timestamp per download.
 *
 * ## Security
 *
 * - Filenames are validated against `[a-zA-Z0-9_\-\.]` — no path traversal possible
 * - Hidden files (starting with `.`) are rejected
 * - Upload/delete require contributor role
 * - Downloads are available to all users (served via controller, not direct file access)
 *
 * @package App\Controller
 */

use Cake\Log\Log;

class FilesController extends AppController
{
    /**
     * @var string Absolute path to the media storage directory.
     */
    private string $mediaDir;

    /**
     * Initialize the controller and ensure the storage directory exists.
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->mediaDir = ROOT . DS . 'storage' . DS . 'media' . DS;
        if (!is_dir($this->mediaDir)) {
            mkdir($this->mediaDir, 0755, true);
        }
    }

    /**
     * File management index page.
     *
     * Lists all files with name, size, date, and download count.
     * Contributor+ only.
     *
     * @return void
     */
    public function index(): void
    {
        if (!$this->hasRole(self::ROLE_CONTRIBUTOR)) {
            $this->redirect('/user/login');
            return;
        }
        $files = $this->getFileList();
        $this->set(compact('files'));
    }

    /**
     * Handle file upload.
     *
     * Accepts a single file via multipart POST. Validates the filename
     * and moves the uploaded file to the storage directory.
     *
     * @return \Cake\Http\Response|null JSON with file metadata on success.
     */
    public function upload(): ?\Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        if (!$this->hasRole(self::ROLE_CONTRIBUTOR)) {
            return $this->jsonError('not_authenticated');
        }

        $file = $this->request->getUploadedFile('file');
        if (!$file) {
            return $this->jsonError('upload_failed');
        }

        $error = \App\Service\UploadService::validate($file);
        if ($error) {
            return $this->jsonError($error);
        }

        $filename = \App\Service\UploadService::resolveConflict(
            basename($file->getClientFilename()),
            $this->mediaDir
        );
        $target = $this->mediaDir . $filename;
        $file->moveTo($target);

        return $this->jsonSuccess([
            'success' => true,
            'filename' => $filename,
            'hash' => md5($filename),
            'size' => $this->formatFilesize(filesize($target)),
            'date' => date('d.m.Y H:i', filemtime($target)),
            'file' => [
                'name' => $filename,
                'size' => $this->formatFilesize(filesize($target)),
                'date' => date('d.m.Y H:i', filemtime($target)),
                'views' => 0,
            ],
        ]);
    }

    /**
     * Delete a file from storage.
     *
     * Also removes the associated download log sidecar file.
     * Contributor+ only.
     *
     * @return \Cake\Http\Response|null JSON success or error.
     */
    public function delete(): ?\Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        if (!$this->hasRole(self::ROLE_CONTRIBUTOR)) {
            return $this->jsonError('not_authenticated');
        }

        $filename = basename($this->request->getData('filename', ''));
        if (empty($filename) || $filename[0] === '.') {
            return $this->jsonError('invalid_filename');
        }

        $path = $this->mediaDir . $filename;
        if (!file_exists($path)) {
            return $this->jsonError('file_not_found');
        }

        if (unlink($path)) {
            // Remove download log sidecar file
            $logFile = $this->mediaDir . '.' . $filename;
            if (file_exists($logFile)) {
                unlink($logFile);
            }
            return $this->jsonSuccess(['success' => true]);
        }
        return $this->jsonError('delete_failed');
    }

    /**
     * Serve a file for download.
     *
     * Public endpoint — no authentication required.
     * Logs each download with a timestamp in a hidden sidecar file.
     * The file is served with `Content-Disposition: attachment`.
     *
     * @return void
     */
    public function download(): void
    {
        $this->autoRender = false;
        $filename = $this->request->getQuery('file', '');
        if (empty($filename)) {
            $args = $this->request->getParam('pass');
            $filename = !empty($args) ? $args[0] : '';
        }

        $filename = basename($filename);
        if (empty($filename) || $filename[0] === '.' || !preg_match('/^[a-zA-Z0-9_\-\.]+$/', $filename)) {
            $this->response = $this->response->withStatus(403);
            return;
        }

        $path = $this->mediaDir . $filename;
        if (!file_exists($path)) {
            $this->response = $this->response->withStatus(404);
            return;
        }

        // Append download timestamp to sidecar log file
        $logFile = $this->mediaDir . '.' . $filename;
        file_put_contents($logFile, date('Y-m-d H:i:s') . PHP_EOL, FILE_APPEND | LOCK_EX);

        $this->response = $this->response->withFile($path, ['download' => true, 'name' => $filename]);
    }

    /**
     * List all files for the browse dialog in the WYSIWYG editor.
     *
     * Returns a simple array of filenames. Used by the Summernote
     * link picker to let authors insert file download links.
     *
     * @return \Cake\Http\Response|null JSON with `{"files": [{"name": "file.pdf"}]}`.
     */
    public function browse(): ?\Cake\Http\Response
    {
        $this->autoRender = false;
        if (!$this->hasRole(self::ROLE_EDITOR)) {
            return $this->jsonError('not_authenticated');
        }

        $files = [];
        if (is_dir($this->mediaDir)) {
            foreach (scandir($this->mediaDir) as $entry) {
                if ($entry[0] === '.' || !is_file($this->mediaDir . $entry)) {
                    continue;
                }
                $files[] = ['name' => $entry];
            }
        }
        return $this->jsonSuccess(['files' => $files]);
    }

    // ── Private Helpers ────────────────────────────────────────────

    /**
     * Get the complete file list with metadata.
     *
     * @return array<int, array{name: string, size: string, date: string, views: int}>
     */
    private function getFileList(): array
    {
        $files = [];
        if (!is_dir($this->mediaDir)) {
            return $files;
        }

        foreach (scandir($this->mediaDir) as $entry) {
            if ($entry[0] === '.' || !is_file($this->mediaDir . $entry)) {
                continue;
            }
            $path = $this->mediaDir . $entry;
            $files[] = [
                'name' => $entry,
                'size' => $this->formatFilesize(filesize($path)),
                'date' => date('d.m.Y H:i', filemtime($path)),
                'views' => $this->countViews($entry),
            ];
        }
        return $files;
    }

    /**
     * Count download events for a file by reading its sidecar log.
     *
     * Each line in the sidecar file represents one download.
     *
     * @param string $filename The filename to count downloads for.
     * @return int Number of recorded downloads.
     */
    private function countViews(string $filename): int
    {
        $logFile = $this->mediaDir . '.' . $filename;
        if (!file_exists($logFile)) {
            return 0;
        }
        $content = file_get_contents($logFile);
        if (empty($content)) {
            return 0;
        }
        return count(explode(PHP_EOL, trim($content)));
    }

    /**
     * Format a byte count into a human-readable string.
     *
     * @param int $bytes File size in bytes.
     * @return string Formatted size (e.g., "1,5 MB").
     */
    private function formatFilesize(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2, ',', '.') . ' GB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2, ',', '.') . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2, ',', '.') . ' KB';
        }
        return $bytes . ' B';
    }
}
