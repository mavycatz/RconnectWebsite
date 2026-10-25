<style>
.bottom-nav-container{
    position: fixed;
    bottom: 0;
    left: 0;
    width: 100%;
    z-index: 9999;
}

.nav-bg{
    width:100%;
    height:80px; 
    display:block;
}

.bottom-nav{
    position:absolute;
    bottom:6px;
    width:100%;
    display:flex;
    justify-content:space-around;
    align-items:center;
}

.nav-item{
    display:flex;
    flex-direction:column;
    align-items:center;
    font-size:10px;
    color:#6c757d;
    text-decoration:none;
}

.nav-item i{
    font-size:18px;
    transition: all 0.3s ease;
}

.nav-item.active{
    color:#24513c;
}

.nav-item.active i{
    background:#c6d48c;
    color:#24513c;
    width:40px;
    height:40px;
    display:flex;
    align-items:center;
    justify-content:center;
    border-radius:50%;
    transform: translateY(-18px);
    box-shadow: 0 4px 10px rgba(0,0,0,0.12);
}

.nav-item.active span{
    transform: translateY(-10px);
    font-weight:600;
}
</style>

<div class="bottom-nav-container">

    <svg class="nav-bg" viewBox="0 0 400 80" preserveAspectRatio="none">
        <path 
            d="
                M0,30
                Q200,0 400,30
                L400,80
                L0,80
                Z
            "
            fill="#fef8f3"
            stroke="#c6d48c"
            stroke-width="2"
            stroke-linejoin="round"
        />
    </svg>

    <div class="bottom-nav">

        <a href="/passenger/home" class="nav-item <?php echo $route == "homepage" ? "active" : ""; ?>">
            <i class="bi bi-qr-code-scan"></i>
            <span>Scan</span>
        </a>

        <a href="/passenger/trips" class="nav-item <?php echo $route == "trips" ? "active" : ""; ?>">
            <i class="bi bi-clock-history"></i>
            <span>Trips</span>
        </a>

        <a href="/passenger/profile" class="nav-item <?php echo $route == "profile" ? "active" : ""; ?>">
            <i class="bi bi-person"></i>
            <span>Profile</span>
        </a>

    </div>

</div>
