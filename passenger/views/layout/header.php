
<div style="display:flex; justify-content:space-between; align-items:center; background:#fcf8ed; padding:15px 20px; border-bottom:1px solid #dee2e6; flex-wrap:nowrap;">
    <div style="display:flex; flex-direction:column; align-items:flex-start;">
        <div style="display:flex; align-items:center; gap:15px;">
            <img src="/assets/images/passenger/buslogo.png" alt="Logo Left" style="height:40px;">
            <h4 style="margin:0; font-family:'Poppins', sans-serif; font-weight:700; font-size:32px;">R-Connect</h4>
        </div>
        <span style="font-size:10px; color:#6c757d; font-family:'Poppins', sans-serif;">
            <?= htmlspecialchars(passenger_t('app.tagline', 'Transportation Management System for'), ENT_QUOTES, 'UTF-8') ?> <strong>RPMVFMPC</strong>
        </span>
    </div>

    <div>
        <img src="/assets/images/passenger/logoR.png" alt="Logo Right" style="height:60px;">
    </div>
</div>

<style>
@media (max-width: 576px) {
    div[style*="display:flex; justify-content:space-between"] {
        padding: 10px 15px;
    }

    div[style*="display:flex; flex-direction:column; align-items:flex-start;"] h4 {
        font-size:22px;
    }

    div[style*="display:flex; flex-direction:column; align-items:flex-start;"] span {
        font-size:16px;
    }

    div[style*="display:flex; align-items:center; gap:15px;"] img {
        height:50px;
    }

    div[style*="height:60px;"]:not([alt="Logo Left"]) {
        height:50px;
    }
}
</style>
