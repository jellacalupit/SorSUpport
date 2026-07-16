<?php

namespace App\Http\Controllers\Recipient;

use App\Http\Controllers\Controller;

class RecipientDashboardController extends Controller
{
    /**
     * Display the Recipient dashboard.
     */
    public function index()
    {
        return view('recipient.dashboard');
    }
}