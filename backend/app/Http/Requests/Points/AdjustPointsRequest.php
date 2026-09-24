<?php

namespace App\Http\Requests\Points;

use App\Http\Requests\BaseFormRequest;

class AdjustPointsRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'points' => ['required', 'integer', 'not_in:0'],
            'note' => ['required', 'string', 'max:500'],
            // Free-text identifier for who performed this — see
            // docs/POINTS_ENGINE.md's note on staff-account auth status;
            // no per-user session exists yet to derive this from
            // automatically, so it's accepted as an optional field the
            // frontend can populate from whatever context it has.
            'admin_identity' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
