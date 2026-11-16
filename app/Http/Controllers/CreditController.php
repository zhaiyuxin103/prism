<?php

namespace App\Http\Controllers;

use App\Actions\CreditLedger;
use App\Http\Requests\PurchaseCreditsRequest;
use App\Http\Resources\CreditEntryResource;
use App\Models\CreditEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CreditController extends Controller
{
    public function index(Request $request, CreditLedger $ledger): Response
    {
        $request->validate(['page' => ['sometimes', 'integer', 'min:1']]);

        return Inertia::render('Credits', [
            'credits' => fn () => $ledger->balance($request->user()),
            'creditPackages' => CreditLedger::PACKAGES,
            'entries' => fn () => CreditEntryResource::collection(CreditEntry::query()
                ->whereHas('creditAccount', fn ($query) => $query->where('user_id', $request->user()->id))
                ->orderByDesc('id')->paginate(20)),
        ]);
    }

    public function store(PurchaseCreditsRequest $request, CreditLedger $ledger): JsonResponse
    {
        $entry = $ledger->purchase($request->user(), $request->validated('amount'), $request->validated('client_request_id'));

        return response()->json([
            'entry' => new CreditEntryResource($entry),
            'credits' => $ledger->balance($request->user()),
        ], $entry->wasRecentlyCreated ? 201 : 200);
    }
}
