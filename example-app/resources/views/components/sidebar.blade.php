<aside class="sidebar">

    <div class="sidebar-brand">
        <div class="brand-icon">S</div>

        <div class="brand-text">
            <h2>STOCKRAGE</h2>
            <p>POS & Inventory</p>
        </div>
    </div>

    <nav class="sidebar-nav">

        <p class="nav-label">MAIN MENU</p>

        <a href="{{ url('/dashboard') }}" class="nav-link active">
            Dashboard
        </a>

        <a href="#" class="nav-link">
            Point of Sale
        </a>

        <a href="#" class="nav-link">
            Products & Inventory
        </a>

        <a href="#" class="nav-link">
            Sales History
        </a>

        <a href="#" class="nav-link">
            Stock History
        </a>

        <a href="#" class="nav-link">
            Reports & Analytics
        </a>

        <p class="nav-label settings-label">PREFERENCES</p>

        <a href="#" class="nav-link">
            System Settings
        </a>

    </nav>

    <div class="sidebar-bottom">

        <form action="{{ route('theme.toggle') }}" method="POST">
            @csrf

            <button type="submit" class="theme-toggle">
                Switch Theme
            </button>
        </form>

        <div class="user-info">
            <div class="user-avatar">A</div>

            <div>
                <strong>Administrator</strong>
                <p>System Admin</p>
            </div>
        </div>

    </div>

</aside>