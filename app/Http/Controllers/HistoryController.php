<?php

namespace App\Http\Controllers;

use App\Http\Requests\HistoryFilterRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

class HistoryController extends Controller
{
    public function export(HistoryFilterRequest $request): Response
    {
        $filters = $request->filters();
        $maxRows = 5000;
        $readings = $filters->query()->limit($maxRows + 1)->get();
        $tooManyRows = $readings->count() > $maxRows;

        return response()->view('history.export', [
            'filters' => $filters->toArray(),
            'readings' => $tooManyRows ? collect() : $readings,
            'tooManyRows' => $tooManyRows,
            'maxRows' => $maxRows,
            'nodes' => collect(config('monitoring.nodes'))->keyBy('id'),
            'sensors' => collect(config('monitoring.sensors'))->keyBy('id'),
        ], $tooManyRows ? 422 : 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function index(HistoryFilterRequest $request): View
    {
        $filters = $request->filters();

        return view('history.index', [
            'filters' => $filters->toArray(),
            'readings' => $filters->query()->paginate(25)->appends($filters->toArray()),
            'nodes' => collect(config('monitoring.nodes'))->keyBy('id'),
            'sensors' => collect(config('monitoring.sensors'))->keyBy('id'),
        ]);
    }
}
