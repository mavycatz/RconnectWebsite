<?php

declare(strict_types = 1);


require_once $_SERVER['DOCUMENT_ROOT']
.'/admin/Controllers/CategoriesReportController.php';

require_once $_SERVER['DOCUMENT_ROOT']
.'/admin/models/CategoriesReportModel.php';

require_once $_SERVER['DOCUMENT_ROOT']
.'/app/Core/Database.php';


use admin\Controllers\CategoriesReportController;
use App\Core\Database;


$controller =

new CategoriesReportController();


$type =

$_GET['type']

??

'generated';


$selectedCategory =

$_GET['category']

??

'';


$isRecent =

isset($_GET['recent']);


$reports = [];


$db = Database::get();


if ($isRecent) {


    $generatedPath =

    $_SERVER['DOCUMENT_ROOT']

    .

    '/generated_reports';

    if (is_dir($generatedPath)) {

        $folders = scandir($generatedPath);

        foreach ($folders as $folder) {

            if (

            $folder === '.'

            ||

            $folder === '..'

            ||

            !is_dir($generatedPath.'/'.$folder)

            ) {
                continue;
            }

            $files = glob(

            $generatedPath
            .
            '/'
            .
            $folder
            .
            '/*.pdf'
            );

            foreach ($files as $file) {

                $reports[] = [

                'category' =>

                $folder,

                'title' =>

                pathinfo(
                $file,
                PATHINFO_FILENAME
                ),

                'printed' =>

                'System',

                'timestamp' =>

                filemtime($file),

                'date' =>

                date(
                'F d, Y',
                filemtime($file)
                ),

                'size' =>

                round(
                filesize($file)
                /
                1024
                /
                1024,
                2
                )
                .' MB',

                'file' =>

                str_replace(

                $_SERVER['DOCUMENT_ROOT'],

                '',

                $file
                )
                ];
            }
        }
    }


    $stmt = $db->query("

        SELECT
            report_title,
            report_category,
            generated_at
            
        FROM ai_trip_operations_reports

        WHERE archived = 0

    ");

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {

        $path =

        $_SERVER['DOCUMENT_ROOT']

        .

        $row['pdf_path'];

        $reports[] = [

        'category' =>

        $row['report_category'],

        'title' =>

        $row['report_title'],

        'printed' =>

        $row['generated_by']

        ??

        'AI System',

        'timestamp' =>

        strtotime(
        $row['report_date']
        ),

        'date' =>

        date(
        'F d, Y',
        strtotime(
        $row['report_date']
        )
        ),

        'size' =>

        file_exists($path)

        ?

        round(
        filesize($path)
        /
        1024
        /
        1024,
        2
        )
        .' MB'

        :

        'N/A',

        'file' =>

        $row['pdf_path']
        ];
    }


    $stmt = $db->query("

        SELECT
            trip_group_code,
            report_category,
            created_at
            
        FROM trip_operations_reports

        WHERE archived = 0

    ");

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {

        $path =

        $_SERVER['DOCUMENT_ROOT']

        .

        $row['pdf_path'];

        $reports[] = [

        'category' =>

        $row['report_category'],

        'title' =>

        $row['report_title'],

        'printed' =>

        $row['generated_by']

        ??

        'Operations System',

        'timestamp' =>

        strtotime(
        $row['report_date']
        ),

        'date' =>

        date(
        'F d, Y',
        strtotime(
        $row['report_date']
        )
        ),

        'size' =>

        file_exists($path)

        ?

        round(
        filesize($path)
        /
        1024
        /
        1024,
        2
        )
        .' MB'

        :

        'N/A',

        'file' =>

        $row['pdf_path']
        ];
    }



    $finStmt = $db->query("

        SELECT
            id,
            title,
            report_category,
            generated_by,
            generated_at

        FROM financial_reports

        WHERE archived = 0

        ORDER BY generated_at DESC

    ");

    $finRows = $finStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($finRows as $row) {

        $reports[] = [

        'category' =>

        'Finance — '
        .
        ($row['report_category']
        ?? 'General'),

        'title' =>

        $row['title'],

        'printed' =>

        $row['generated_by']
        ??
        'Finance System',

        'timestamp' =>

        strtotime(
        $row['generated_at']
        ),

        'date' =>

        date(
        'F d, Y',
        strtotime(
        $row['generated_at']
        )
        ),

        'size' => 'N/A',

        'file' =>

        '/finance/api/financial_reports_api.php'
        .
        '?action=export_report_pdf&id='
        .
        $row['id']
        ];
    }


    usort(

    $reports,

    function($a, $b) {

        return $b['timestamp']

        <=>

        $a['timestamp'];
    }
    );


    $reports = array_slice(
    $reports,
    0,
    20
    );


    $pageTitle =

    'Recently Added Reports';
}


elseif ($type === 'generated') {

    $generatedPath =

    $_SERVER['DOCUMENT_ROOT']

    .

    '/generated_reports';

    if (is_dir($generatedPath)) {

        $folders = scandir($generatedPath);

        foreach ($folders as $folder) {

            if (

            $folder === '.'

            ||

            $folder === '..'

            ||

            !is_dir($generatedPath.'/'.$folder)

            ) {
                continue;
            }

            if (

            !empty($selectedCategory)

            &&

            $selectedCategory !== $folder

            ) {
                continue;
            }

            $files = glob(

            $generatedPath
            .
            '/'
            .
            $folder
            .
            '/*.pdf'
            );

            foreach ($files as $file) {

                $reports[] = [

                'category' =>

                $folder,

                'title' =>

                pathinfo(
                $file,
                PATHINFO_FILENAME
                ),

                'printed' =>

                'System',

                'timestamp' =>

                filemtime($file),

                'date' =>

                date(
                'F d, Y',
                filemtime($file)
                ),

                'size' =>

                round(
                filesize($file)
                /
                1024
                /
                1024,
                2
                )
                .' MB',

                'file' =>

                str_replace(

                $_SERVER['DOCUMENT_ROOT'],

                '',

                $file
                )
                ];
            }
        }
    }

    $pageTitle =

    ucwords(
    str_replace(
    '_',
    ' ',
    $selectedCategory
    )
    );
}


elseif ($type === 'ai') {

    $stmt = $db->prepare("

        SELECT
            report_title,
            report_category,
            generated_by,
            report_date,
            pdf_path

        FROM ai_trip_operations_reports

        WHERE archived = 0

        AND report_category = :category

        ORDER BY report_date DESC

    ");

    $stmt->execute([

    'category' =>

    $selectedCategory
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {

        $path =

        $_SERVER['DOCUMENT_ROOT']

        .

        $row['pdf_path'];

        $reports[] = [

        'category' =>

        $row['report_category'],

        'title' =>

        $row['report_title'],

        'printed' =>

        $row['generated_by']

        ??

        'AI System',

        'timestamp' =>

        strtotime(
        $row['report_date']
        ),

        'date' =>

        date(
        'F d, Y',
        strtotime(
        $row['report_date']
        )
        ),

        'size' =>

        file_exists($path)

        ?

        round(
        filesize($path)
        /
        1024
        /
        1024,
        2
        )
        .' MB'

        :

        'N/A',

        'file' =>

        $row['pdf_path']
        ];
    }

    $pageTitle =

    ucwords(
    str_replace(
    '_',
    ' ',
    $selectedCategory
    )
    );
}


elseif ($type === 'trip') {

    $stmt = $db->prepare("

        SELECT
            report_title,
            report_category,
            generated_by,
            report_date,
            pdf_path

        FROM trip_operations_reports

        WHERE archived = 0

        AND report_category = :category

        ORDER BY report_date DESC

    ");

    $stmt->execute([

    'category' =>

    $selectedCategory
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {

        $path =

        $_SERVER['DOCUMENT_ROOT']

        .

        $row['pdf_path'];

        $reports[] = [

        'category' =>

        $row['report_category'],

        'title' =>

        $row['report_title'],

        'printed' =>

        $row['generated_by']

        ??

        'Operations System',

        'timestamp' =>

        strtotime(
        $row['report_date']
        ),

        'date' =>

        date(
        'F d, Y',
        strtotime(
        $row['report_date']
        )
        ),

        'size' =>

        file_exists($path)

        ?

        round(
        filesize($path)
        /
        1024
        /
        1024,
        2
        )
        .' MB'

        :

        'N/A',

        'file' =>

        $row['pdf_path']
        ];
    }

    $pageTitle =

    ucwords(
    str_replace(
    '_',
    ' ',
    $selectedCategory
    )
    );
}


elseif ($type === 'finance') {

    $finSql = "

        SELECT
            id,
            title,
            report_category,
            generated_by,
            generated_at

        FROM financial_reports

        WHERE archived = 0

    ";

    $finParams = [];

    if (!empty($selectedCategory)) {

        $finSql .= " AND report_category = :category";

        $finParams['category'] = $selectedCategory;
    }

    $finSql .= " ORDER BY generated_at DESC";

    $finStmt = $db->prepare($finSql);

    $finStmt->execute($finParams);

    $finRows = $finStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($finRows as $row) {

        $reports[] = [

        'category' =>

        'Finance — '
        .
        ($row['report_category']
        ?? 'General'),

        'title' =>

        $row['title'],

        'printed' =>

        $row['generated_by']
        ??
        'Finance System',

        'timestamp' =>

        strtotime(
        $row['generated_at']
        ),

        'date' =>

        date(
        'F d, Y',
        strtotime(
        $row['generated_at']
        )
        ),

        'size' => 'N/A',

        'file' =>

        '/finance/api/financial_reports_api.php'
        .
        '?action=export_report_pdf'
        .
        '&id='
        .
        $row['id']
        ];
    }

    $pageTitle =

    'Finance Reports';
}


usort(

$reports,

function($a, $b) {

    return $b['timestamp']

    <=>

    $a['timestamp'];
}
);


function reportLogo(array $report): array
{
    $formatted = ucwords(
    str_replace(
    '_',
    ' ',
    $report['title']
    )
    );

    $words = explode(
    ' ',
    $formatted
    );


    $initials = '';

    foreach (array_slice($words, 0, 2) as $word) {

        if (!empty($word[0])) {

            $initials .= strtoupper($word[0]);
        }
    }


    $background = '#f9f4da';
    $text = '#2e4836';
    $border = '#dfe7d1';

    $category = strtolower(
    $report['category']
    );


    if (str_contains($category, 'driver')) {

        $background = '#dbeafe';
        $text = '#1e3a8a';
        $border = '#bfdbfe';
    }


    elseif (str_contains($category, 'passenger')) {

        $background = '#dcfce7';
        $text = '#166534';
        $border = '#bbf7d0';
    }


    elseif (str_contains($category, 'trip')) {

        $background = '#fef3c7';
        $text = '#92400e';
        $border = '#fde68a';
    }


    elseif (str_contains($category, 'ai')) {

        $background = '#ede9fe';
        $text = '#5b21b6';
        $border = '#ddd6fe';
    }


    elseif (str_contains($category, 'remittance')) {

        $background = '#fee2e2';
        $text = '#991b1b';
        $border = '#fecaca';
    }


    elseif (str_contains($category, 'inspector')) {

        $background = '#e0f2fe';
        $text = '#075985';
        $border = '#bae6fd';
    }

    return [

    'initials' =>

    $initials
    ?: 'RP',

    'background' =>

    $background,

    'text' =>

    $text,

    'border' =>

    $border
    ];
}


?>


<style>
    @media(max-width:768px) {
        .report-card > div:first-child { flex-direction: column !important; align-items: flex-start !important; gap: 8px; }
        .report-card > div:first-child div[style*="font-size:1.45rem"] { font-size: 1.1rem !important; }
        .report-card > div:first-child div[style*="font-size:.82rem"] { font-size: 0.75rem !important; }
        .report-item { flex-direction: column !important; align-items: flex-start !important; gap: 12px !important; padding: 14px !important; }
        .report-item > div:first-child { width: 100%; }
        .report-item > div:first-child > div:first-child { width: 44px !important; height: 44px !important; font-size: 0.85rem !important; }
        .report-item > div:first-child > div:last-child div[style*="font-size:1rem"] { font-size: 0.85rem !important; }
        .report-item > div:first-child > div:last-child div[style*="font-size:.82rem"] { font-size: 0.75rem !important; }
        .report-item > div:last-child { text-align: left !important; width: 100%; display: flex; align-items: center; gap: 10px; }
        .report-item > div:last-child a { padding: 7px 12px !important; font-size: 0.75rem !important; }
        .report-item > div:last-child div[style*="border-radius:999px"] { font-size: 0.65rem !important; padding: 4px 8px !important; margin-bottom: 0 !important; }
        .report-card div[style*="padding:18px"][style*="background:#fff"] { padding: 12px !important; }
        .report-card div[style*="padding:60px"] { padding: 30px 16px !important; font-size: 0.85rem !important; }
        #reportSearch { padding: 10px 12px !important; font-size: 0.8rem !important; }
    }
</style>
<div class="report-card h-100 d-flex flex-column">


    <div style="
        display:flex;
        justify-content:space-between;
        align-items:center;
        margin-bottom:18px;
    ">

        <div>

            <div style="
                font-size:1.45rem;
                font-weight:700;
                color:#2e4836;
                margin-bottom:4px;
            ">

                <?= $pageTitle ?: 'Reports'; ?>

            </div>

            <div style="
                font-size:.82rem;
                color:#7a7a7a;
            ">

                Generated report records and exports

            </div>

            </div>

            </div>


            <div style="margin-bottom:18px;">

                <input
            type="text"
            id="reportSearch"
            placeholder="Search reports..."
            style="
                width:100%;
                padding:12px 14px;
                border:1px solid #dfe7d1;
                border-radius:10px;
                background:#fff;
                outline:none;
                font-size:.88rem;
            "
        >

            </div>


            <div style="
        flex:1;
        background:#fff;
        border:1px solid #dfe7d1;
        border-radius:14px;
        padding:18px;
        overflow:auto;
    ">

        <?php if (empty($reports)): ?>

        <div style="
            padding:60px 20px;
            text-align:center;
            color:#7a7a7a;
        ">

            No reports available.

        </div>

        <?php else: ?>

        <div id="reportList">

        <?php foreach ($reports as $i => $report): ?>

        <?php $logo = reportLogo($report); ?>

        <div
            class="report-item"
            data-name="<?= strtolower($report['title']); ?>"
            style="
                background:#fff;
                border:1px solid #e5eadf;
                border-radius:14px;
                padding:18px;
                margin-bottom:14px;
                display:flex;
                justify-content:space-between;
                align-items:center;
                <?= $i >= 5 ? 'display:none;' : '' ?>
            "
        >


            <div style="
                display:flex;
                gap:16px;
                align-items:flex-start;
            ">


                <div style="
                    width:56px;
                    height:56px;
                    border-radius:16px;
                    background:<?= $logo['background']; ?>;
                    border:1px solid <?= $logo['border']; ?>;
                    display:flex;
                    align-items:center;
                    justify-content:center;
                    color:<?= $logo['text']; ?>;
                    font-size:1rem;
                    font-weight:800;
                    letter-spacing:.8px;
                    flex-shrink:0;
                ">

                    <?= $logo['initials']; ?>

                </div>


                <div>

                    <div style="
                        font-size:1rem;
                        font-weight:700;
                        color:#2e4836;
                        margin-bottom:8px;
                    ">

                        <?= ucwords(
str_replace(
'_',
' ',
$report['title']
)
); ?>

                    </div>

                    <div style="
                        font-size:.82rem;
                        color:#6b7280;
                        margin-bottom:4px;
                    ">

                        Generated:
                        <?= $report['date']; ?>

                    </div>

                    <div style="
                        font-size:.82rem;
                        color:#6b7280;
                        margin-bottom:4px;
                    ">

                        File Size:
                        <?= $report['size']; ?>

                    </div>

                    <div style="
                        font-size:.82rem;
                        color:#6b7280;
                    ">

                        Generated by:
                        <?= $report['printed']; ?>

                    </div>

                    </div>

                    </div>


                    <div style="text-align:right;">

                        <div style="
                    background:#f9f4da;
                    color:#2e4836;
                    border:1px solid #dfe7d1;
                    padding:6px 10px;
                    border-radius:999px;
                    font-size:.72rem;
                    font-weight:700;
                    margin-bottom:10px;
                    display:inline-block;
                    text-transform:uppercase;
                ">

                    <?= ucwords(
str_replace(
'_',
' ',
$report['category']
)
); ?>

                </div>

                <br>

                <a
                    href="<?= $report['file']; ?>"
                    download
                    target="_blank"
                    style="
                        text-decoration:none;
                        background:#2e4836;
                        color:#fff;
                        padding:9px 14px;
                        border-radius:8px;
                        font-size:.82rem;
                        font-weight:600;
                        display:inline-block;
                    "
                >

                    Download

</a>

</div>

</div>

        <?php endforeach; ?>

        </div>

        <?php if (count($reports) > 5): ?>
        <div style="text-align:center;margin-top:16px;">
            <button id="showMoreBtn" onclick="toggleReports()" style="
                background:#fef8f3;
                border:2px solid #e7e3af;
                border-radius:12px;
                padding:10px 28px;
                font-size:0.85rem;
                font-weight:600;
                color:#295738;
                cursor:pointer;
                transition:all .2s ease;
            " onmouseover="this.style.background='#f4f0dc'" onmouseout="this.style.background='#fef8f3'">
                Show More Reports (<?= count($reports) - 5 ?>)
</button>
</div>
        <?php endif; ?>

        <?php endif; ?>

    </div>

    </div>

    <script>

        var showAllReports = false;

        function toggleReports() {

            showAllReports = !showAllReports;

            var items = document.querySelectorAll('.report-item');
            var btn = document.getElementById('showMoreBtn');
            var total = items.length;

            items.forEach(function (item, idx) {
                if (idx >= 5) {
                    item.style.display = showAllReports ? 'flex' : 'none';
                }
            });

            if (btn) {
                var hidden = total - 5;
                btn.innerHTML = showAllReports
                ? 'Show Less Reports'
                : 'Show More Reports (' + hidden + ')';
            }
        }

        const reportSearch =

        document.getElementById(
        'reportSearch'
        );

        reportSearch.addEventListener(

        'keyup',

        function () {

            var value = this.value.toLowerCase();

            document
            .querySelectorAll('.report-item')
            .forEach(function (item) {

                var name = item.dataset.name;

                var idx = Array.from(item.parentNode.children).indexOf(item);

                if (idx >= 5 && !showAllReports) {
                    return;
                }

                item.style.display =

                name.includes(value)

                ?

                'flex'

                :

                'none';
            });
        }
        );

    </script>
