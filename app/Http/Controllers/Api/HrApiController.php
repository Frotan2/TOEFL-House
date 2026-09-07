<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Commands\MaintainContract;
use App\Modules\Hr\Commands\MaintainContractVersion;
use App\Modules\Hr\Commands\MaintainEmployment;
use App\Modules\Hr\Commands\MaintainLeave;
use App\Modules\Hr\Commands\MaintainScale;
use App\Modules\Hr\Models\Contract;
use App\Modules\Hr\Models\ContractVersion;
use App\Modules\Hr\Models\Employment;
use App\Modules\Hr\Models\Leave;
use App\Modules\Hr\Models\Scale;
use App\Modules\Identity\Models\Person;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** JSON interface for the People / HR authority surface. */
final class HrApiController extends Controller
{
    public function workspace(): JsonResponse
    {
        $this->requireOrganizationRead('hr.employ', 'api.hr.workspace');
        $people = Person::query()
            ->where('verification_state', 'verified')
            ->whereIn('home_branch_id', $this->authorizedBranches('hr.employ'))
            ->orderBy('legal_name')->limit(300)->get();
        $employmentIds = Employment::query()->whereIn('person_id', $people->pluck('id')->all())->pluck('id')->all();

        $employments = Employment::query()->whereIn('id', $employmentIds)->orderByDesc('id')->limit(300)->get();
        $leaves = Leave::query()->whereIn('employment_id', $employmentIds)->orderByDesc('date_from')->limit(300)->get();
        $contracts = Contract::query()->whereIn('employment_id', $employmentIds)->orderByDesc('id')->limit(200)->get();
        $versions = ContractVersion::query()->whereIn('contract_id', $contracts->pluck('id')->all())->orderByDesc('effective_from')->limit(300)->get();

        return response()->json([
            'data' => [
                'people' => $people,
                'employments' => $employments,
                'leaves' => $leaves,
                'contracts' => $contracts,
                'contract_versions' => $versions,
                'scales' => Scale::query()->orderBy('rank_order')->get(),
                'capabilities' => [
                    'employ' => $this->can('hr.employ'),
                    'contract' => $this->can('hr.contract'),
                ],
            ],
        ]);
    }

    public function employ(Request $request): JsonResponse
    {
        $input = $request->validate(['person_id' => ['required', 'string']]);
        $result = app(MaintainEmployment::class)->employ($this->actor(), $input['person_id'], $this->idempotencyKey('hr.employ'));
        return response()->json(['status' => 'candidate_created', 'result' => $result], 201);
    }

