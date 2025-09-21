@extends('layouts.dashboard')

@section('title', '個人儀表板')

@section('sidebar-title', '個人功能')

@section('sidebar-menu')
    <li class="nav-item">
        <a class="nav-link active" href="{{ route('user.dashboard') }}">
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
        <a class="nav-link" href="{{ route('user.carbon.aiAnalyses') }}">
            <i class="fas fa-lightbulb me-2"></i>AI 碳排放分析
        </a>
    </li>
@endsection

@section('content')
<!-- 個人資訊卡片 -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <i class="fas fa-user-circle fa-3x"></i>
                    </div>
                    <div>
                        <h4 class="mb-1">{{ Auth::user()->name }}</h4>
                        <p class="mb-0">歡迎使用碳排放追蹤系統</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 碳排放統計 -->
<div class="row mb-4">
    <div class="col-md-12">
        <h3>碳排放統計</h3>
        @livewire('user.dashboard-stats')
    </div>
</div>

<!-- 近期打卡記錄 -->
<div class="row">
    <div class="col-md-12">
        <h3>近期打卡記錄</h3>
        @livewire('user.attendance-records')
    </div>
</div>
@endsection