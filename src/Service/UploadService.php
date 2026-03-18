<?php

declare(strict_types=1);

namespace App\Service;

use Cake\Core\Configure;
use Cake\Log\Log;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Upload Service — centralized file upload handling.
 *
 * Used by PagesController::uploadMedia() and FilesController::upload()
 * to ensure consistent validation, MIME checking, and naming across
 * all upload endpoints.
 */
class UploadService
{
    /** Server-executable extensions — always blocked. */
    private const BLOCKED_EXTENSIONS = [
        'php', 'phtml', 'phar', 'sh', 'bash', 'exe', 'bat', 'cmd',
        'cgi', 'pl', 'py', 'rb', 'jsp', 'asp', 'aspx',
        'svg', 'html', 'htm', 'shtml', 'xhtml',
    ];

    /** MIME types indicating executable content — always blocked. */
    private const BLOCKED_MIMES = [
        'application/x-httpd-php', 'application/x-php', 'text/x-php',
        'application/x-executable', 'application/x-sharedlib',
        'application/x-shellscript', 'application/x-perl',
    ];

    /**
     * Validate an uploaded file.
     *
     * @return string|null Error key if invalid, null if valid.
     */
    public static function validate(UploadedFileInterface $file): ?string
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            return 'upload_failed';
        }

        $filename = basename($file->getClientFilename());

        // Reject hidden files, empty names, unsafe characters
        if (empty($filename) || $filename[0] === '.' || !preg_match('/^[a-zA-Z0-9_\-\.]+$/', $filename)) {
            return 'invalid_filename';
        }

        // Block dangerous extensions (check ALL extensions to prevent double-extension attacks)
        $parts = explode('.', $filename);
        array_shift($parts); // remove the base name
        foreach ($parts as $part) {
            if (in_array(strtolower($part), self::BLOCKED_EXTENSIONS)) {
                return 'invalid_file_type';
            }
        }

        // Size limit from config
        $maxSize = Configure::read('Manual.maxUploadSize') ?? 10485760;
        if ($file->getSize() > $maxSize) {
            return 'file_too_large';
        }

        // MIME type check — real content via finfo, not just client-reported
        try {
            $tmpPath = $file->getStream()->getMetadata('uri');
            if ($tmpPath && file_exists($tmpPath)) {
                $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmpPath);
            } else {
                // Cannot determine real MIME type — reject for safety
                Log::warning('MIME check: temp file not accessible, rejecting upload');
                return 'upload_failed';
            }
            if (in_array($mime, self::BLOCKED_MIMES)) {
                return 'invalid_file_type';
            }
        } catch (\Exception $e) {
            Log::warning('MIME check failed: ' . $e->getMessage());
            return 'upload_failed';
        }

        return null;
    }

    /**
     * Generate a unique filename with timestamp prefix.
     * Used by PagesController::uploadMedia() for inline media.
     */
    public static function timestampedName(string $filename): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $filename);
        return date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '_' . $safe;
    }

    /**
     * Generate a safe filename with conflict resolution.
     * Used by FilesController::upload() for the file manager.
     * Appends _1, _2, etc. if a file with the same name exists.
     */
    public static function resolveConflict(string $filename, string $directory): string
    {
        $target = $directory . $filename;
        if (!file_exists($target)) {
            return $filename;
        }

        $base = pathinfo($filename, PATHINFO_FILENAME);
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $i = 1;
        while (file_exists($directory . "{$base}_{$i}.{$ext}")) {
            $i++;
        }
        return "{$base}_{$i}.{$ext}";
    }
}
