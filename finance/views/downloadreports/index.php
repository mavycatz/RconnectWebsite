<?php header('Cache-Control: no-store, no-cache, must-revalidate'); header('Pragma: no-cache');
require_once __DIR__ . '/../layout/navbarfinance.php'; ?>

<div class="main-content" style="background:#eceed9;">

    <div style="
        background:#fef8f3;
        border:1px solid #aaa8b1;
        border-radius:24px;
        padding:24px;
    ">

        <?php require_once __DIR__ . '/../layout/_headernav.php'; ?>
        <?php require __DIR__ . '/_header.php'; ?>

        <div class="container-fluid mt-4">
            <div class="row g-4 align-items-stretch">
                <div class="col-12 d-flex">
                    <div class="w-100 d-flex flex-column">
                        <div style="font-family:'Poppins', sans-serif;">
                            <?php require __DIR__ . '/_content.php'; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

</div>