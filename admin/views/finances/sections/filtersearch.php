


<form id="financeFilterForm">

    <div style="
    margin-top:1rem;
    margin-bottom:1.5rem;
    padding:16px;
    background:transparent;
    border:2px solid #e5e0a4;
    border-radius:14px;
">

        <div class="d-flex flex-wrap align-items-end gap-3">

            <div style="flex:1; min-width:250px;">

                <label style="
            font-weight:600;
            color:#295738;
            font-size:.9rem;
            display:block;
            margin-bottom:4px;
        ">
            Search
                </label>

                <input type="text" 
               name="search"
               placeholder="Search passenger, payment..."
               style="
                   width:100%;
                   padding:8px 12px;
                   background:#faf9e5;
                   border:2px solid #e5e0a4;
                   border-radius:10px;
                   outline:none;
               ">

            </div>

            <div>

                <label style="
            font-weight:600;
            color:#295738;
            font-size:.9rem;
            display:block;
            margin-bottom:4px;
        ">
            Category
                </label>

                <select name="category"
            style="
                padding:8px 10px;
                background:#faf9e5;
                border:2px solid #e5e0a4;
                border-radius:10px;
                outline:none;
                color:#295738;
                font-weight:500;
                min-width:200px;
            ">

                    <option value="">All Categories</option>
                    <option value="Cash">Cash</option>
                    <option value="GCash">GCash</option>

                </select>

            </div>

            <div>

                <label style="
            font-weight:600;
            color:#295738;
            font-size:.9rem;
            display:block;
            margin-bottom:4px;
        ">
            From
                </label>

                <input type="date"
               name="from"
               style="
                    padding:8px 10px;
                    background:#faf9e5;
                    border:2px solid #e5e0a4;
                    border-radius:10px;
                    outline:none;
               ">

            </div>

            <div>

                <label style="
            font-weight:600;
            color:#295738;
            font-size:.9rem;
            display:block;
            margin-bottom:4px;
        ">
            To
                </label>

                <input type="date"
               name="to"
               style="
                    padding:8px 10px;
                    background:#faf9e5;
                    border:2px solid #e5e0a4;
                    border-radius:10px;
                    outline:none;
               ">

            </div>

            <div>

                <button type="submit"
            style="
                background:#096d11;
                color:white;
                padding:9px 18px;
                border:none;
                border-radius:10px;
                font-weight:600;
            ">

            Apply

                </button>

            </div>

        </div>

    </div>

</form>
