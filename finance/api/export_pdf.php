<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ .
'/../../vendor/autoload.php';

if(!defined('APP_LOADED')){
    define('APP_LOADED', true);
}

require_once __DIR__ .
'/../../app/init.php';

require_once __DIR__ .
'/../middleware/validate_request.php';
require_once __DIR__ .
'/../../app/views/pdf/template.php';

use App\Core\Database;
use Dompdf\Dompdf;
use Dompdf\Options;

$id =
    (int)(
        $_GET['id']
        ?? 0
    );

if($id <= 0){

    die('Invalid report ID.');
}

try{

    $pdo =
        Database::get();

    /*
    |--------------------------------------------------------------------------
    | GET REPORT
    |--------------------------------------------------------------------------
    */

    $sql = "

    SELECT id, report_title AS title, report_content AS content, report_type AS type, generated_at, 'ai_reports' AS source
    FROM ai_reports
    WHERE id = :id

    ";

    $stmt =
        $pdo->prepare($sql);

    $stmt->execute([

        ':id' => $id
    ]);

    $report =
        $stmt->fetch();

    if(!$report){

        $sql2 = "

        SELECT id, title, report_data AS content, report_category AS type, generated_at, 'financial_reports' AS source
        FROM financial_reports
        WHERE id = :id2
        LIMIT 1

        ";

        $stmt2 =
            $pdo->prepare($sql2);

        $stmt2->execute([

            ':id2' => $id
        ]);

        $report =
            $stmt2->fetch();
    }

    if(!$report){

        die('Report not found.');
    }

    /*
    |--------------------------------------------------------------------------
    | REVENUE
    |--------------------------------------------------------------------------
    */

    $revenueQuery = "

    SELECT

        COALESCE(
            SUM(fare),
            0
        ) AS total_revenue,

        COUNT(*) AS total_passengers

    FROM passenger_trips

    ";

    $revenueStmt =
        $pdo->query(
            $revenueQuery
        );

    $revenue =
        $revenueStmt->fetch();

    /*
    |--------------------------------------------------------------------------
    | REMITTANCE
    |--------------------------------------------------------------------------
    */

    $expenseQuery = "

    SELECT

        COALESCE(
            SUM(total_collection),
            0
        ) AS total_collection

    FROM remittance_logs

    ";

    $expenseStmt =
        $pdo->query(
            $expenseQuery
        );

    $expenses =
        $expenseStmt->fetch();

    /*
    |--------------------------------------------------------------------------
    | PASSENGER TYPES
    |--------------------------------------------------------------------------
    */

    $passengerTypeQuery = "

    SELECT

        CASE

            WHEN passengers.discount_type IS NULL
            OR passengers.discount_type = ''
            THEN 'Regular'

            ELSE passengers.discount_type

        END AS passenger_type,

        COUNT(*) as total

    FROM passenger_trips

    LEFT JOIN passengers

    ON passenger_trips.passenger_id =
       passengers.id

    GROUP BY passenger_type

    ORDER BY total DESC

    ";

    $passengerTypeStmt =
        $pdo->query(
            $passengerTypeQuery
        );

    $passengerTypes =
        $passengerTypeStmt
        ->fetchAll();

    /*
    |--------------------------------------------------------------------------
    | TOP ROUTE
    |--------------------------------------------------------------------------
    */

    $routeQuery = "

    SELECT

        destination,

        COUNT(*) as total

    FROM passenger_trips

    GROUP BY destination

    ORDER BY total DESC

    LIMIT 1

    ";

    $routeStmt =
        $pdo->query(
            $routeQuery
        );

    $topRoute =
        $routeStmt->fetch();

    /*
    |--------------------------------------------------------------------------
    | NET PROFIT
    |--------------------------------------------------------------------------
    */

    $totalRevenue =
        (float)(
            $revenue['total_revenue']
            ?? 0
        );

    $totalCollection =
        (float)(
            $expenses['total_collection']
            ?? 0
        );

    $netProfit =
        $totalRevenue -
        $totalCollection;

    /*
    |--------------------------------------------------------------------------
    | EXECUTIVE SUMMARY
    |--------------------------------------------------------------------------
    */

    $executiveSummary = "

Transportation operations generated
₱" .

number_format(
    $totalRevenue,
    2
)

.

" in passenger revenue with a total of "

.

number_format(
    $revenue['total_passengers']
)

.

" passengers recorded across active routes.

The most active route was "

.

htmlspecialchars(
    $topRoute['destination']
    ?? 'Unknown Route'
)

.

", while transportation collection performance reached ₱"

.

number_format(
    $totalCollection,
    2
)

.

". Operational analytics indicate stable passenger movement trends with continued transportation demand across major routes.";

    /*
    |--------------------------------------------------------------------------
    | HTML
    |--------------------------------------------------------------------------
    */

    function cleanPdfText(string $text): string {
        $text = preg_replace('/_{3,}/', '—', $text);
        $text = preg_replace('/[ \t]+—[ \t]+/', ' — ', $text);
        return trim($text);
    }

    $cleanSummary = cleanPdfText($executiveSummary);

    $bodyContent = '

<div class="section">

    <div class="section-title">

        Executive Summary

    </div>

    <div class="summary-box">

        ' .

        nl2br(
            htmlspecialchars(
                $cleanSummary
            )
        )

        .

    '</div>

</div>

<div class="section">

    <div class="section-title">

        Transportation Performance Overview

    </div>

    <div class="stats-grid">

        <div class="stats-card">

            <div class="stats-title">
                Total Revenue
            </div>

            <div class="stats-value">
                ₱' .

                number_format(
                    $totalRevenue,
                    2
                )

                .

            '</div>

        </div>

        <div class="stats-card">

            <div class="stats-title">
                Total Collection
            </div>

            <div class="stats-value">
                ₱' .

                number_format(
                    $totalCollection,
                    2
                )

                .

            '</div>

        </div>

        <div class="stats-card">

            <div class="stats-title">
                Net Profit
            </div>

            <div class="' .

            (
                $netProfit >= 0
                ? 'stats-value green'
                : 'stats-value red'
            )

            .

            '">

                ₱' .

                number_format(
                    $netProfit,
                    2
                )

                .

            '</div>

        </div>

        <div class="stats-card">

            <div class="stats-title">
                Total Passengers
            </div>

            <div class="stats-value">

                ' .

                number_format(
                    $revenue['total_passengers']
                    ?? 0
                )

                .

            '</div>

        </div>

    </div>

</div>

<div class="section">

    <div class="section-title">

        Passenger Demographic Analysis

    </div>

    <table>

        <thead>

            <tr>

                <th>
                    Passenger Category
                </th>

                <th>
                    Total Passengers
                </th>

                <th>
                    Transportation Insight
                </th>

            </tr>

        </thead>

        <tbody>';

    foreach($passengerTypes as $type){

        $insight =
            $type['total'] > 20

            ? 'High passenger transportation demand detected.'

            : 'Moderate transportation activity recorded.';

        $bodyContent .= '

        <tr>

            <td>

                ' .

                htmlspecialchars(
                    $type['passenger_type']
                )

                .

            '</td>

            <td>

                ' .

                number_format(
                    $type['total']
                )

                .

            '</td>

            <td>

                ' .

                $insight

                .

            '</td>

        </tr>';
    }

    $bodyContent .= '

        </tbody>

    </table>

</div>

<div class="section">

    <div class="section-title">

        Transportation Operational Analysis

    </div>

    <div class="summary-box analysis">

        ' .

nl2br(
    htmlspecialchars(
        cleanPdfText(
            str_replace(
                ['***','**','*','##'],
                '',
                $report['content'] ?? $report['report_content']
            )
        )
    )
)

        .

    '</div>

</div>

<div class="section">

    <div class="section-title">

        Transportation Evidence Table

    </div>

    <table>

        <thead>

            <tr>

                <th>
                    Operational Metric
                </th>

                <th>
                    Value
                </th>

            </tr>

        </thead>

        <tbody>

            <tr>

                <td>
                    Total Revenue
                </td>

                <td>
                    ₱' .

                    number_format(
                        $totalRevenue,
                        2
                    )

                    .

                '</td>

            </tr>

            <tr>

                <td>
                    Total Collection
                </td>

                <td>
                ₱' .

                    number_format(
                        $totalCollection,
                        2
                    )

                    .

                '</td>

            </tr>

            <tr>

                <td>
                    Net Profit
                </td>

                <td>
                    ₱' .

                    number_format(
                        $netProfit,
                        2
                    )

                    .

                '</td>

            </tr>

            <tr>

                <td>
                    Most Active Route
                </td>

                <td>

                    ' .

                    htmlspecialchars(
                        $topRoute['destination']
                        ?? 'Unknown'
                    )

                    .

                '</td>

            </tr>

            <tr>

                <td>
                    Total Passengers
                </td>

                <td>

                    ' .

                    number_format(
                        $revenue['total_passengers']
                        ?? 0
                    )

                    .

                '</td>

            </tr>

        </tbody>

    </table>

</div>

';

    /*
    |--------------------------------------------------------------------------
    | RENDER PDF
    |--------------------------------------------------------------------------
    */

    $html = renderPdfHtml(
        title: $report['title'] ?? $report['report_title'],
        bodyContent: $bodyContent,
        orgSub: 'Transportation Management System &mdash; Intelligence Report',
        meta: ['Report #' . $report['id'], $report['type'] ?? 'Financial', 'Generated: ' . $report['generated_at']]
    );

    /*
    |--------------------------------------------------------------------------
    | DOMPDF
    |--------------------------------------------------------------------------
    */

    $options =
        new Options();

    $options->set(
        'isRemoteEnabled',
        true
    );

    $dompdf =
        new Dompdf(
            $options
        );

    $dompdf->loadHtml(
        $html
    );

    $dompdf->setPaper(
        'A4',
        'portrait'
    );

    $dompdf->render();

    /*
    |--------------------------------------------------------------------------
    | DOWNLOAD
    |--------------------------------------------------------------------------
    */

    $filename =

        'transportation_report_' .

        $report['id']

        .

        '.pdf';

    $dompdf->stream(

        $filename,

        [
            'Attachment' => true
        ]
    );

}catch(Throwable $e){

    die(

        'PDF Export Error: '

        .

        $e->getMessage()
    );
}