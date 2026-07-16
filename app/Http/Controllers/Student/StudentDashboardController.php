<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;

class StudentDashboardController extends Controller
{
    /**
     * Display the Student dashboard.
     */
    public function index()
    {
        return view('student.dashboard');
    }
}