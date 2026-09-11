<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\StudentPortalService;
use Illuminate\View\View;

class EventController extends Controller
{
    public function index(StudentPortalService $portal): View
    {
        $user = request()->user()->load('teams');

        return view('student.events', ['user' => $user, 'events' => $portal->eligibleEvents($user, true)]);
    }
}
