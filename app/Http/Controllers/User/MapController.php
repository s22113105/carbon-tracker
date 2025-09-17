<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;

class MapController extends Controller
{
    public function index()
    {
        return view('user.map');
    }
}