<?php

namespace App\Http\Controllers;

use App\Monitoring\MonitoringFilters;
use App\Monitoring\MonitoringSnapshot;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(MonitoringSnapshot $snapshot, MonitoringFilters $filters, Request $request): View
    {
        $selected = $filters->fromRequest($request);

        return view('dashboard', [...$snapshot->forNode(), ...$selected]);
    }
}
