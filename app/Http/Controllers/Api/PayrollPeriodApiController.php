<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Payroll\Commands\MaintainPayrollPeriod;
use App\Modules\Payroll\Models\PayrollPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** JSON interface for the payroll period lifecycle. */
final class PayrollPeriodApiController extends Controller
{
    public function open(Request $request): JsonResponse
    {
        $input = $request->validate([
            'period_key' => ['required', 'string', 'max:40'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
        ]);
        $result = app(MaintainPayrollPeriod::class)->open(
            $this->actor(),
            $input['period_key'],
            $input['date_from'],
            $input['date_to'],
            $this->idempotencyKey('payroll.open'),
        );

        return response()->json(['status' => 'opened', 'result' => $result], 201);
    }

    public function close(string $periodId): JsonResponse
    {
        $result = app(MaintainPayrollPeriod::class)->close(
            $this->actor(),
            PayrollPeriod::query()->findOrFail($periodId),
            $this->idempotencyKey('payroll.close'),
        );

        return response()->json(['status' => 'closed', 'result' => $result]);
    }
}
