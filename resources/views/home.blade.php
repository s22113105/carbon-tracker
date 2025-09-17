@extends('layouts.app')

@section('content')
{{-- 移除main的padding，讓全版面設計正常顯示 --}}
<style>
    main.py-4 {
        padding-top: 0 !important;
        padding-bottom: 0 !important;
    }
    .navbar {
        position: relative;
        z-index: 1000;
    }
</style>

{{-- 引用Font Awesome和CSS --}}
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/homepage.css') }}">

<!-- Hero Section -->
<section class="hero">
    <div class="particles" id="particles"></div>
    <div class="container">
        <div class="hero-content">
            <div class="hero-text">
                <h1>碳排放追蹤系統</h1>
                <p>透過智慧裝置自動追蹤您的通勤碳足跡，為地球環保盡一份心力</p>
                <div class="cta-buttons">
                    @guest
                        {{-- 訪客看到註冊和登入按鈕 --}}
                        <a href="{{ route('register') }}" class="btn btn-primary">立即註冊</a>
                        <a href="{{ route('login') }}" class="btn btn-outline">會員登入</a>
                    @else
                        {{-- 已登入用戶看到儀表板按鈕 --}}
                        <a href="{{ route('home') }}" class="btn btn-primary">進入儀表板</a>
                    @endguest
                    <a href="#features" class="btn btn-outline">了解更多</a>
                </div>
            </div>
            <div class="hero-visual">
                <div class="eco-system">
                    <div class="earth"></div>
                    <div class="orbit orbit-1">
                        <div class="satellite"></div>
                    </div>
                    <div class="orbit orbit-2">
                        <div class="satellite"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="features" id="features">
    <div class="container">
        <div class="section-title">
            <h2>系統特色</h2>
            <p>結合物聯網、AI 分析與網頁視覺化的完整解決方案</p>
        </div>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon smart">
                    <i class="fas fa-microchip"></i>
                </div>
                <h5>智慧裝置追蹤</h5>
                <p>ESP32 碳排放識別證，自動記錄 GPS 軌跡，省電設計，離線儲存</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon ai">
                    <i class="fas fa-brain"></i>
                </div>
                <h5>AI 智慧分析</h5>
                <p>自動判斷交通工具、計算碳排放量、提供個人化減碳建議</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon chart">
                    <i class="fas fa-chart-line"></i>
                </div>
                <h5>視覺化報表</h5>
                <p>即時統計圖表、通勤路線地圖、碳足跡趨勢分析</p>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="cta-section">
    <div class="container">
        <h3>開始您的環保之旅</h3>
        <p>加入我們，一起為永續發展努力</p>
        @guest
            {{-- 訪客看到註冊按鈕 --}}
            <a href="{{ route('register') }}" class="btn btn-primary">免費註冊</a>
        @else
            {{-- 已登入用戶看到返回儀表板按鈕 --}}
            <a href="{{ route('home') }}" class="btn btn-primary">返回儀表板</a>
        @endguest
    </div>
</section>

<script>
    // Create floating particles
    function createParticles() {
        const particlesContainer = document.getElementById('particles');
        if (!particlesContainer) return;
        
        const particleCount = 50;

        for (let i = 0; i < particleCount; i++) {
            const particle = document.createElement('div');
            particle.className = 'particle';
            particle.style.left = Math.random() * 100 + '%';
            particle.style.width = particle.style.height = Math.random() * 4 + 2 + 'px';
            particle.style.animationDelay = Math.random() * 20 + 's';
            particle.style.animationDuration = (Math.random() * 10 + 15) + 's';
            particlesContainer.appendChild(particle);
        }
    }

    // Intersection Observer for scroll animations
    function initScrollAnimations() {
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe feature cards
        document.querySelectorAll('.feature-card').forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(50px)';
            card.style.transition = `all 0.6s ease ${index * 0.2}s`;
            observer.observe(card);
        });
    }

    // Smooth scrolling for anchor links
    function initSmoothScroll() {
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    }

    // Initialize all animations and interactions
    document.addEventListener('DOMContentLoaded', function() {
        createParticles();
        initScrollAnimations();
        initSmoothScroll();
    });

    // Add mouse movement parallax effect
    document.addEventListener('mousemove', function(e) {
        const mouseX = e.clientX / window.innerWidth;
        const mouseY = e.clientY / window.innerHeight;
        
        const ecoSystem = document.querySelector('.eco-system');
        if (ecoSystem) {
            ecoSystem.style.transform = `translate(${mouseX * 20 - 10}px, ${mouseY * 20 - 10}px)`;
        }
    });
</script>
@endsection