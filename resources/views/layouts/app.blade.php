<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Laravel') }}</title>
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])

    {{-- 引用外部CSS檔案 --}}
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/navbar.css') }}">
</head>
<body>
    <div id="app">
        <nav class="navbar navbar-expand-md modern-navbar" id="mainNavbar">
            <div class="container">
                <a class="navbar-brand" href="{{ url('/') }}">
                    碳排放追蹤系統
                </a>
                
                {{-- 手機版切換按鈕 --}}
                <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" 
                        style="box-shadow: none; background: linear-gradient(45deg, #667eea, #764ba2); border-radius: 10px;">
                    <span style="background-image: none; width: 20px; height: 2px; background: white; display: block; margin: 4px 0;"></span>
                    <span style="background-image: none; width: 20px; height: 2px; background: white; display: block; margin: 4px 0;"></span>
                    <span style="background-image: none; width: 20px; height: 2px; background: white; display: block; margin: 4px 0;"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarNav">
                    <div class="navbar-nav ms-auto">
                        @guest
                            @if (Route::has('login'))
                                <a class="nav-link modern-nav-link" href="{{ route('login') }}">登入</a>
                            @endif
                            @if (Route::has('register'))
                                <a class="nav-link register-btn" href="{{ route('register') }}">註冊</a>
                            @endif
                        @else
                            <div class="nav-item dropdown user-dropdown">
                                <a class="nav-link dropdown-toggle user-dropdown-toggle" href="#" role="button" 
                                   data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-user-circle me-1"></i>
                                    {{ Auth::user()->name }}
                                </a>
                                <ul class="dropdown-menu">
                                    <li>
                                        <a class="dropdown-item" href="{{ route('home') }}">
                                            <i class="fas fa-tachometer-alt me-2"></i>儀表板
                                        </a>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item" href="{{ route('logout') }}"
                                           onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                            <i class="fas fa-sign-out-alt me-2"></i>登出
                                        </a>
                                    </li>
                                </ul>
                                <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                                    @csrf
                                </form>
                            </div>
                        @endguest
                    </div>
                </div>
            </div>
        </nav>

        <main class="py-4">
            @yield('content')
        </main>
    </div>

    {{-- Font Awesome for icons --}}
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    {{-- 滾動效果JavaScript --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const navbar = document.getElementById('mainNavbar');
            
            // 滾動時改變navbar樣式
            window.addEventListener('scroll', function() {
                if (window.scrollY > 50) {
                    navbar.classList.add('scrolled');
                } else {
                    navbar.classList.remove('scrolled');
                }
            });

            // 平滑滾動到首頁
            document.querySelector('.navbar-brand').addEventListener('click', function(e) {
                if (window.location.pathname === '/') {
                    e.preventDefault();
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                }
            });
        });
    </script>
</body>
</html>