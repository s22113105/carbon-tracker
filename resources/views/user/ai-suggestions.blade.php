@extends('layouts.dashboard')

@section('title', 'AI 減碳建議')

@section('sidebar-title', '個人功能')

@section('sidebar-menu')
    <li class="nav-item">
        <a class="nav-link" href="{{ route('user.dashboard') }}">
            <i class="fas fa-home me-2"></i>個人儀表板
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('user.charts') }}">
            <i class="fas fa-chart-bar me-2"></i>每月/每日通勤碳排統計圖表
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('user.map') }}">
            <i class="fas fa-map-marked-alt me-2"></i>地圖顯示通勤路線
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="#">
            <i class="fas fa-chart-pie me-2"></i>交通工具使用分布
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('user.attendance') }}">
            <i class="fas fa-clock me-2"></i>打卡紀錄
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('user.realtime') }}">
            <i class="fas fa-sync-alt me-2"></i>即時儀表板
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link active" href="{{ route('user.ai-suggestions') }}">
            <i class="fas fa-lightbulb me-2"></i>AI 減碳建議
        </a>
    </li>
@endsection

@section('content')
<div class="row mb-4">
    <div class="col-md-12">
        <h1>AI 減碳建議</h1>
        <p class="text-muted">透過 AI 分析您的通勤模式，提供個人化的減碳建議</p>
    </div>
</div>

@livewire('user.ai-suggestions')
@endsection