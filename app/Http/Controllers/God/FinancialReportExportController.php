<?php

namespace App\Http\Controllers\God;

use App\Application\UseCases\GodAdmin\GenerateFinancialReportUseCase;
use App\Application\UseCases\Landlord\LogLandlordAuditUseCase;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialReportExportController extends Controller
{
    public function __invoke(
        GenerateFinancialReportUseCase $generateFinancialReport,
        LogLandlordAuditUseCase $logLandlordAudit,
    ): StreamedResponse {
        $report = $generateFinancialReport->execute();

        $logLandlordAudit->execute(
            actorId: Auth::guard('godadmin')->id(),
            action: 'financial_report_exported',
            model: 'FinancialReport',
        );

        return response()->streamDownload(function () use ($report) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Month', 'Revenue (cents)']);
            foreach ($report['monthly_revenue'] as $row) {
                fputcsv($handle, [$row['month'], $row['total_cents']]);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Plan', 'Visibility', 'Tenants', 'Revenue (cents)']);
            foreach ($report['by_plan'] as $row) {
                fputcsv($handle, [$row['plan_name'], $row['is_public'] ? 'Public' : 'Private', $row['tenant_count'], $row['total_price_cents']]);
            }

            fclose($handle);
        }, 'financial-report-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }
}
