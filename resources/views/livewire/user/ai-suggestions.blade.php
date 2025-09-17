<div>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">AI 個人化減碳建議</h5>
            <button class="btn btn-primary btn-sm" wire:click="generateSuggestions" 
                    wire:loading.attr="disabled">
                <span wire:loading.remove>
                    <i class="fas fa-magic me-1"></i>生成新建議
                </span>
                <span wire:loading>
                    <i class="fas fa-spinner fa-spin me-1"></i>AI 分析中...
                </span>
            </button>
        </div>
        <div class="card-body">
            @if($isLoading)
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">載入中...</span>
                    </div>
                    <p class="mt-2 text-muted">AI 正在分析您的通勤資料...</p>
                </div>
            @elseif($suggestions)
                <div class="alert alert-info mb-3">
                    <i class="fas fa-robot me-2"></i>
                    基於您過去30天的通勤資料分析
                    @if($lastUpdated)
                        <small class="text-muted">(更新時間：{{ $lastUpdated }})</small>
                    @endif
                </div>
                
                <div class="ai-suggestions" style="line-height: 1.8;">
                    {!! nl2br(e($suggestions)) !!}
                </div>
            @else
                <div class="text-center py-4">
                    <i class="fas fa-lightbulb fa-3x text-muted mb-3"></i>
                    <h6 class="text-muted">點擊上方按鈕獲得 AI 減碳建議</h6>
                    <p class="text-muted small">AI 將分析您的通勤模式並提供個人化建議</p>
                </div>
            @endif
        </div>
    </div>

    <!-- 快速建議卡片 -->
    <div class="row mt-4">
        <div class="col-md-4">
            <div class="card border-success">
                <div class="card-body text-center">
                    <i class="fas fa-walking fa-2x text-success mb-2"></i>
                    <h6>多走路</h6>
                    <small class="text-muted">短距離改用步行</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-info">
                <div class="card-body text-center">
                    <i class="fas fa-subway fa-2x text-info mb-2"></i>
                    <h6>搭乘大眾運輸</h6>
                    <small class="text-muted">捷運公車更環保</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-warning">
                <div class="card-body text-center">
                    <i class="fas fa-users fa-2x text-warning mb-2"></i>
                    <h6>共乘計畫</h6>
                    <small class="text-muted">與同事共享交通</small>
                </div>
            </div>
        </div>
    </div>
</div>