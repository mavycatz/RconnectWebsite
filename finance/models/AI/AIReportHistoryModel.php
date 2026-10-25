<?php

namespace Finance\Models\AI;

require_once __DIR__
    . '/../../../app/Core/Database.php';

use App\Core\Database;
use PDO;

class AIReportHistoryModel
{
    private PDO $conn;

    public function __construct()
    {
        $this->conn =
            Database::get();
    }

    /*
    |--------------------------------------------------------------------------
    | SAVE REPORT
    |--------------------------------------------------------------------------
    */

    public function saveReport(

        $title,
        $type,
        $prompt,
        $content,
        $generatedBy = null

    ){

        $stmt =
            $this->conn->prepare("

                INSERT INTO ai_reports (

                    report_title,
                    report_type,
                    prompt,
                    report_content,
                    generated_by

                )

                VALUES (

                    :title,
                    :type,
                    :prompt,
                    :content,
                    :generated_by

                )

            ");

        return $stmt->execute([

            ':title' =>
                $title,

            ':type' =>
                $type,

            ':prompt' =>
                $prompt,

            ':content' =>
                $content,

            ':generated_by' =>
                $generatedBy

        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | GET REPORTS
    |--------------------------------------------------------------------------
    */

    public function getReports()
    {
        $stmt =
            $this->conn->prepare("

                SELECT *
                FROM ai_reports
                ORDER BY id DESC

            ");

        $stmt->execute();

        return
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );
    }
}