<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Commands\DeactivateUserAccount;
use App\Modules\Identity\Commands\LinkUserAccount;
use App\Modules\Identity\Commands\RegisterPerson;
use App\Modules\Identity\Commands\SetAccountPassword;
use App\Modules\Identity\Commands\VerifyPerson;
use App\Modules\Identity\Models\Person;
use App\Modules\Identity\Models\UserAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** JSON interface for identity administration; domain commands remain authoritative. */
final class IdentityApiController extends Controller
{
    public function people(): JsonResponse
    {
        $this->requireOrganizationRead('identity.admin', 'api.identity.people');
        $visibleBranchIds = $this->authorizedBranches('identity.admin');
        $people = Person::query()->whereIn('home_branch_id', $visibleBranchIds)->orderBy('legal_name')->limit(300)->get(['id', 'legal_name', 'verification_state', 'home_branch_id']);
        return response()->json(['people' => $people]);
    }

    public function accounts(): JsonResponse
    {
        $this->requireOrganizationRead('identity.admin', 'api.identity.accounts');
        $visibleBranchIds = $this->authorizedBranches('identity.admin');
        $accounts = UserAccount::query()
            ->whereHas('person', fn ($query) => $query->whereIn('home_branch_id', $visibleBranchIds))
            ->orderBy('username')->limit(300)->get(['id', 'person_id', 'username', 'account_state', 'password_hash']);
        $accounts->each(fn (UserAccount $account) => $account->makeHidden(['password_hash']));
        return response()->json(['accounts' => $accounts]);
    }

    public function register(Request $request): JsonResponse
    {
        $input = $request->validate(['legal_name' => ['required', 'string', 'max:160'], 'date_of_birth' => ['required', 'date', 'before:today'], 'home_branch_id' => ['required', 'string']]);
        $result = app(RegisterPerson::class)->register($this->actor(), $input['legal_name'], $input['date_of_birth'], $input['home_branch_id'], $this->idempotencyKey('identity.person.register'));
        return response()->json(['status' => 'registered', 'person_id' => $result['person_id']], 201);
    }

    public function verify(Request $request, string $personId): JsonResponse
    {
        $input = $request->validate(['identity_key' => ['required', 'string', 'max:120'], 'evidence_ref' => ['required', 'string', 'max:255']]);
        app(VerifyPerson::class)->verify($this->actor(), Person::query()->findOrFail($personId), $input['identity_key'], $input['evidence_ref'], $this->idempotencyKey('identity.verify'));
        return response()->json(['status' => 'verified']);
    }

    public function link(Request $request): JsonResponse
    {
        $input = $request->validate(['person_id' => ['required', 'string'], 'username' => ['required', 'string', 'max:120']]);
        app(LinkUserAccount::class)->link($this->actor(), Person::query()->findOrFail((string) $input['person_id']), $input['username'], $this->idempotencyKey('identity.link'));
        return response()->json(['status' => 'linked'], 201);
    }

    public function password(Request $request, string $accountId): JsonResponse
    {
        $input = $request->validate(['password' => ['required', 'string', 'min:'.SetAccountPassword::MIN_PASSWORD_LENGTH, 'max:255']]);
        app(SetAccountPassword::class)->set($this->actor(), UserAccount::query()->findOrFail($accountId), $input['password'], $this->idempotencyKey('identity.password'));
        return response()->json(['status' => 'set']);
    }

    public function deactivate(Request $request, string $accountId): JsonResponse
    {
        $input = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        app(DeactivateUserAccount::class)->deactivate($this->actor(), UserAccount::query()->findOrFail($accountId), $input['reason'], $this->idempotencyKey('identity.deactivate'));
        return response()->json(['status' => 'deactivated']);
    }
}
