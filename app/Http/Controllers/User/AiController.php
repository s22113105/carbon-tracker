<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;

class AiController extends Controller
{
    public function index()
    {
        return view('user.ai-suggestions');
    }
}   