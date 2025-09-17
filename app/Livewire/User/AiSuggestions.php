<?php

namespace App\Livewire\User;

use Livewire\Component;
use App\Services\AiSuggestionService;

class AiSuggestions extends Component
{
    public $suggestions = '';
    public $isLoading = false;
    public $lastUpdated = null;

    protected $aiService;

    public function boot(AiSuggestionService $aiService)
    {
        $this->aiService = $aiService;
    }

    public function mount()
    {
        $this->loadCachedSuggestions();
    }

    public function generateSuggestions()
    {
        $this->isLoading = true;
        
        try {
            $this->suggestions = $this->aiService->generateSuggestionsForUser(auth()->id());
            $this->lastUpdated = now()->format('Y-m-d H:i');
            
            // 可以選擇將建議儲存到資料庫
            $this->cacheSuggestions();
            
        } catch (\Exception $e) {
            $this->suggestions = '生成建議時發生錯誤，請稍後再試。';
        }
        
        $this->isLoading = false;
    }

    private function loadCachedSuggestions()
    {
        // 這裡可以從資料庫載入快取的建議
        // 暫時先留空，每次都重新生成
    }

    private function cacheSuggestions()
    {
        // 這裡可以將建議儲存到資料庫
        // 暫時先不實作
    }

    public function render()
    {
        return view('livewire.user.ai-suggestions');
    }
}