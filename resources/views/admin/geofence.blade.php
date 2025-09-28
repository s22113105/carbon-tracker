@extends('layouts.dashboard')

@section('title', '地理圍欄設定')

@section('sidebar-title', '管理員功能')

@section('sidebar-menu')
    <li class="nav-item">
        <a class="nav-link" href="{{ route('admin.dashboard') }}">
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
        <a class="nav-link active" href="{{ route('admin.geofence') }}">
            <i class="fas fa-map-marked-alt me-2"></i>地理圍欄設定
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('admin.settings') }}">
            <i class="fas fa-cog me-2"></i>系統設定
        </a>
    </li>
@endsection

@section('styles')
<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
@endsection

@section('content')
<div class="row mb-4">
    <div class="col-md-12">
        <h1><i class="fas fa-map-marked-alt me-2"></i>地理圍欄設定</h1>
        <p class="text-muted">設定公司辦公室、停車場等地理圍欄，用於自動打卡和位置驗證</p>
    </div>
</div>

<!-- 警告訊息區域 -->
<div id="alertContainer"></div>

<!-- 操作按鈕 -->
<div class="row mb-4">
    <div class="col-md-12">
        <button class="btn btn-primary" id="addGeofenceBtn">
            <i class="fas fa-plus me-2"></i>新增地理圍欄
        </button>
        <button class="btn btn-info" id="refreshMapBtn">
            <i class="fas fa-sync me-2"></i>重新整理地圖
        </button>
        <button class="btn btn-success" id="getCurrentLocationBtn">
            <i class="fas fa-location-arrow me-2"></i>使用目前位置
        </button>
    </div>
</div>

<div class="row">
    <!-- 地圖顯示 -->
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-map me-2"></i>地理圍欄地圖</h5>
            </div>
            <div class="card-body p-0">
                <div id="geofenceMap" style="height: 600px; border-radius: 0 0 0.375rem 0.375rem;"></div>
            </div>
        </div>
    </div>

    <!-- 圍欄列表 -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>圍欄列表</h5>
                <span class="badge bg-info">{{ count($geofences) }} 個</span>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush" id="geofenceList">
                    @forelse($geofences as $geofence)
                    <div class="list-group-item" data-geofence-id="{{ $geofence->id }}">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <h6 class="mb-1">
                                    <i class="fas fa-{{ $geofence->type === 'office' ? 'building' : 
                                        ($geofence->type === 'parking' ? 'parking' : 
                                        ($geofence->type === 'restricted' ? 'ban' : 'map-pin')) }} me-2"></i>
                                    {{ $geofence->name }}
                                </h6>
                                <p class="mb-1 text-muted small">{{ $geofence->description ?? '無描述' }}</p>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-{{ $geofence->status_color }}">{{ $geofence->status_text }}</span>
                                    <span class="badge bg-secondary">{{ $geofence->type_name }}</span>
                                    <span class="badge bg-info">{{ $geofence->radius }}m</span>
                                </div>
                                <small class="text-muted">
                                    {{ $geofence->latitude }}, {{ $geofence->longitude }}
                                </small>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" 
                                        data-bs-toggle="dropdown">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="#" onclick="zoomToGeofence({{ $geofence->id }})">
                                        <i class="fas fa-search-plus me-2"></i>放大檢視</a></li>
                                    <li><a class="dropdown-item" href="#" onclick="editGeofence({{ $geofence->id }})">
                                        <i class="fas fa-edit me-2"></i>編輯</a></li>
                                    <li><a class="dropdown-item" href="#" onclick="toggleGeofence({{ $geofence->id }})">
                                        <i class="fas fa-{{ $geofence->is_active ? 'pause' : 'play' }} me-2"></i>
                                        {{ $geofence->is_active ? '停用' : '啟用' }}
                                    </a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="#" onclick="deleteGeofence({{ $geofence->id }})">
                                        <i class="fas fa-trash me-2"></i>刪除</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    @empty
                    <div class="text-center py-4">
                        <i class="fas fa-map-marked-alt fa-3x text-muted mb-3"></i>
                        <h6 class="text-muted">尚未設定任何地理圍欄</h6>
                        <p class="text-muted small">點擊上方按鈕開始建立</p>
                    </div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- 統計資訊 -->
        <div class="card mt-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i>圍欄統計</h6>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6">
                        <h5 class="text-success">{{ $geofences->where('is_active', true)->count() }}</h5>
                        <small class="text-muted">啟用中</small>
                    </div>
                    <div class="col-6">
                        <h5 class="text-secondary">{{ $geofences->where('is_active', false)->count() }}</h5>
                        <small class="text-muted">已停用</small>
                    </div>
                </div>
                <hr>
                <div class="row text-center">
                    @php
                        $types = $geofences->groupBy('type');
                    @endphp
                    <div class="col-6">
                        <h5 class="text-primary">{{ $types->get('office', collect())->count() }}</h5>
                        <small class="text-muted">辦公室</small>
                    </div>
                    <div class="col-6">
                        <h5 class="text-info">{{ $types->get('parking', collect())->count() }}</h5>
                        <small class="text-muted">停車場</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 新增/編輯圍欄 Modal -->
