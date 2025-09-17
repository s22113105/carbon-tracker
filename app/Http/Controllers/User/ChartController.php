<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;

class ChartController extends Controller
{
    public function index()
    {
        return view('user.chart');
    }
}