<?php declare(strict_types=1);

namespace Admin\Services\RoleManagement;

/**
 * Shared upload validation and storage for the Role Management page.
 *
 * Document uploads (CVs and supporting documents) are validated by extension,
 * detected MIME type, and size before being stored in a protected directory.
 */
class UploadService
{
    public const CV_EXT = ['pdf', 'doc', 'docx'];
    public const DOC_EXT = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];

    public const CV_MAX_BYTES = 5242880;   // 5 MB
    public const DOC_MAX_BYTES = 5242880;  // 5 MB

    private const MIME_BY_EXT = [
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword', 'application/vnd.ms-word', 'application/octet-stream'],
        'docx' => [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
            'application/octet-stream',
        ],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
    ];

    private const PHP_DENY_HTACCESS = "Options -Indexes\n\n<FilesMatch \"\\.(php|phtml|phar|pl|py|cgi|sh|asp|aspx|jsp|shtml)$\">\n    <IfModule mod_authz_core.c>\n        Require all denied\n    </IfModule>\n    <IfModule !mod_authz_core.c>\n        Order allow,deny\n        Deny from all\n    </IfModule>\n</FilesMatch>\n";

    /**
     * Validate an uploaded file for the given kind ('cv' or 'document').
     *
     * @return array{ok:bool,error:?string,ext:?string}
     */
    public static function validateFile(array $file, string $kind): array
    {
        $allowed = $kind === 'cv' ? self::CV_EXT : self::DOC_EXT;
        $maxBytes = $kind === 'cv' ? self::CV_MAX_BYTES : self::DOC_MAX_BYTES;

        if (!isset($file['tmp_name']) || !is_file((string)$file['tmp_name'])) {
            return ['ok' => false, 'error' => 'No file was uploaded.', 'ext' => null];
        }

        $error = (int)($file['error'] ?? UPLOAD_ERR_OK);
        if ($error !== UPLOAD_ERR_OK) {
            $message = $error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE
                ? 'The file is larger than the allowed size.'
                : 'The file could not be uploaded.';
            return ['ok' => false, 'error' => $message, 'ext' => null];
        }

        $original = (string)($file['name'] ?? '');
        $ext = strtolower((string)pathinfo($original, PATHINFO_EXTENSION));
        if ($ext === '' || !in_array($ext, $allowed, true)) {
            return [
                'ok' => false,
                'error' => 'Allowed file types: ' . strtoupper(implode(', ', $allowed)) . '.',
                'ext' => null,
            ];
        }

        $size = (int)($file['size'] ?? 0);
        if ($size <= 0) {
            $size = (int)(@filesize((string)$file['tmp_name']) ?: 0);
        }
        if ($size <= 0) {
            return ['ok' => false, 'error' => 'The uploaded file is empty.', 'ext' => null];
        }
        if ($size > $maxBytes) {
            return [
                'ok' => false,
                'error' => 'The file exceeds the ' . round($maxBytes / 1048576) . ' MB limit.',
                'ext' => null,
            ];
        }

        $mime = '';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = (string)(finfo_file($finfo, (string)$file['tmp_name']) ?: '');
            }
        }

        $allowedMime = self::MIME_BY_EXT[$ext] ?? [];
        if ($allowedMime && $mime !== '' && !in_array(strtolower($mime), $allowedMime, true)) {
            return [
                'ok' => false,
                'error' => 'The file content does not match its type.',
                'ext' => null,
            ];
        }

        return ['ok' => true, 'error' => null, 'ext' => $ext];
    }

    /**
     * Validate and store an uploaded file under uploads/<subdir>/.
     *
     * @return string stored path with a leading slash (e.g. /uploads/cv/driver/file.pdf)
     * @throws \RuntimeException on validation or storage failure
     */
    public static function store(array $file, string $subdir, string $kind = 'document'): string
    {
        $check = self::validateFile($file, $kind);
        if (($check['ok'] ?? false) !== true) {
            throw new \RuntimeException((string)($check['error'] ?? 'Invalid upload.'));
        }

        $subdir = trim(str_replace('\\', '/', $subdir), '/');
        $baseDir = rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/\\');
        $targetDir = $baseDir . '/uploads/' . $subdir;
        if (!is_dir($targetDir) && !@mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
            throw new \RuntimeException('Could not create the upload directory.');
        }

        self::ensureProtected($baseDir . '/uploads/' . explode('/', $subdir)[0]);

        $original = (string)($file['name'] ?? 'file');
        $safeName = preg_replace('/[^A-Za-z0-9._\-]/', '_', basename($original)) ?: 'file';
        $safeName = substr($safeName, 0, 120);
        $filename = uniqid('', true) . '_' . $safeName;
        $target = $targetDir . '/' . $filename;

        $stored = @move_uploaded_file((string)$file['tmp_name'], $target)
            || @rename((string)$file['tmp_name'], $target)
            || @copy((string)$file['tmp_name'], $target);
        if (!$stored) {
            throw new \RuntimeException('Could not store the uploaded file.');
        }

        return '/uploads/' . $subdir . '/' . $filename;
    }

    /**
     * Create a PHP-execution-deny .htaccess in a directory when missing.
     */
    public static function ensureProtected(string $absoluteDir): void
    {
        if (!is_dir($absoluteDir)) {
            return;
        }
        $htaccess = rtrim($absoluteDir, '/\\') . '/.htaccess';
        if (!is_file($htaccess)) {
            @file_put_contents($htaccess, self::PHP_DENY_HTACCESS);
        }
    }

    /**
     * Normalize a stored path for display/URL use (leading slash).
     */
    public static function normalizePath(?string $path): string
    {
        $path = trim((string)$path);
        if ($path === '') {
            return '';
        }
        return '/' . ltrim(str_replace('\\', '/', $path), '/');
    }

    /**
     * Resolve a stored path to a filesystem path, tolerating both
     * "uploads/..." and "/uploads/..." forms. Returns null when missing.
     */
    public static function resolvePath(?string $path): ?string
    {
        $path = trim((string)$path);
        if ($path === '') {
            return null;
        }
        $candidate = rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/\\') . '/' . ltrim(str_replace('\\', '/', $path), '/');
        return is_file($candidate) ? $candidate : null;
    }
}
