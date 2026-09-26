<?php
/**
 * Common Admin Layout Header
 * Bootstrap 5.3 + Responsive Navigation + Icons
 */

declare(strict_types=1);

if (!isset($page_title)) {
    $page_title = 'Student & Semester Fee Management';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($page_title) ?> - EduFee System</title>
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    
    <!-- Iframe Auth Token Synchronizer -->
    <script>
    (function() {
        try {
            const urlParams = new URLSearchParams(window.location.search);
            let token = urlParams.get('auth_token') || localStorage.getItem('edufee_auth_token') || sessionStorage.getItem('edufee_auth_token');
            if (token && token.length > 10) {
                localStorage.setItem('edufee_auth_token', token);
                sessionStorage.setItem('edufee_auth_token', token);

                // Auto-append auth_token to internal links and forms
                document.addEventListener('DOMContentLoaded', function() {
                    document.querySelectorAll('a[href]').forEach(function(link) {
                        const href = link.getAttribute('href');
                        if (href && !href.startsWith('http') && !href.startsWith('#') && !href.startsWith('javascript:') && !href.includes('logout.php')) {
                            try {
                                const u = new URL(href, window.location.href);
                                if (!u.searchParams.has('auth_token')) {
                                    u.searchParams.set('auth_token', token);
                                    link.setAttribute('href', u.pathname.replace(/^\//, '') + u.search + u.hash);
                                }
                            } catch(e) {}
                        }
                    });

                    document.querySelectorAll('form').forEach(function(form) {
                        if (!form.querySelector('input[name="auth_token"]')) {
                            const inp = document.createElement('input');
                            inp.type = 'hidden';
                            inp.name = 'auth_token';
                            inp.value = token;
                            form.appendChild(inp);
                        }
                    });
                });

                // Attach to window.fetch for API endpoints
                const origFetch = window.fetch;
                window.fetch = function(url, init) {
                    init = init || {};
                    init.headers = init.headers || {};
                    if (init.headers instanceof Headers) {
                        if (!init.headers.has('X-Auth-Token')) init.headers.append('X-Auth-Token', token);
                    } else if (Array.isArray(init.headers)) {
                        init.headers.push(['X-Auth-Token', token]);
                    } else {
                        init.headers['X-Auth-Token'] = token;
                    }
                    if (typeof url === 'string' && (url.startsWith('api/') || url.includes('.php'))) {
                        const sep = url.includes('?') ? '&' : '?';
                        if (!url.includes('auth_token=')) {
                            url += sep + 'auth_token=' + encodeURIComponent(token);
                        }
                    }
                    return origFetch(url, init);
                };
            }
        } catch(e) {}
    })();
    </script>
    
    <style>
        :root {
            --edu-primary: #1e3a8a;
            --edu-primary-dark: #0f172a;
            --edu-accent: #2563eb;
            --edu-bg: #f8fafc;
            --edu-card-bg: #ffffff;
            --edu-text: #0f172a;
            --edu-text-muted: #64748b;
            --edu-border: #e2e8f0;
        }

        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            background-color: var(--edu-bg);
            color: var(--edu-text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .mono {
            font-family: 'JetBrains Mono', monospace;
        }

        .sidebar-wrapper {
            width: 260px;
            background-color: #0f172a;
            min-height: 100vh;
            color: #e2e8f0;
            transition: all 0.3s ease;
        }

        .sidebar-brand {
            padding: 1.25rem 1.5rem;
            font-weight: 800;
            font-size: 1.15rem;
            color: #ffffff;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
        }

        .sidebar-nav {
            list-style: none;
            padding: 1rem 0.75rem;
            margin: 0;
        }

        .sidebar-nav .nav-item {
            margin-bottom: 0.25rem;
        }

        .sidebar-nav .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.7rem 1rem;
            color: #94a3b8;
            font-weight: 500;
            font-size: 0.925rem;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .sidebar-nav .nav-link:hover {
            color: #ffffff;
            background-color: rgba(255, 255, 255, 0.08);
        }

        .sidebar-nav .nav-link.active {
            color: #ffffff;
            background-color: var(--edu-accent);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.35);
        }

        .sidebar-nav .nav-link i {
            font-size: 1.1rem;
        }

        .main-content {
            flex: 1;
            padding: 1.75rem 2rem;
            background-color: var(--edu-bg);
            overflow-y: auto;
        }

        .top-navbar {
            background-color: #ffffff;
            border-bottom: 1px solid var(--edu-border);
            padding: 0.75rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-custom {
            background: #ffffff;
            border: 1px solid var(--edu-border);
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .card-stat {
            border-left: 4px solid var(--edu-accent);
        }

        .table-custom thead th {
            background-color: #f1f5f9;
            color: #475569;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            border-bottom: 2px solid var(--edu-border);
            padding: 0.75rem 1rem;
        }

        .table-custom tbody td {
            vertical-align: middle;
            padding: 0.85rem 1rem;
            border-color: #f1f5f9;
            font-size: 0.925rem;
        }

        @media (max-width: 991.98px) {
            .sidebar-wrapper {
                position: fixed;
                z-index: 1040;
                transform: translateX(-100%);
            }
            .sidebar-wrapper.show {
                transform: translateX(0);
            }
            .main-content {
                padding: 1.25rem 1rem;
            }
        }

        @media print {
            .sidebar-wrapper, .top-navbar, .btn-no-print, .no-print {
                display: none !important;
            }
            body {
                background: #ffffff !important;
            }
            .main-content {
                padding: 0 !important;
            }
        }
    </style>
</head>
<body>

<div class="d-flex min-vh-100">
    <!-- Sidebar -->
    <aside class="sidebar-wrapper" id="sidebarNav">
        <a href="index.php" class="sidebar-brand">
            <span class="d-inline-flex align-items-center justify-content-center bg-primary text-white rounded-3 p-2" style="width: 38px; height: 38px;">
                <i class="bi bi-mortarboard-fill"></i>
            </span>
            <div>
                <div>EduFee System</div>
                <small class="text-secondary fw-normal" style="font-size: 0.7rem;">Fee Management Core</small>
            </div>
        </a>

        <ul class="sidebar-nav">
            <li class="nav-item">
                <a href="index.php" class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : '' ?>">
                    <i class="bi bi-grid-1x2-fill"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="students.php" class="nav-link <?= (in_array(basename($_SERVER['PHP_SELF']), ['students.php', 'student_add.php', 'student_edit.php', 'student_view.php'])) ? 'active' : '' ?>">
                    <i class="bi bi-people-fill"></i>
                    <span>Students</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="courses.php" class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'courses.php') ? 'active' : '' ?>">
                    <i class="bi bi-book-half"></i>
                    <span>Courses</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="semester_fees.php" class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'semester_fees.php') ? 'active' : '' ?>">
                    <i class="bi bi-currency-rupee"></i>
                    <span>Semester Fees</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="payments.php" class="nav-link <?= (in_array(basename($_SERVER['PHP_SELF']), ['payments.php', 'payment_add.php'])) ? 'active' : '' ?>">
                    <i class="bi bi-wallet2"></i>
                    <span>Fee Payments</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="reports.php" class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'reports.php') ? 'active' : '' ?>">
                    <i class="bi bi-bar-chart-line-fill"></i>
                    <span>Reports</span>
                </a>
            </li>

            <li class="nav-item mt-4 pt-3 border-top border-secondary-subtle">
                <span class="text-uppercase text-secondary px-3" style="font-size: 0.65rem; font-weight: 700; letter-spacing: 0.08em;">System Info</span>
            </li>
            <li class="nav-item mt-1">
                <a href="test_app.php" class="nav-link text-info-emphasis">
                    <i class="bi bi-shield-check"></i>
                    <span>Self-Test Suite</span>
                </a>
            </li>
        </ul>
    </aside>

    <!-- Main Container -->
    <div class="d-flex flex-column flex-grow-1 w-100">
        <!-- Top Navbar -->
        <header class="top-navbar">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-sm btn-outline-secondary d-lg-none" type="button" onclick="document.getElementById('sidebarNav').classList.toggle('show')">
                    <i class="bi bi-list"></i>
                </button>
                <h5 class="mb-0 fw-bold text-dark"><?= sanitize($page_title) ?></h5>
            </div>

            <div class="d-flex align-items-center gap-3">
                <div class="d-none d-md-flex flex-column text-end">
                    <span class="fw-semibold text-dark fs-6"><?= sanitize($_SESSION['admin_name'] ?? 'Admin') ?></span>
                    <span class="text-muted small">@<?= sanitize($_SESSION['admin_user'] ?? 'admin') ?></span>
                </div>
                <div class="dropdown">
                    <button class="btn btn-light rounded-circle border p-2" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-fill text-primary"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                        <li><h6 class="dropdown-header">Signed in as <?= sanitize($_SESSION['admin_user'] ?? 'admin') ?></h6></li>
                        <li><a class="dropdown-item" href="reports.php"><i class="bi bi-file-earmark-text me-2"></i>Fee Reports</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </header>

        <!-- Dynamic Content Body -->
        <main class="main-content">
            <?php if (!empty($_SESSION['flash_success'])): ?>
                <div class="alert alert-success alert-dismissible fade show d-flex align-items-center" role="alert">
                    <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                    <div><?= sanitize($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($_SESSION['flash_error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                    <div><?= sanitize($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
