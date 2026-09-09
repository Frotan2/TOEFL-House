<?php

declare(strict_types=1);

namespace App\Modules\Privacy\Models;

use App\Support\Errors\BusinessRejection;
use Illuminate\Database\Eloquent\Model;

/** Immutable evidence of consent withdrawal. */
final class ConsentRevocation extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['id', 'consent_id', 'revoked_by', 'scope', 'effect'];

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw BusinessRejection::forCode('privacy.revocation_immutable', 'consent revocation evidence is append-only');
        }
        return parent::save($options);
    }

    public function delete(): bool
    {
        throw BusinessRejection::forCode('privacy.revocation_immutable', 'consent revocation evidence is append-only');
    }

    public function forceDelete(): bool
    {
        throw BusinessRejection::forCode('privacy.revocation_immutable', 'consent revocation evidence is append-only');
    }
}