<div class="modal fade" id="geofenceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="geofenceModalTitle">
                    <i class="fas fa-plus me-2"></i>新增地理圍欄
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="geofenceForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">圍欄名稱 *</label>
                                <input type="text" class="form-control" id="geofenceName" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">圍欄類型 *</label>
                                <select class="form-select" id="geofenceType" required>
                                    <option value="office">辦公室</option>
                                    <option value="parking">停車場</option>
                                    <option value="restricted">限制區域</option>
                                    <option value="custom">自訂區域</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">描述</label>
                        <textarea class="form-control" id="geofenceDescription" rows="2" 
                                  placeholder="選填：圍欄用途說明"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">緯度 *</label>
                                <input type="number" class="form-control" id="geofenceLatitude" 
                                       step="0.00000001" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">經度 *</label>
                                <input type="number" class="form-control" id="geofenceLongitude" 
                                       step="0.00000001" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">半徑 (公尺) *</label>
                                <input type="number" class="form-control" id="geofenceRadius" 
                                       min="10" max="5000" value="100" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="geofenceActive" checked>
                        <label class="form-check-label" for="geofenceActive">
                            立即啟用此圍欄
                        </label>
                    </div>

                    <div class="alert alert-info mt-3">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>提示：</strong>您可以在地圖上點擊選擇位置，系統會自動填入座標
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>取消
                    </button>
                    <button type="submit" class="btn btn-primary" id="saveGeofenceBtn">
                        <i class="fas fa-save me-2"></i>儲存圍欄
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
let map;
let geofenceCircles = [];
let currentGeofences = @json($geofences);
let isEditMode = false;
let editingId = null;
let clickMarker = null;

// 初始化地圖
function initMap() {
    // 預設中心點（樹德科技大學）
    map = L.map('geofenceMap').setView([22.7632038, 120.3757461], 15);
    
    // 使用 OpenStreetMap
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    // 載入現有圍欄
    loadGeofences();
    
    // 地圖點擊事件
    map.on('click', function(e) {
        const lat = e.latlng.lat.toFixed(8);
        const lng = e.latlng.lng.toFixed(8);
        
        // 更新表單座標
        document.getElementById('geofenceLatitude').value = lat;
        document.getElementById('geofenceLongitude').value = lng;
        
        // 顯示臨時標記
        if (clickMarker) {
            map.removeLayer(clickMarker);
        }
        clickMarker = L.marker([lat, lng], {
            icon: L.icon({
                iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
                shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
                iconSize: [25, 41],
                iconAnchor: [12, 41],
                popupAnchor: [1, -34],
                shadowSize: [41, 41]
            })
        }).addTo(map);
        
        clickMarker.bindPopup(`選擇的座標：<br>緯度：${lat}<br>經度：${lng}`).openPopup();
    });
}

