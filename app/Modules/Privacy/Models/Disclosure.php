<?php

declare(strict_types=1);

namespace App\Modules\Privacy\Models;

use App\Support\Errors\BusinessRejection;
use Illuminate\Database\Eloquent\Model;

/** Immutable evidence of a personal-information release. */
final class Disclosure extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'subject_person_id', 'recipient', 'purpose', 'authority', 'scope_type', 'scope_id', 'disclosed_category', 'disclosed_by'];

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw BusinessRejection::forCode('privacy.disclosure_immutable', 'disclosure evidence is append-only');
        }

        return parent::save($options);
    }

    public function delete(): bool
    {
        throw BusinessRejection::forCode('privacy.disclosure_immutable', 'disclosure evidence is append-only');
    }

    public function forceDelete(): bool
    {
        throw BusinessRejection::forCode('privacy.disclosure_immutable', 'disclosure evidence is append-only');
    }
}