    public function employmentTransition(Request $request, string $employmentId, string $action): JsonResponse
    {
        $input = $request->validate([
            'effective_from' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);
        $employment = Employment::query()->findOrFail($employmentId);
        $idempotency = $this->idempotencyKey('hr.employment.'.$action);
        $effectiveFrom = $input['effective_from'];
        $result = match ($action) {
            'hire' => app(MaintainEmployment::class)->hire($this->actor(), $employment, $effectiveFrom, $idempotency),
            'leave' => app(MaintainEmployment::class)->placeOnLeave($this->actor(), $employment, $effectiveFrom, $idempotency),
            'suspend' => app(MaintainEmployment::class)->suspend($this->actor(), $employment, $effectiveFrom, $idempotency),
            'reinstate' => app(MaintainEmployment::class)->reinstate($this->actor(), $employment, $effectiveFrom, $idempotency),
            'terminate' => app(MaintainEmployment::class)->terminate($this->actor(), $employment, $effectiveFrom, (string) ($input['reason'] ?? ''), $idempotency),
            default => abort(404, 'unknown_hr_employment_action'),
        };
        return response()->json(['status' => $action.'_recorded', 'result' => $result]);
    }

    public function requestLeave(Request $request, string $employmentId): JsonResponse
    {
        $input = $request->validate([
            'category' => ['required', 'string', 'max:120'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $result = app(MaintainLeave::class)->request($this->actor(), Employment::query()->findOrFail($employmentId), $input['category'], $input['date_from'], $input['date_to'], $input['reason'], $this->idempotencyKey('hr.leave.request'));
        return response()->json(['status' => 'requested', 'result' => $result], 201);
    }

    public function decideLeave(Request $request, string $leaveId): JsonResponse
    {
        $input = $request->validate(['decision' => ['required', 'in:approve,reject']]);
        $result = app(MaintainLeave::class)->decide($this->actor(), Leave::query()->findOrFail($leaveId), $input['decision'] === 'approve', $this->idempotencyKey('hr.leave.decide'));
        return response()->json(['status' => 'decided', 'result' => $result]);
    }

    public function cancelLeave(string $leaveId): JsonResponse
    {
        $result = app(MaintainLeave::class)->cancel($this->actor(), Leave::query()->findOrFail($leaveId), $this->idempotencyKey('hr.leave.cancel'));
        return response()->json(['status' => 'cancelled', 'result' => $result]);
    }

    public function prepareVersion(Request $request): JsonResponse
    {
        $input = $request->validate([
            'employment_id' => ['required', 'string'], 'terms_ref' => ['required', 'string', 'max:255'], 'scale_id' => ['nullable', 'string'],
            'effective_from' => ['required', 'date'], 'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ]);
        $result = app(MaintainContractVersion::class)->prepare($this->actor(), Employment::query()->findOrFail($input['employment_id']), $input['terms_ref'], ($input['scale_id'] ?? '') !== '' ? $input['scale_id'] : null, $input['effective_from'], $input['effective_to'] ?? null, $this->idempotencyKey('hr.version.prepare'));
        return response()->json(['status' => 'prepared', 'result' => $result], 201);
    }

    public function addRule(Request $request, string $versionId): JsonResponse
    {
        $input = $request->validate([
            'method' => ['required', 'in:fixed_monthly,session_rate,hourly_rate,scale_rate,allowance'], 'rate' => ['required', 'numeric', 'money', 'gte:0'],
            'skill_id' => ['nullable', 'string'], 'scale_id' => ['nullable', 'string'], 'label' => ['nullable', 'string', 'max:120'],
        ]);
        $result = app(MaintainContractVersion::class)->addRule($this->actor(), ContractVersion::query()->findOrFail($versionId), $input['method'], $input['rate'], ($input['skill_id'] ?? '') !== '' ? $input['skill_id'] : null, ($input['scale_id'] ?? '') !== '' ? $input['scale_id'] : null, ($input['label'] ?? '') !== '' ? $input['label'] : null, $this->idempotencyKey('hr.version.rule'));
        return response()->json(['status' => 'rule_added', 'result' => $result], 201);
    }

    public function versionTransition(string $versionId, string $action): JsonResponse
    {
        $version = ContractVersion::query()->findOrFail($versionId);
        $result = match ($action) {
            'submit' => app(MaintainContractVersion::class)->submit($this->actor(), $version, $this->idempotencyKey('hr.version.submit')),
            'withdraw' => app(MaintainContractVersion::class)->withdraw($this->actor(), $version, $this->idempotencyKey('hr.version.withdraw')),
            'approve' => app(MaintainContractVersion::class)->approve($this->actor(), $version, $this->idempotencyKey('hr.version.approve')),
            default => abort(404, 'unknown_hr_version_action'),
        };
        return response()->json(['status' => $action.'_recorded', 'result' => $result]);
    }

    public function draftContract(Request $request): JsonResponse
    {
        $input = $request->validate(['employment_id' => ['required', 'string'], 'terms_summary' => ['required', 'string', 'max:2000'], 'effective_from' => ['required', 'date']]);
        $result = app(MaintainContract::class)->draft($this->actor(), Employment::query()->findOrFail($input['employment_id']), $input['terms_summary'], $input['effective_from'], $this->idempotencyKey('hr.contract.draft'));
        return response()->json(['status' => 'drafted', 'result' => $result], 201);
    }

    public function contractSign(Request $request, string $contractId): JsonResponse
    {
        $input = $request->validate(['signed_ref' => ['required', 'string', 'max:255']]);
        $result = app(MaintainContract::class)->sign($this->actor(), Contract::query()->findOrFail($contractId), $input['signed_ref'], $this->idempotencyKey('hr.contract.sign'));
        return response()->json(['status' => 'signed', 'result' => $result]);
    }

    public function contractClose(Request $request, string $contractId): JsonResponse
    {
        $input = $request->validate(['effective_to' => ['required', 'date']]);
        $result = app(MaintainContract::class)->close($this->actor(), Contract::query()->findOrFail($contractId), $input['effective_to'], $this->idempotencyKey('hr.contract.close'));
        return response()->json(['status' => 'closed', 'result' => $result]);
    }

    public function registerScale(Request $request): JsonResponse
    {
        $input = $request->validate(['key' => ['required', 'string', 'max:64'], 'name' => ['required', 'string', 'max:255'], 'rank_order' => ['required', 'integer', 'min:1']]);
        $result = app(MaintainScale::class)->register($this->actor(), $input['key'], $input['name'], (int) $input['rank_order'], $this->idempotencyKey('hr.scale.register'));
        return response()->json(['status' => 'registered', 'result' => $result], 201);
    }

    public function retireScale(string $scaleId): JsonResponse
    {
        $result = app(MaintainScale::class)->retire($this->actor(), Scale::query()->findOrFail($scaleId), $this->idempotencyKey('hr.scale.retire'));
        return response()->json(['status' => 'retired', 'result' => $result]);
    }

    private function can(string $capability): bool
    {
        return app(\App\Support\Authorization\AccessDecision::class)->decide($this->actor(), $capability, null)->allowed;
    }
}
