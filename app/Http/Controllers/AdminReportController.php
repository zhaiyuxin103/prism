<?php

namespace App\Http\Controllers;

use App\Actions\BuildAdminReport;
use App\Http\Requests\AdminReportRequest;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class AdminReportController extends Controller
{
    public function __invoke(AdminReportRequest $request, BuildAdminReport $builder): Response
    {
        $startDate = $request->validated('start_date');
        $endDate = $request->validated('end_date');
        $report = $builder->handle($startDate, $endDate);
        $users = collect($report['users']);
        unset($report['users']);

        return Inertia::render('AdminReport', [
            'filters' => ['start_date' => $startDate, 'end_date' => $endDate],
            'report' => $report,
            'users' => (new LengthAwarePaginator(
                $users->forPage($request->integer('page', 1), 25)->values(),
                $users->count(), 25, $request->integer('page', 1),
                ['path' => route('admin.report')],
            ))->appends(['start_date' => $startDate, 'end_date' => $endDate]),
        ]);
    }
}
