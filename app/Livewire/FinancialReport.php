<?php

namespace App\Livewire;

use App\Application\UseCases\GodAdmin\GenerateFinancialReportUseCase;
use Livewire\Component;

class FinancialReport extends Component
{
    public function render(GenerateFinancialReportUseCase $generateFinancialReport)
    {
        return view('livewire.financial-report', [
            'report' => $generateFinancialReport->execute(),
        ])->layout('layouts.god');
    }
}
