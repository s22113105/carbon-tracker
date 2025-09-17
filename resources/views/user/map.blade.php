@extends('layouts.dashboard')

@section('title', '通勤路線地圖')

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
        <a class="nav-link active" href="{{ route('user.map') }}">
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
        <a class="nav-link" href="{{ route('user.ai-suggestions') }}">
            <i class="fas fa-lightbulb me-2"></i>AI 減碳建議
        </a>
    </li>
@endsection

@section('content')
<div class="row mb-4">
    <div class="col-md-12">
        <h1>通勤路線地圖</h1>
        <p class="text-muted">查看您的 GPS 軌跡和通勤路線，分析行程模式</p>
    </div>
</div>

@livewire('user.route-map')

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
// 防止重複載入
if (window.mapManagerLoaded) {
    console.log('地圖管理器已載入，跳過重複載入');
} else {
    window.mapManagerLoaded = true;

// 全域地圖管理器
window.mapManager = {
    map: null,
    routeLayer: null,
    markersLayer: null,
    universityMarker: null,
    isInitialized: false,
    isInitializing: false,
    initAttempts: 0,
    maxInitAttempts: 5,
    
    // 樹德科技大學座標
    university: {
        lat: 22.7632,
        lng: 120.3757,
        zoom: 15
    },
    
    // 安全地獲取元素屬性
    safeGetProperty: function(element, property) {
        try {
            if (!element) return 0;
            
            // 確保元素在 DOM 中
            if (!document.contains(element)) {
                console.warn('元素不在 DOM 中');
                return 0;
            }
            
            // 確保元素可見
            if (element.style.display === 'none') {
                element.style.display = 'block';
            }
            
            // 安全地獲取屬性
            const value = element[property];
            return value || 0;
            
        } catch (error) {
            console.warn(`無法獲取屬性 ${property}:`, error);
            return 0;
        }
    },
    
    // 強制確保元素有尺寸
    ensureElementSize: function(element) {
        if (!element) return false;
        
        try {
            // 設定基本樣式
            element.style.display = 'block';
            element.style.visibility = 'visible';
            
            // 如果沒有寬度，設定寬度
            if (this.safeGetProperty(element, 'offsetWidth') === 0) {
                element.style.width = '100%';
                console.log('設定元素寬度為 100%');
            }
            
            // 如果沒有高度，設定高度
            if (this.safeGetProperty(element, 'offsetHeight') === 0) {
                element.style.height = '500px';
                console.log('設定元素高度為 500px');
            }
            
            // 強制重新計算佈局
            element.offsetHeight; // 觸發重新計算
            
            return this.safeGetProperty(element, 'offsetWidth') > 0 && 
                   this.safeGetProperty(element, 'offsetHeight') > 0;
                   
        } catch (error) {
            console.error('確保元素尺寸時發生錯誤:', error);
            return false;
        }
    },
    
    // 等待元素準備就緒
    waitForElement: function(selector, timeout = 15000) {
        return new Promise((resolve, reject) => {
            const startTime = Date.now();
            let attempts = 0;
            const maxAttempts = Math.floor(timeout / 200);
            
            const checkElement = () => {
                attempts++;
                
                try {
                    const element = document.querySelector(selector);
                    
                    if (element) {
                        console.log(`找到元素 ${selector}，嘗試確保尺寸...`);
                        
                        if (this.ensureElementSize(element)) {
                            console.log(`元素 ${selector} 準備就緒`);
                            resolve(element);
                            return;
                        }
                    }
                    
                    if (attempts >= maxAttempts) {
                        reject(new Error(`等待元素 ${selector} 超時 (${attempts} 次嘗試)`));
                        return;
                    }
                    
                    console.log(`等待元素 ${selector}... (${attempts}/${maxAttempts})`);
                    setTimeout(checkElement, 200);
                    
                } catch (error) {
                    console.error(`檢查元素 ${selector} 時發生錯誤:`, error);
                    setTimeout(checkElement, 200);
                }
            };
            
            checkElement();
        });
    },
    
    // 安全地創建地圖
    createMapSafely: function(element) {
        try {
            // 最後一次確認元素狀態
            if (!this.ensureElementSize(element)) {
                throw new Error('無法確保地圖容器有正確尺寸');
            }
            
            console.log('創建 Leaflet 地圖實例...');
            
            // 創建地圖時使用更保守的選項
            const map = L.map(element, {
                center: [this.university.lat, this.university.lng],
                zoom: this.university.zoom,
                zoomControl: true,
                attributionControl: true,
                preferCanvas: false, // 使用 SVG 渲染，更穩定
                fadeAnimation: false, // 禁用動畫避免計算問題
                zoomAnimation: false,
                markerZoomAnimation: false
            });
            
            console.log('地圖實例創建成功');
            return map;
            
        } catch (error) {
            console.error('創建地圖實例時發生錯誤:', error);
            throw error;
        }
    },
    
    // 初始化地圖
    init: function() {
        if (this.isInitializing) {
            console.log('地圖正在初始化中，跳過重複初始化');
            return Promise.resolve();
        }
        
        if (this.isInitialized && this.map) {
            console.log('地圖已經初始化完成');
            return Promise.resolve();
        }
        
        this.initAttempts++;
        if (this.initAttempts > this.maxInitAttempts) {
            console.error('地圖初始化超過最大嘗試次數');
            return Promise.reject(new Error('初始化失敗'));
        }
        
        this.isInitializing = true;
        console.log(`開始初始化地圖 (第 ${this.initAttempts} 次嘗試)...`);
        
        return this.waitForElement('#map')
            .then((mapElement) => {
                console.log('地圖容器準備就緒，開始創建地圖...');
                
                // 清理舊實例
                if (this.map) {
                    try {
                        this.map.remove();
                    } catch (e) {
                        console.warn('清理舊地圖實例時發生錯誤:', e);
                    }
                    this.map = null;
                }
                
                // 創建新地圖
                this.map = this.createMapSafely(mapElement);
                
                // 添加圖層
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors',
                    maxZoom: 19
                }).addTo(this.map);
                
                // 創建圖層群組
                this.routeLayer = L.layerGroup().addTo(this.map);
                this.markersLayer = L.layerGroup().addTo(this.map);
                
                // 添加大學標記
                this.universityMarker = L.marker([this.university.lat, this.university.lng], {
                    icon: L.divIcon({
                        className: 'university-marker',
                        html: '<div style="background-color: #ff6b35; color: white; border-radius: 50%; width: 25px; height: 25px; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);">樹</div>',
                        iconSize: [25, 25]
                    }),
                    isPermanent: true
                }).bindPopup('樹德科技大學').addTo(this.markersLayer);
                
                // 返回 Promise，在地圖完全載入後解析
                return new Promise((resolve) => {
                    this.map.whenReady(() => {
                        console.log('地圖載入完成');
                        this.isInitialized = true;
                        this.isInitializing = false;
                        this.initAttempts = 0; // 重置嘗試次數
                        
                        // 最終確保正確渲染
                        setTimeout(() => {
                            try {
                                if (this.map) {
                                    this.map.invalidateSize();
                                    console.log('地圖初始化並渲染完成');
                                }
                            } catch (error) {
                                console.warn('最終渲染時發生警告:', error);
                            }
                            resolve();
                        }, 300);
                    });
                });
                
            })
            .catch((error) => {
                console.error('地圖初始化失敗:', error);
                this.isInitializing = false;
                this.isInitialized = false;
                
                // 如果還有重試機會，稍後重試
                if (this.initAttempts < this.maxInitAttempts) {
                    console.log(`將在 2 秒後重試初始化 (${this.initAttempts}/${this.maxInitAttempts})`);
                    return new Promise((resolve) => {
                        setTimeout(() => {
                            this.init().then(resolve).catch(() => resolve());
                        }, 2000);
                    });
                }
                
                throw error;
            });
    },
    
    // 重置地圖
    reset: function() {
        console.log('重置地圖...');
        
        if (!this.isInitialized || !this.map) {
            console.log('地圖未初始化，重新初始化');
            return this.init();
        }
        
        try {
            // 清除圖層
            if (this.routeLayer) {
                this.routeLayer.clearLayers();
            }
            
            if (this.markersLayer) {
                this.markersLayer.eachLayer((layer) => {
                    if (!layer.options.isPermanent) {
                        this.markersLayer.removeLayer(layer);
                    }
                });
            }
            
            // 重置視野
            this.map.setView([this.university.lat, this.university.lng], this.university.zoom);
            
            // 確保正確顯示
            setTimeout(() => {
                try {
                    if (this.map) {
                        this.map.invalidateSize();
                    }
                } catch (error) {
                    console.warn('重置時渲染發生警告:', error);
                }
            }, 100);
            
            console.log('地圖重置完成');
            return Promise.resolve();
            
        } catch (error) {
            console.error('重置地圖錯誤:', error);
            // 重新初始化
            this.isInitialized = false;
            return this.init();
        }
    },
    
    // 更新地圖路線
    update: function(gpsPoints, selectedTrip) {
        console.log('更新地圖...', {
            pointsCount: gpsPoints?.length || 0,
            selectedTrip: selectedTrip
        });
        
        if (!this.isInitialized || !this.map) {
            console.log('地圖未初始化，先初始化');
            return this.init().then(() => {
                setTimeout(() => this.update(gpsPoints, selectedTrip), 500);
            });
        }
        
        try {
            // 清除現有路線
            if (this.routeLayer) {
                this.routeLayer.clearLayers();
            }
            
            // 清除臨時標記
            if (this.markersLayer) {
                this.markersLayer.eachLayer((layer) => {
                    if (!layer.options.isPermanent) {
                        this.markersLayer.removeLayer(layer);
                    }
                });
            }
            
            if (!gpsPoints || gpsPoints.length === 0) {
                console.log('沒有 GPS 資料，重置到預設視野');
                this.map.setView([this.university.lat, this.university.lng], this.university.zoom);
                return;
            }
            
            // 準備路線點
            const routePoints = gpsPoints.map(point => [point.lat, point.lng]);
            
            // 繪製路線
            const polyline = L.polyline(routePoints, {
                color: '#007bff',
                weight: 4,
                opacity: 0.8
            }).addTo(this.routeLayer);
            
            // 起點標記
            if (routePoints.length > 0) {
                L.marker(routePoints[0], {
                    icon: L.divIcon({
                        className: 'custom-marker',
                        html: '<div style="background-color: #28a745; color: white; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-size: 12px;">起</div>',
                        iconSize: [20, 20]
                    }),
                    isPermanent: false
                }).addTo(this.markersLayer);
            }
            
            // 終點標記
            if (routePoints.length > 1) {
                L.marker(routePoints[routePoints.length - 1], {
                    icon: L.divIcon({
                        className: 'custom-marker',
                        html: '<div style="background-color: #dc3545; color: white; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-size: 12px;">終</div>',
                        iconSize: [20, 20]
                    }),
                    isPermanent: false
                }).addTo(this.markersLayer);
            }
            
            // 調整視野
            this.map.fitBounds(polyline.getBounds(), {
                padding: [20, 20],
                maxZoom: 16
            });
            
            console.log('地圖更新完成');
            
        } catch (error) {
            console.error('更新地圖錯誤:', error);
        }
    },
    
    // 獲取 Livewire 組件資料
    getLivewireData: function() {
        try {
            const element = document.querySelector('[wire\\:id]');
            if (!element) return null;
            
            const wireId = element.getAttribute('wire:id');
            if (!wireId) return null;
            
            return Livewire.find(wireId);
        } catch (error) {
            console.error('獲取 Livewire 資料錯誤:', error);
            return null;
        }
    }
};

