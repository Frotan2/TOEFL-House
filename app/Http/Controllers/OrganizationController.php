<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Audit\AttemptedOperation;
use App\Modules\Organization\Models\Organization;
use App\Support\Authorization\AccessDecision;
use App\Support\Authorization\StructureDecision;
use App\Support\Authorization\StructureScope;
use App\Support\Errors\AuthorizationDenied;
use Illuminate\View\View;

/**
 * Organization &amp; Configuration console shell. The React boundary loads
 * the fail-closed management projection and the staged topology governance
 * workflow from the API; this controller only admits actors that hold at
 * least one topology governance capability anywhere in their scope.
 */
final class OrganizationController extends Controller
{
    public function index(): View
    {
        $actor = $this->actor();
        $access = app(AccessDecision::class);
        $capabilities = [
            StructureDecision::CAPABILITY_INITIATE,
            StructureDecision::CAPABILITY_REVIEW,
            StructureDecision::CAPABILITY_APPROVE,
        ];

        foreach ($capabilities as $capability) {
            if ($access->decide($actor, $capability, null)->allowed) {
                return view('organization.index');
            }
        }
        foreach (Organization::query()->get() as $organization) {
            $scope = (new StructureScope($organization->id))->withInactiveLifecycleAccess();
            foreach ($capabilities as $capability) {
                if ($access->decide($actor, $capability, $scope)->allowed) {
                    return view('organization.index');
                }
            }
        }

        app(AttemptedOperation::class)->deniedByActor(
            AuthorizationDenied::forCode('api.organization_read_denied', 'no topology governance capability is held in any visible scope'),
            $actor,
            'organization.console.index',
            'console',
            'index',
        );
    }
}
