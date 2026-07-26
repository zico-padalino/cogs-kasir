<?php

namespace App\Http\Controllers\Web;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\BusinessExpense;
use App\Services\BusinessFundService;
use App\Support\Format;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class BusinessFundController extends Controller
{
    public function index(Request $request, BusinessFundService $fundService): View
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date', 'before_or_equal:today'],
            'edit' => ['nullable', 'integer', 'exists:business_expenses,id'],
        ]);

        $date = Carbon::parse($validated['date'] ?? now()->toDateString())->startOfDay();
        $editExpense = isset($validated['edit'])
            ? BusinessExpense::query()->find($validated['edit'])
            : null;

        return view('business-funds.index', [
            ...$fundService->dayReport($date),
            'balance' => $fundService->balance(),
            'editExpense' => $editExpense,
            'categories' => BusinessExpense::CATEGORIES,
            'paymentMethods' => PaymentMethod::cases(),
            'format' => Format::class,
        ]);
    }

    public function store(Request $request, BusinessFundService $fundService): RedirectResponse
    {
        $validated = $this->validateExpense($request);

        try {
            $fundService->addExpense(
                amount: (float) $validated['amount'],
                category: $validated['category'],
                paymentMethod: PaymentMethod::from($validated['payment_method']),
                note: $validated['note'],
                occurredAt: $this->expenseDate($validated['date']),
                user: $request->user(),
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('business-funds.index', ['date' => $validated['date']])
            ->with('success', 'Pengeluaran usaha berhasil dicatat.');
    }

    public function update(
        Request $request,
        BusinessExpense $businessExpense,
        BusinessFundService $fundService,
    ): RedirectResponse {
        $validated = $this->validateExpense($request);

        try {
            $fundService->updateExpense(
                expense: $businessExpense,
                amount: (float) $validated['amount'],
                category: $validated['category'],
                paymentMethod: PaymentMethod::from($validated['payment_method']),
                note: $validated['note'],
                occurredAt: $this->expenseDate($validated['date']),
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('business-funds.index', ['date' => $validated['date']])
            ->with('success', 'Pengeluaran usaha berhasil diperbarui.');
    }

    public function destroy(BusinessExpense $businessExpense): RedirectResponse
    {
        $date = $businessExpense->occurred_at?->toDateString() ?? now()->toDateString();
        $businessExpense->delete();

        return redirect()
            ->route('business-funds.index', ['date' => $date])
            ->with('success', 'Pengeluaran usaha berhasil dihapus.');
    }

    /** @return array{amount: numeric-string|int|float, category: string, payment_method: string, note: string, date: string} */
    private function validateExpense(Request $request): array
    {
        return $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'category' => ['required', Rule::in(array_keys(BusinessExpense::CATEGORIES))],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'note' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date', 'before_or_equal:today'],
        ], [
            'amount.required' => 'Nominal pengeluaran wajib diisi.',
            'category.required' => 'Kategori pengeluaran wajib dipilih.',
            'payment_method.required' => 'Metode pembayaran wajib dipilih.',
            'note.required' => 'Keterangan pengeluaran wajib diisi.',
            'date.before_or_equal' => 'Tanggal pengeluaran tidak boleh melewati hari ini.',
        ]);
    }

    private function expenseDate(string $date): Carbon
    {
        $value = Carbon::parse($date);

        return $value->isToday() ? now() : $value->endOfDay();
    }
}
