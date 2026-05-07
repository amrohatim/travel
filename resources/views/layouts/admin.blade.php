<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --bg: #ffffff;
            --bg-soft: #f7f7f7;
            --text: #000000;
            --muted: #6b6b6b;
            --line: #d9d9d9;
        }
        body {
            margin: 0;
            background: var(--bg-soft);
            color: var(--text);
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
        }
        .admin-shell {
            min-height: 100vh;
        }
        .admin-sidebar {
            background: var(--bg);
            border-right: 1px solid var(--line);
            min-height: 100vh;
            width: 240px;
            position: fixed;
            left: 0;
            top: 0;
            padding: 24px 16px;
        }
        .brand {
            font-size: 0.95rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 20px;
        }
        .sidebar-link {
            display: block;
            padding: 10px 12px;
            border: 1px solid transparent;
            text-decoration: none;
            color: var(--text);
            font-weight: 600;
            font-size: 0.92rem;
            margin-bottom: 8px;
        }
        .sidebar-link:hover,
        .sidebar-link.active {
            border-color: var(--text);
            background: var(--bg-soft);
            color: var(--text);
        }
        .sidebar-bottom {
            position: absolute;
            bottom: 24px;
            left: 16px;
            right: 16px;
        }
        .admin-main {
            margin-left: 240px;
            padding: 28px;
        }
        .panel {
            background: var(--bg);
            border: 1px solid var(--line);
            padding: 20px;
        }
        .table {
            --bs-table-bg: #fff;
            --bs-table-striped-bg: #fafafa;
        }
        .form-control,
        .form-select {
            border-color: #b8b8b8;
            border-radius: 0;
        }
        .btn-mono {
            border: 1px solid #000;
            background: #000;
            color: #fff;
            border-radius: 0;
            font-weight: 600;
        }
        .btn-mono:hover {
            background: #fff;
            color: #000;
        }
        .btn-outline-mono {
            border: 1px solid #000;
            color: #000;
            border-radius: 0;
            background: #fff;
            font-weight: 600;
        }
        .btn-outline-mono:hover {
            background: #000;
            color: #fff;
        }
        @media (max-width: 991px) {
            .admin-sidebar {
                position: static;
                min-height: auto;
                width: 100%;
                border-right: 0;
                border-bottom: 1px solid var(--line);
            }
            .sidebar-bottom {
                position: static;
                margin-top: 16px;
            }
            .admin-main {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
<div class="admin-shell">
    <aside class="admin-sidebar">
        <div class="brand">Admin Panel</div>
        <nav>
            <a href="{{ route('admin.users.index') }}" class="sidebar-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">Users</a>
            <a href="{{ route('admin.flights.index') }}" class="sidebar-link {{ request()->routeIs('admin.flights.*') ? 'active' : '' }}">Flights</a>
            <a href="{{ route('admin.bookings.index') }}" class="sidebar-link {{ request()->routeIs('admin.bookings.*') ? 'active' : '' }}">Bookings</a>
            <a href="{{ route('admin.states.index') }}" class="sidebar-link {{ request()->routeIs('admin.states.*') ? 'active' : '' }}">States</a>
        </nav>

        <div class="sidebar-bottom d-grid gap-2">
            <a href="{{ route('profile.edit') }}" class="btn btn-outline-mono">Profile</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn btn-mono w-100" type="submit">Log Out</button>
            </form>
        </div>
    </aside>

    <main class="admin-main">
        @if (session('success'))
            <div class="alert alert-light border border-dark">{{ session('success') }}</div>
        @endif

        @if (session('error'))
            <div class="alert alert-light border border-dark">{{ session('error') }}</div>
        @endif

        @yield('content')
    </main>
</div>
</body>
</html>
