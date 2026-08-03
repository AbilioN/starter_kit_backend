<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChangeTenantSubscriptionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // subscription_plans lives on the landlord connection, not
            // whatever database.default is for this tenant-resolved request.
            'subscription_plan_id' => 'required|uuid|exists:landlord.subscription_plans,id',
        ];
    }
}
