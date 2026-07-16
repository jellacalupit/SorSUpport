<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class AdminDashboardController extends Controller
{
    /**
     * Display the SDS Administrator dashboard.
     */
    public function index()
    {
        return view('admin.dashboard');
    }
}