// 全域函數
window.initMap = () => window.mapManager.init();
window.resetMap = () => window.mapManager.reset();
window.updateMap = (gpsPoints, selectedTrip) => window.mapManager.update(gpsPoints, selectedTrip);

// 事件監聽
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM 載入完成，延遲初始化地圖...');
    setTimeout(() => {
        window.mapManager.init().catch(error => {
            console.error('DOMContentLoaded 初始化失敗:', error);
        });
    }, 800);
});

// Livewire 事件 - 使用更安全的方式
let livewireInitialized = false;

document.addEventListener('livewire:init', function() {
    if (livewireInitialized) return;
    livewireInitialized = true;
    
    console.log('Livewire 初始化，設定事件監聽器');
    
    Livewire.on('tripSelected', () => {
        setTimeout(() => {
            const component = window.mapManager.getLivewireData();
            if (component) {
                window.mapManager.update(component.gpsPoints, component.selectedTrip);
            }
        }, 100);
    });
    
    Livewire.on('mapReset', () => {
        setTimeout(() => {
            window.mapManager.reset();
        }, 100);
    });
    
    Livewire.on('centerToUniversity', () => {
        setTimeout(() => {
            if (window.mapManager.map) {
                window.mapManager.map.setView([
                    window.mapManager.university.lat, 
                    window.mapManager.university.lng
                ], window.mapManager.university.zoom);
            }
        }, 100);
    });
});

