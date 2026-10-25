<?php declare(strict_types=1);

namespace Admin\Models\RoleManagement;

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';

use App\Core\Database;
use PDO;

/**
 * Shared CV/resume profiles for the unified Role Management page.
 *
 * Additive storage keyed by subject type and source record reference; no
 * existing account table is altered. The table is ensured at runtime with the
 * same CREATE TABLE IF NOT EXISTS pattern used elsewhere in the app.
 */
class CvProfileModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::get();
    }

    public static function allowedTypes(): array
    {
        return ['driver', 'inspector', 'dispatcher', 'finance', 'admin'];
    }

    public function ensureTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS `user_cv_profiles` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `subject_type` VARCHAR(20) NOT NULL,
                `subject_ref` VARCHAR(64) NOT NULL,
                `resume_file` VARCHAR(255) NULL,
                `resume_text` MEDIUMTEXT NULL,
                `education` JSON NULL,
                `experience` JSON NULL,
                `skills` JSON NULL,
                `extracted_at` DATETIME NULL,
                `updated_by` VARCHAR(100) NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_cv_subject` (`subject_type`, `subject_ref`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    public function get(string $subjectType, string $subjectRef): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, subject_type, subject_ref, resume_file, resume_text, education, experience, skills,
                   extracted_at, updated_by, created_at, updated_at
            FROM user_cv_profiles
            WHERE subject_type = ? AND subject_ref = ?
            LIMIT 1
        ");
        $stmt->execute([$subjectType, $subjectRef]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        foreach (['education', 'experience', 'skills'] as $key) {
            $decoded = json_decode((string)($row[$key] ?? ''), true);
            $row[$key] = is_array($decoded) ? $decoded : [];
        }
        return $row;
    }

    /**
     * Insert or update the reviewed CV data for a subject.
     */
    public function save(string $subjectType, string $subjectRef, array $data, ?string $actor = null): array
    {
        if (!in_array($subjectType, self::allowedTypes(), true)) {
            throw new \InvalidArgumentException('Unsupported CV subject type.');
        }

        $education = self::normalizeList($data['education'] ?? []);
        $experience = self::normalizeList($data['experience'] ?? []);
        $skills = self::normalizeSkills($data['skills'] ?? []);
        $resumeFile = trim((string)($data['resume_file'] ?? ''));
        $resumeText = (string)($data['resume_text'] ?? '');
        if (strlen($resumeText) > 200000) {
            $resumeText = substr($resumeText, 0, 200000);
        }

        $existing = $this->get($subjectType, $subjectRef);

        $params = [
            ':subject_type' => $subjectType,
            ':subject_ref' => $subjectRef,
            ':resume_file' => $resumeFile !== '' ? $resumeFile : null,
            ':resume_text' => $resumeText !== '' ? $resumeText : null,
            ':education' => json_encode($education, JSON_UNESCAPED_UNICODE),
            ':experience' => json_encode($experience, JSON_UNESCAPED_UNICODE),
            ':skills' => json_encode($skills, JSON_UNESCAPED_UNICODE),
            ':extracted_at' => $resumeText !== '' ? date('Y-m-d H:i:s') : null,
            ':updated_by' => $actor,
        ];

        if ($existing === null) {
            $stmt = $this->db->prepare("
                INSERT INTO user_cv_profiles
                    (subject_type, subject_ref, resume_file, resume_text, education, experience, skills, extracted_at, updated_by)
                VALUES
                    (:subject_type, :subject_ref, :resume_file, :resume_text, :education, :experience, :skills, :extracted_at, :updated_by)
            ");
            $stmt->execute($params);
        } else {
            $stmt = $this->db->prepare("
                UPDATE user_cv_profiles SET
                    resume_file = :resume_file,
                    resume_text = :resume_text,
                    education = :education,
                    experience = :experience,
                    skills = :skills,
                    extracted_at = :extracted_at,
                    updated_by = :updated_by
                WHERE subject_type = :subject_type AND subject_ref = :subject_ref
            ");
            $stmt->execute($params);
        }

        return $this->get($subjectType, $subjectRef) ?? [];
    }

    private static function normalizeList($value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }
            $clean = [];
            foreach ($item as $key => $val) {
                $key = preg_replace('/[^a-z_]/i', '', (string)$key);
                $val = trim((string)$val);
                if ($key !== '' && $val !== '') {
                    $clean[$key] = mb_substr($val, 0, 400);
                }
            }
            if ($clean) {
                $out[] = $clean;
            }
        }
        return array_slice($out, 0, 50);
    }

    private static function normalizeSkills($value): array
    {
        if (is_string($value)) {
            $value = array_map('trim', explode(',', $value));
        }
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $skill) {
            $skill = trim((string)$skill);
            if ($skill !== '') {
                $out[] = mb_substr($skill, 0, 100);
            }
        }
        return array_slice(array_values(array_unique($out)), 0, 50);
    }
}
