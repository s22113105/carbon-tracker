<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <title>{{ config('app.name', 'Laravel') }} - @yield('title')</title>
    
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
    @livewireStyles
    
    <style>
        .sidebar {
            min-height: 100vh;
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
        }
        .sidebar .nav-link {
            color: #333;
            font-weight: 500;
        }
        .sidebar .nav-link:hover {
            color: #007bff;
        }
        .sidebar .nav-link.active {
            color: #007bff;
            background-color: rgba(0, 123, 255, .1);
        }
        .main-content {
            margin-left: 0;
        }
        @media (min-width: 768px) {
            .main-content {
                margin-left: 250px;
            }
        }
    </style>
</head>
<body>
    <div id="app">
        <!-- 頂部導覽列 -->
        <nav class="navbar navbar-expand-md navbar-light bg-white shadow-sm fixed-top">
            <div class="container-fluid">
                <button class="navbar-toggler d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar">
                    <span class="navbar-toggler-icon"></span>
                </button>
                
                <a class="navbar-brand ms-2" href="{{ url('/') }}">
                    碳排放追蹤系統
                </a>
                
                <div class="navbar-nav ms-auto">
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            {{ Auth::user()->name }} 
                            <span class="badge bg-{{ Auth::user()->isAdmin() ? 'danger' : 'primary' }}">
                                {{ Auth::user()->isAdmin() ? '管理員' : '使用者' }}
                            </span>
                        </a>
                        
                        <div class="dropdown-menu dropdown-menu-end">
                            <a class="dropdown-item" href="#">個人設定</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="{{ route('logout') }}"
                               onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                登出
                            </a>
                            <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                                @csrf
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <!-- 側邊欄 -->
        <div class="collapse d-md-block sidebar bg-light position-fixed" id="sidebar" style="width: 250px; top: 56px; left: 0; z-index: 1000;">
            <div class="p-3">
                <h6 class="text-muted text-uppercase mb-3">@yield('sidebar-title', '主要功能')</h6>
                <ul class="nav flex-column">
                    @yield('sidebar-menu')
                </ul>
            </div>
        </div>

        <!-- 主要內容區域 -->
        <main class="main-content" style="margin-top: 56px;">
            <div class="container-fluid p-4">
                @yield('content')
            </div>
        </main>
    </div>
    
    @livewireScripts
</body>
</html>