// 其他事件
document.addEventListener('livewire:navigated', () => {
    setTimeout(() => {
        if (!window.mapManager.isInitialized) {
            window.mapManager.init();
        }
    }, 500);
});

document.addEventListener('livewire:updated', () => {
    setTimeout(() => {
        if (window.mapManager.map) {
            try {
                window.mapManager.map.invalidateSize();
            } catch (error) {
                console.warn('livewire:updated 渲染警告:', error);
            }
        } else if (!window.mapManager.isInitializing) {
            window.mapManager.init();
        }
    }, 200);
});

window.addEventListener('resize', () => {
    setTimeout(() => {
        if (window.mapManager.map) {
            try {
                window.mapManager.map.invalidateSize();
            } catch (error) {
                console.warn('resize 渲染警告:', error);
            }
        }
    }, 100);
});

} // 結束防重複載入檢查
</script>


<style>
/* 原有樣式保留 */
.trip-item:hover {
    background-color: #e9ecef !important;
}

.trip-item.bg-primary:hover {
    background-color: #0056b3 !important;
}

#map {
    border-radius: 8px;
    border: 1px solid #dee2e6;
}

.custom-marker {
    border: none !important;
    background: transparent !important;
}

/* 新增：大學標記樣式 */
.university-marker {
    border: none !important;
    background: transparent !important;
}

/* 地圖控制項樣式 */
.leaflet-control-zoom {
    border: none !important;
    box-shadow: 0 1px 5px rgba(0,0,0,0.15) !important;
}

.leaflet-control-zoom a {
    background-color: #fff !important;
    color: #333 !important;
    border: 1px solid #ccc !important;
}

.leaflet-control-zoom a:hover {
    background-color: #f5f5f5 !important;
}

/* 載入動畫 */
.map-loading {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    z-index: 1000;
    background-color: rgba(255, 255, 255, 0.9);
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

/* 確保地圖在各種設備上都能正常顯示 */
@media (max-width: 768px) {
    #map {
        height: 400px !important;
    }
}
</style>
@endsection