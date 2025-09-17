<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;

class RealTimeController extends Controller
{
    public function index()
    {
        return view('user.realtime');
    }
}