// 載入地理圍欄到地圖
function loadGeofences() {
    // 清除現有圍欄
    geofenceCircles.forEach(circle => {
        map.removeLayer(circle);
    });
    geofenceCircles = [];
    
    currentGeofences.forEach(geofence => {
        addGeofenceToMap(geofence);
    });
}

// 新增圍欄到地圖
function addGeofenceToMap(geofence) {
    const color = geofence.is_active ? 
        (geofence.type === 'office' ? 'blue' : 
         geofence.type === 'parking' ? 'green' : 
         geofence.type === 'restricted' ? 'red' : 'purple') : 'gray';
    
    const circle = L.circle([geofence.latitude, geofence.longitude], {
        color: color,
        fillColor: color,
        fillOpacity: 0.3,
        radius: geofence.radius,
        weight: 2
    }).addTo(map);
    
    circle.bindPopup(`
        <strong>${geofence.name}</strong><br>
        類型：${geofence.type_name}<br>
        半徑：${geofence.radius}公尺<br>
        狀態：${geofence.status_text}<br>
        <hr>
        <button class="btn btn-sm btn-primary" onclick="editGeofence(${geofence.id})">編輯</button>
        <button class="btn btn-sm btn-danger" onclick="deleteGeofence(${geofence.id})">刪除</button>
    `);
    
    geofenceCircles.push(circle);
}

// 事件處理器
document.addEventListener('DOMContentLoaded', function() {
    // 確保 DOM 完全載入後才初始化地圖
    setTimeout(function() {
        initMap();
    }, 100);
    
    // 新增圍欄按鈕
    document.getElementById('addGeofenceBtn').addEventListener('click', function() {
        openGeofenceModal();
    });
    
    // 重新整理地圖
    document.getElementById('refreshMapBtn').addEventListener('click', function() {
        location.reload();
    });
    
    // 取得目前位置
    document.getElementById('getCurrentLocationBtn').addEventListener('click', function() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                
                map.setView([lat, lng], 16);
                document.getElementById('geofenceLatitude').value = lat.toFixed(8);
                document.getElementById('geofenceLongitude').value = lng.toFixed(8);
                
                showAlert('success', '已取得您的目前位置');
            }, function() {
                showAlert('error', '無法取得目前位置，請手動選擇或輸入座標');
            });
        } else {
            showAlert('error', '瀏覽器不支援地理位置功能');
        }
    });
    
    // 表單提交
    document.getElementById('geofenceForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveGeofence();
    });
});

// 開啟圍欄設定視窗
function openGeofenceModal(geofenceData = null) {
    isEditMode = !!geofenceData;
    editingId = geofenceData ? geofenceData.id : null;
    
    const modal = new bootstrap.Modal(document.getElementById('geofenceModal'));
    const title = document.getElementById('geofenceModalTitle');
    const saveBtn = document.getElementById('saveGeofenceBtn');
    
    // 清除臨時標記
    if (clickMarker) {
        map.removeLayer(clickMarker);
        clickMarker = null;
    }
    
    if (isEditMode) {
        title.innerHTML = '<i class="fas fa-edit me-2"></i>編輯地理圍欄';
        saveBtn.innerHTML = '<i class="fas fa-save me-2"></i>更新圍欄';
        
        // 填入現有資料
        document.getElementById('geofenceName').value = geofenceData.name;
        document.getElementById('geofenceDescription').value = geofenceData.description || '';
        document.getElementById('geofenceLatitude').value = geofenceData.latitude;
        document.getElementById('geofenceLongitude').value = geofenceData.longitude;
        document.getElementById('geofenceRadius').value = geofenceData.radius;
        document.getElementById('geofenceType').value = geofenceData.type;
        document.getElementById('geofenceActive').checked = geofenceData.is_active;
    } else {
        title.innerHTML = '<i class="fas fa-plus me-2"></i>新增地理圍欄';
        saveBtn.innerHTML = '<i class="fas fa-save me-2"></i>儲存圍欄';
        
        // 重置表單
        document.getElementById('geofenceForm').reset();
        document.getElementById('geofenceActive').checked = true;
        document.getElementById('geofenceRadius').value = 100;
    }
    
    modal.show();
}

