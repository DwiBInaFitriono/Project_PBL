<?php

namespace App\Http\Controllers;

use App\Monitoring\MonitoringSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class MonitoringDataController extends Controller
{
    public function __invoke(Request $request, MonitoringSnapshot $snapshot): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'node' => ['bail', 'nullable', 'string', Rule::in(['1', '2'])],
        ], ['node.*' => 'Node yang dipilih tidak valid.']);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Filter monitoring tidak valid.',
                'errors' => $validator->errors(),
            ], 422, ['Cache-Control' => 'private, no-store']);
        }

        return response()->json(
            $snapshot->forNode($validator->validated()['node'] ?? null)['monitoring'],
            200,
            ['Cache-Control' => 'private, no-store'],
        );
    }
}
