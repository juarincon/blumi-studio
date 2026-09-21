<?php
namespace App\Controllers;

use App\Helpers\View;
use App\Middleware\Auth;
use App\Services\ProjectService;

final class DashboardController
{
    public function index(): void
    {
        Auth::requireLogin();
        $projects = (new ProjectService())->all();
        View::render('dashboard/index', ['projects' => $projects]);
    }
}
