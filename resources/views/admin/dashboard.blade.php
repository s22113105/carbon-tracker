@extends('layouts.dashboard')

@section('title', '管理員儀表板')

@section('sidebar-title', '管理員功能')

@section('sidebar-menu')
    <li class="nav-item">
        <a class="nav-link active" href="{{ route('admin.dashboard') }}">
            <i class="fas fa-tachometer-alt me-2"></i>總覽儀表板
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('admin.users') }}">
            <i class="fas fa-users me-2"></i>使用者管理
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('admin.statistics') }}">
            <i class="fas fa-chart-bar me-2"></i>全公司統計
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('admin.devices') }}">
            <i class="fas fa-cog me-2"></i>設備統計
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('admin.geofence') }}">
            <i class="fas fa-map-marked-alt me-2"></i>地理圍欄設定
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('admin.settings') }}">
            <i class="fas fa-cog me-2"></i>系統設定
        </a>
    </li>
@endsection

@section('content')
<div class="row">
    <div class="col-md-12">
        <h1>管理員儀表板</h1>
        <div class="alert alert-success">
            歡迎，管理員 {{ Auth::user()->name }}！
        </div>
    </div>
</div>
@endsection