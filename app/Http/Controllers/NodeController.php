<?php

namespace App\Http\Controllers;

use App\Monitoring\MonitoringFilters;
use App\Monitoring\MonitoringSnapshot;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class NodeController extends Controller
{
    public function __invoke(string $node, MonitoringSnapshot $snapshot, Request $request, MonitoringFilters $filters): View
    {
        $selected = $filters->fromRequest($request);
        $data = $snapshot->forNode($node);
        abort_if($data['nodes'] === [], 404);

        $data['monitoring']['activeSensor'] = $selected['activeSensor'];

        return view('nodes.show', [...$data, 'node' => $data['nodes'][0], ...$selected]);
    }
}
