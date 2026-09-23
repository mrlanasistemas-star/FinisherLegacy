<?php

namespace App\Http\Controllers\Api\V1\Social;

use App\Actions\Social\CreateReport;
use App\Enums\ReportTargetType;
use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Api\V1\Concerns\ResolvesAuthenticatedUser;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * `POST /reports` — profile (username), moment (uuid) or comment (uuid).
 * Never reveals anything about the review; just acknowledges.
 */
class ReportController extends Controller
{
    use ApiResponses;
    use ResolvesAuthenticatedUser;

    public function store(Request $request, CreateReport $create): JsonResponse
    {
        $data = $request->validate([
            'target_type' => ['required', Rule::enum(ReportTargetType::class)],
            'target' => ['required', 'string', 'max:100'],
            'reason' => ['required', Rule::in(config('finisher.social.report_reasons'))],
            'details' => ['nullable', 'string', 'max:1000'],
        ]);

        $report = $create->handle(
            $this->sanctumUser($request),
            ReportTargetType::from($data['target_type']),
            $data['target'],
            $data['reason'],
            $data['details'] ?? null,
        );

        return $this->respond(['uuid' => $report->uuid], 'Gracias. Nuestro equipo revisará tu reporte.', status: 201);
    }
}