// 儲存圍欄
function saveGeofence() {
    const formData = {
        name: document.getElementById('geofenceName').value,
        description: document.getElementById('geofenceDescription').value,
        latitude: document.getElementById('geofenceLatitude').value,
        longitude: document.getElementById('geofenceLongitude').value,
        radius: document.getElementById('geofenceRadius').value,
        type: document.getElementById('geofenceType').value,
        is_active: document.getElementById('geofenceActive').checked ? 1 : 0
    };
    
    const url = isEditMode ? 
        `/admin/geofence/${editingId}` : 
        '{{ route("admin.geofence.store") }}';
    const method = isEditMode ? 'PUT' : 'POST';
    
    fetch(url, {
        method: method,
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', data.message);
            bootstrap.Modal.getInstance(document.getElementById('geofenceModal')).hide();
            
            // 重新載入頁面或更新列表
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            let errorMsg = data.message || '操作失敗';
            if (data.errors) {
                errorMsg = Object.values(data.errors).join('<br>');
            }
            showAlert('error', errorMsg);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('error', '操作失敗，請稍後再試');
    });
}

// 編輯圍欄
function editGeofence(id) {
    const geofence = currentGeofences.find(g => g.id === id);
    if (geofence) {
        openGeofenceModal(geofence);
        
        // 地圖定位到該圍欄
        map.setView([geofence.latitude, geofence.longitude], 16);
    }
}

// 刪除圍欄
function deleteGeofence(id) {
    const geofence = currentGeofences.find(g => g.id === id);
    if (!geofence) return;
    
    if (confirm(`確定要刪除圍欄「${geofence.name}」嗎？此操作無法復原。`)) {
        fetch(`/admin/geofence/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('success', data.message);
                
                // 從列表移除
                currentGeofences = currentGeofences.filter(g => g.id !== id);
                loadGeofences();
                
                // 重新載入頁面
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                showAlert('error', data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('error', '刪除失敗，請稍後再試');
        });
    }
}

// 切換圍欄狀態
function toggleGeofence(id) {
    const geofence = currentGeofences.find(g => g.id === id);
    if (!geofence) return;
    
    fetch(`/admin/geofence/${id}/toggle`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', data.message);
            
            // 更新狀態
            geofence.is_active = data.is_active;
            loadGeofences();
            
            // 重新載入頁面
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showAlert('error', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('error', '狀態切換失敗，請稍後再試');
    });
}

// 縮放到指定圍欄
function zoomToGeofence(id) {
    const geofence = currentGeofences.find(g => g.id === id);
    if (geofence) {
        map.setView([geofence.latitude, geofence.longitude], 17);
        
        // 高亮顯示該圍欄
        const circleIndex = currentGeofences.findIndex(g => g.id === id);
        if (circleIndex !== -1 && geofenceCircles[circleIndex]) {
            geofenceCircles[circleIndex].openPopup();
        }
    }
}

// 顯示警告訊息
function showAlert(type, message) {
    const alertContainer = document.getElementById('alertContainer');
    const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    const iconClass = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
    
    const alertHtml = `
        <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
            <i class="fas ${iconClass} me-2"></i>${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    alertContainer.innerHTML = alertHtml;
    
    // 自動消失
    setTimeout(() => {
        const alert = alertContainer.querySelector('.alert');
        if (alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }
    }, 5000);
}
</script>
@endsection