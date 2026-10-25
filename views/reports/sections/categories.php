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


$data =

$controller->all();


$token = $_GET['token'] ?? '';
$baseReportsUrl = $token ? '/admin/reports/' . urlencode($token) : '#';

$generatedReports =

$data['generated_reports']

??

[];

$aiReports =

$data['ai_trip_operations_reports']

??

[];

$tripReports =

$data['trip_operations_reports']

??

[];


$recentReports = [];


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

            $recentReports[] = [

            'type' => 'generated',

            'category' =>

            $folder,

            'title' =>

            pathinfo(
            $file,
            PATHINFO_FILENAME
            ),

            'date' =>

            filemtime($file)
            ];
        }
    }
}


$db = Database::get();


$aiStmt = $db->query("

    SELECT
        report_title,
        report_category,
        generated_at

    FROM ai_trip_operations_reports

    WHERE archived = 0

");

$aiRows = $aiStmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($aiRows as $row) {

    $recentReports[] = [

    'type' => 'ai',

    'category' =>

    $row['report_category'],

    'title' =>

    $row['report_title'],

    'date' =>

    strtotime(
    $row['created_at']
    )
    ];
}


$tripStmt = $db->query("

    SELECT
        trip_group_code,
        report_category,
        created_at

    FROM trip_operations_reports

    WHERE archived = 0

");

$tripRows = $tripStmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($tripRows as $row) {

    $recentReports[] = [

    'type' => 'trip',

    'category' =>

    $row['report_category'],

    'title' =>

    $row['report_title'],

    'date' =>

    strtotime(
    $row['created_at']
    )
    ];
}


usort(

$recentReports,

function($a, $b) {

    return $b['date']

    <=>

    $a['date'];
}
);


$recentReports = array_slice(
$recentReports,
0,
6
);


function renderCategoryLogo(string $category): string
{
    $formatted = ucwords(
    str_replace(
    '_',
    ' ',
    $category
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

    $categoryLower = strtolower($category);


    if (str_contains($categoryLower, 'driver')) {

        $background = '#dbeafe';
        $text = '#1e3a8a';
        $border = '#bfdbfe';
    }


    elseif (str_contains($categoryLower, 'passenger')) {

        $background = '#dcfce7';
        $text = '#166534';
        $border = '#bbf7d0';
    }


    elseif (str_contains($categoryLower, 'trip')) {

        $background = '#fef3c7';
        $text = '#92400e';
        $border = '#fde68a';
    }


    elseif (str_contains($categoryLower, 'ai')) {

        $background = '#ede9fe';
        $text = '#5b21b6';
        $border = '#ddd6fe';
    }


    elseif (str_contains($categoryLower, 'remittance')) {

        $background = '#fee2e2';
        $text = '#991b1b';
        $border = '#fecaca';
    }


    elseif (str_contains($categoryLower, 'inspector')) {

        $background = '#e0f2fe';
        $text = '#075985';
        $border = '#bae6fd';
    }

    return '

    <div style="
        width:54px;
        height:54px;
        border-radius:16px;
        background:'.$background.';
        border:1px solid '.$border.';
        display:flex;
        align-items:center;
        justify-content:center;
        color:'.$text.';
        font-size:.95rem;
        font-weight:800;
        letter-spacing:.7px;
        flex-shrink:0;
        box-shadow:
            0 2px 4px rgba(0,0,0,0.04);
    ">

        '.$initials.'

    </div>
    ';
}

?>

<div class="report-card h-100 d-flex flex-column">


    <div style="
        font-size:1.45rem;
        font-weight:700;
        color:#2e4836;
        margin-bottom:18px;
    ">

        Report Categories

    </div>


    <div style="
        flex:1;
        background:#ffffff;
        border:1px solid #dfe7d1;
        border-radius:14px;
        padding:20px;
        overflow:auto;
    ">

        <input type="text" id="categorySearch" placeholder="Search categories..." style="
        width:100%;
        padding:10px 14px;
        border:1px solid #dfe7d1;
        border-radius:10px;
        background:#fff;
        outline:none;
        font-size:.85rem;
        margin-bottom:18px;
        box-sizing:border-box;
    ">


        <a
    href="<?= $baseReportsUrl ?>?recent=1"
    style="
        text-decoration:none;
        display:block;
        margin-bottom:32px;
    "
>

<div style="
        background:#fff;
        border:1px solid #e6ebe0;
        border-radius:16px;
        padding:18px;
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:16px;
        transition:.18s ease;
    ">


    <div style="
            display:flex;
            align-items:center;
            gap:16px;
        ">


        <div style="
                width:58px;
                height:58px;
                border-radius:16px;
                background:#f9f4da;
                border:1px solid #dfe7d1;
                display:flex;
                align-items:center;
                justify-content:center;
                color:#2e4836;
                font-size:1rem;
                font-weight:800;
                letter-spacing:.7px;
                flex-shrink:0;
            ">

                RA

        </div>


        <div>

            <div style="
                    font-size:.98rem;
                    font-weight:700;
                    color:#2e4836;
                    margin-bottom:4px;
                ">

                    Recently Added Reports

            </div>

            <div style="
                    font-size:.76rem;
                    color:#7a7a7a;
                    line-height:1.5;
                ">

                    View all latest generated,
                    AI, and trip operation reports

            </div>

        </div>

    </div>


    <div style="
            color:#9ca3af;
            font-size:.85rem;
            flex-shrink:0;
        ">

        <i class="fa-solid fa-chevron-right"></i>

    </div>

</div>

</a>

<div id="allCategoryItems" style="display:block;">


    <div style="margin-bottom:32px;">

        <div style="
                font-size:.78rem;
                font-weight:700;
                color:#7b7b7b;
                text-transform:uppercase;
                letter-spacing:1px;
                margin-bottom:14px;
            ">

                Generated Reports

        </div>

        <div style="
                display:grid;
                grid-template-columns:
                    repeat(auto-fill,minmax(280px,1fr));
                gap:14px;
            ">

                <?php foreach ($generatedReports as $category): ?>

                <a class="category-item"
                    data-category="<?= strtolower($category); ?>"
                    href="<?= $baseReportsUrl ?>?type=generated&category=<?= urlencode($category); ?>"
                    style="
                        text-decoration:none;
                        background:#fff;
                        border:1px solid #e6ebe0;
                        border-radius:14px;
                        padding:16px;
                        display:flex;
                        align-items:center;
                        gap:16px;
                    "
                >

                    <?= renderCategoryLogo($category); ?>

                    <div>

                        <div style="
                            font-size:.97rem;
                            font-weight:700;
                            color:#2e4836;
                            margin-bottom:5px;
                        ">

                            <?= ucwords(
str_replace(
'_',
' ',
$category
)
); ?>

                        </div>

                        <div style="
                            font-size:.77rem;
                            color:#7a7a7a;
                        ">

                            Generated report archive

                        </div>

                        </div>

                        </a>

                <?php endforeach; ?>

            </div>

            </div>


            <div style="margin-bottom:32px;">

                <div style="
                font-size:.78rem;
                font-weight:700;
                color:#7b7b7b;
                text-transform:uppercase;
                letter-spacing:1px;
                margin-bottom:14px;
            ">

                AI Trip Operations Reports

                </div>

                <div style="
                display:grid;
                grid-template-columns:
                    repeat(auto-fill,minmax(280px,1fr));
                gap:14px;
            ">

                <?php foreach ($aiReports as $category): ?>

                <a class="category-item"
                    data-category="<?= strtolower($category); ?>"
                href="<?= $baseReportsUrl ?>?type=ai&category=<?= urlencode($category); ?>"
                    style="
                        text-decoration:none;
                        background:#fff;
                        border:1px solid #e6ebe0;
                        border-radius:14px;
                        padding:16px;
                        display:flex;
                        align-items:center;
                        gap:16px;
                    "
                >

                    <?= renderCategoryLogo($category); ?>

                    <div>

                        <div style="
                            font-size:.97rem;
                            font-weight:700;
                            color:#2e4836;
                            margin-bottom:5px;
                        ">

                            <?= ucwords(
str_replace(
'_',
' ',
$category
)
); ?>

                        </div>

                        <div style="
                            font-size:.77rem;
                            color:#7a7a7a;
                        ">

                            AI transport analytics

                        </div>

                        </div>

                        </a>

                <?php endforeach; ?>

            </div>

            </div>


            <div>

                <div style="
                font-size:.78rem;
                font-weight:700;
                color:#7b7b7b;
                text-transform:uppercase;
                letter-spacing:1px;
                margin-bottom:14px;
            ">

                Trip Operations Reports

                </div>

                <div style="
                display:grid;
                grid-template-columns:
                    repeat(auto-fill,minmax(280px,1fr));
                gap:14px;
            ">

                <?php foreach ($tripReports as $category): ?>

                <a class="category-item"
                    data-category="<?= strtolower($category); ?>"
                    href="<?= $baseReportsUrl ?>?type=trip&category=<?= urlencode($category); ?>"
                    style="
                        text-decoration:none;
                        background:#fff;
                        border:1px solid #e6ebe0;
                        border-radius:14px;
                        padding:16px;
                        display:flex;
                        align-items:center;
                        gap:16px;
                    "
                >

                    <?= renderCategoryLogo($category); ?>

                    <div>

                        <div style="
                            font-size:.97rem;
                            font-weight:700;
                            color:#2e4836;
                            margin-bottom:5px;
                        ">

                            <?= ucwords(
str_replace(
'_',
' ',
$category
)
); ?>

                        </div>

                        <div style="
                            font-size:.77rem;
                            color:#7a7a7a;
                        ">

                            Transport monitoring reports

                        </div>

                        </div>

                        </a>

                <?php endforeach; ?>

            </div>

            </div>


        <?php $financeCategories = [

'comprehensive',
'revenue_analysis',
'operating_expenses',
'bus_maintenance',
'fleet_performance',
'route_performance',
'driver_performance',
'financial_summary',
'risk_assessment',
'general'
]; ?>

        <div style="margin-top:32px;">

            <div style="
                font-size:.78rem;
                font-weight:700;
                color:#7b7b7b;
                text-transform:uppercase;
                letter-spacing:1px;
                margin-bottom:14px;
            ">

                Finance Reports

            </div>

            <div style="
                display:grid;
                grid-template-columns:
                    repeat(auto-fill,minmax(280px,1fr));
                gap:14px;
            ">

                <?php foreach ($financeCategories as $category): ?>

                <a class="category-item"
                    data-category="<?= strtolower($category); ?>"
                    href="<?= $baseReportsUrl ?>?type=finance&category=<?= urlencode($category); ?>"
                    style="
                        text-decoration:none;
                        background:#fff;
                        border:1px solid #e6ebe0;
                        border-radius:14px;
                        padding:16px;
                        display:flex;
                        align-items:center;
                        gap:16px;
                    "
                >

                    <?= renderCategoryLogo($category); ?>

                    <div>

                        <div style="
                            font-size:.97rem;
                            font-weight:700;
                            color:#2e4836;
                            margin-bottom:5px;
                        ">

                            <?= ucwords(
str_replace(
'_',
' ',
$category
)
); ?>

                        </div>

                        <div style="
                            font-size:.77rem;
                            color:#7a7a7a;
                        ">

                            Financial analytics report

                        </div>

                        </div>

                        </a>

                <?php endforeach; ?>

            </div>

            </div>

            </div>

            <script>

                var categoryShowAll = false;

                function toggleCategoryItems() {
                    categoryShowAll = !categoryShowAll;
                    var items = document.querySelectorAll('.category-item');
                    var btn = document.getElementById('categoryShowBtn');
                    items.forEach(function (item, idx) {
                        if (idx >= 5) {
                            item.style.display = categoryShowAll ? '' : 'none';
                        }
                    });
                    if (btn) {
                        btn.innerHTML = categoryShowAll ? 'Show Less' : 'Show More Categories';
                    }
                }

                document.addEventListener('DOMContentLoaded', function () {
                    var items = document.querySelectorAll('.category-item');
                    items.forEach(function (item, idx) {
                        if (idx >= 5) {
                            item.style.display = 'none';
                        }
                    });
                    if (items.length <= 5) {
                        var wrap = document.getElementById('categoryShowMoreWrap');
                        if (wrap) wrap.style.display = 'none';
                    }

                    var searchInput = document.getElementById('categorySearch');
                    if (searchInput) {
                        searchInput.addEventListener('keyup', function () {
                            var val = this.value.toLowerCase().trim();
                            items.forEach(function (item, idx) {
                                var cat = (item.getAttribute('data-category') || '').toLowerCase();
                                var match = cat.indexOf(val) !== -1;
                                if (!val) {
                                    if (idx >= 5 && !categoryShowAll) {
                                        item.style.display = 'none';
                                    } else {
                                        item.style.display = '';
                                    }
                                } else {
                                    item.style.display = match ? '' : 'none';
                                }
                            });
                        });
                    }
                });

            </script>

            <div style="text-align:center;margin-top:16px;" id="categoryShowMoreWrap">
                <button id="categoryShowBtn" onclick="toggleCategoryItems()" style="
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
        Show More Categories
                </button>
            </div>

            </div>

            </div>