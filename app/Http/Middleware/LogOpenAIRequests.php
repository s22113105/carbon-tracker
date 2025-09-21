<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LogOpenAIRequests
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->is('carbon/*')) {
            Log::channel('openai')->info('Carbon Analysis Request', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'params' => $request->all(),
                'user_id' => auth()->id()
            ]);
        }
        
        $response = $next($request);
        
        if ($request->is('carbon/*')) {
            Log::channel('openai')->info('Carbon Analysis Response', [
                'status' => $response->status(),
                'content_length' => strlen($response->getContent())
            ]);
        }
        
        return $response;
    }
}