<?php

namespace App\Http\Controllers;

use App\Models\BookingPayment;
use App\Models\CartRecords;
use App\Models\Income;
use App\Models\Journal;
use App\Models\Sales;
use App\Models\Session;
use App\Models\Walkin;
use App\Models\Wallet;
use App\Services\SessionSlotService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BookingPaymentController extends Controller
{
    public function __construct(private readonly SessionSlotService $slotService) {}

    public function create(Request $request): JsonResponse
    {
        $data = $request->validate(['cart_id' => 'required|integer']);
        $customer = $request->user()->customer;
        abort_unless($customer, 403, 'A customer account is required.');

        if (!$this->gatewayReady()) {
            return response()->json(['message' => 'Online payment is not configured.'], 503);
        }

        $cart = CartRecords::with(['treatment', 'employee', 'room.branch'])->where('id', $data['cart_id'])
            ->where('customer_id', $customer->id)->firstOrFail();
        abort_unless($cart->session_type === 'walkin' && $cart->treatment && $cart->employee && $cart->room, 422, 'This cart item is not a scheduled treatment booking.');
        abort_unless((int) $cart->employee->branch_id === (int) $cart->room->branch_id, 422, 'The therapist and room must belong to the same branch.');

        $start = Carbon::parse(Carbon::parse($cart->session_date)->toDateString() . ' ' . $cart->session_time, config('app.timezone'));
        abort_if($start->isPast(), 422, 'Choose a future appointment time.');
        $end = $start->copy()->addMinutes((int) $cart->treatment->duration);

        [$order, $alreadyExists] = DB::transaction(function () use ($cart, $customer, $start, $end) {
            $existing = BookingPayment::where('cart_record_id', $cart->id)
                ->whereIn('status', ['creating', 'pending'])->where('expires_at', '>', now())->lockForUpdate()->first();
            if ($existing) return [$existing, true];

            // Lock the employee and beds during hold selection so simultaneous
            // checkouts cannot reserve the same therapist or bed.
            $cart->employee()->lockForUpdate()->first();
            $beds = $cart->room->bed()->orderBy('id')->lockForUpdate()->get();
            $bed = $beds->first(function ($candidate) use ($cart, $start, $end) {
                $sessionConflict = Session::where('bed_id', $candidate->id)
                    ->where('date', $start->toDateString())
                    ->whereIn('status', ['waiting', 'ongoing'])
                    ->where('start', '<', $end->format('H:i:s'))
                    ->where('end', '>', $start->format('H:i:s'))->exists();
                $holdConflict = BookingPayment::where('bed_id', $candidate->id)
                    ->where('session_date', $start->toDateString())
                    ->whereIn('status', ['creating', 'pending'])->where('expires_at', '>', now())
                    ->where('session_start', '<', $end->format('H:i:s'))
                    ->where('session_end', '>', $start->format('H:i:s'))->exists();
                return !$sessionConflict && !$holdConflict;
            });
            abort_unless($bed, 409, 'That room has no available bed for the selected time.');

            $employeeConflict = Session::where('employee_id', $cart->employee_id)
                ->where('date', $start->toDateString())->whereIn('status', ['waiting', 'ongoing'])
                ->where('start', '<', $end->format('H:i:s'))->where('end', '>', $start->format('H:i:s'))->exists()
                || BookingPayment::where('employee_id', $cart->employee_id)->where('session_date', $start->toDateString())
                    ->whereIn('status', ['creating', 'pending'])->where('expires_at', '>', now())
                    ->where('session_start', '<', $end->format('H:i:s'))->where('session_end', '>', $start->format('H:i:s'))->exists();
            abort_if($employeeConflict, 409, 'The selected therapist is no longer available for that time.');

            $order = BookingPayment::create([
                'customer_id' => $customer->id,
                'branch_id' => $cart->room->branch_id,
                'cart_record_id' => $cart->id,
                'treatment_id' => $cart->treatment_id,
                'employee_id' => $cart->employee_id,
                'room_id' => $cart->room_id,
                'bed_id' => $bed->id,
                'session_date' => $start->toDateString(),
                'session_start' => $start->format('H:i:s'),
                'session_end' => $end->format('H:i:s'),
                'amount' => (int) $cart->treatment->price,
                'reference_id' => 'SPA-' . Str::uuid(),
                'status' => 'creating',
                'expires_at' => now()->addMinutes(30),
            ]);

            return [$order, false];
        });

        if ($order->gateway_session_id && $order->payment_link_url) {
            return response()->json($this->checkoutPayload($order));
        }
        if ($alreadyExists) return response()->json(['message' => 'Checkout is being prepared. Please retry shortly.'], 409);

        $name = preg_replace('/[^A-Za-z0-9 ]/', '', (string) $customer->name) ?: 'Spa customer';
        $parts = preg_split('/\s+/', trim($name), 2) ?: ['Spa customer'];
        $response = Http::withBasicAuth(config('services.xendit.secret_key'), '')
            ->acceptJson()->asJson()->timeout(20)
            ->post(rtrim(config('services.xendit.api_url'), '/') . '/sessions', [
                'reference_id' => $order->reference_id,
                'session_type' => 'PAY',
                'mode' => 'PAYMENT_LINK',
                'amount' => (int) $order->amount,
                'currency' => 'IDR',
                'country' => 'ID',
                'capture_method' => 'AUTOMATIC',
                'locale' => 'id',
                'customer' => [
                    'reference_id' => 'customer' . $customer->id,
                    'type' => 'INDIVIDUAL',
                    'email' => $customer->email,
                    'individual_detail' => ['given_names' => $parts[0], 'surname' => $parts[1] ?? ''],
                ],
                'items' => [[
                    'reference_id' => 'treatment' . $order->treatment_id,
                    'name' => $cart->treatment->name,
                    'description' => 'Spa appointment at ' . $cart->room->branch->name,
                    'type' => 'PHYSICAL_SERVICE',
                    'category' => 'SPA_TREATMENT',
                    'net_unit_amount' => (int) $order->amount,
                    'quantity' => 1,
                    'currency' => 'IDR',
                ]],
                'description' => 'Spa appointment: ' . $cart->treatment->name,
                'success_return_url' => $this->returnUrl(config('services.xendit.success_url'), $order->id, 'success'),
                'cancel_return_url' => $this->returnUrl(config('services.xendit.cancel_url'), $order->id, 'cancelled'),
                'metadata' => ['booking_payment_id' => (string) $order->id],
            ]);

        if (!$response->successful() || !$response->json('payment_session_id') || !$response->json('payment_link_url')) {
            $order->update(['status' => 'failed']);
            Log::error('Xendit payment session creation failed.', ['status' => $response->status(), 'booking_payment_id' => $order->id]);
            return response()->json(['message' => 'Could not start online payment. Please try again.'], 502);
        }

        $order->update([
            'gateway_session_id' => $response->json('payment_session_id'),
            'payment_link_url' => $response->json('payment_link_url'),
            'status' => 'pending',
            'expires_at' => $response->json('expires_at') ? Carbon::parse($response->json('expires_at')) : now()->addMinutes(30),
        ]);

        return response()->json($this->checkoutPayload($order));
    }

    public function status(Request $request, BookingPayment $bookingPayment): JsonResponse
    {
        abort_unless((int) $bookingPayment->customer_id === (int) $request->user()->customer?->id, 404);
        return response()->json([
            'id' => $bookingPayment->id,
            'status' => $bookingPayment->status,
            'session_id' => $bookingPayment->session_id,
            'sales_id' => $bookingPayment->sales_id,
        ]);
    }

    public function webhook(Request $request): JsonResponse
    {
        $secret = (string) config('services.xendit.webhook_token');
        $provided = (string) $request->header('x-callback-token');
        abort_if($secret === '' || $provided === '' || !hash_equals($secret, $provided), 403, 'Invalid webhook token.');

        $data = $request->input('data', []);
        $order = BookingPayment::where('gateway_session_id', $data['payment_session_id'] ?? '')->first();
        abort_unless($order, 404, 'Booking payment not found.');
        abort_unless(($data['reference_id'] ?? null) === $order->reference_id, 422, 'Payment reference mismatch.');

        if (($request->input('event') ?? '') === 'payment_session.expired') {
            if ($order->status === 'pending') $order->update(['status' => 'expired']);
            return response()->json(['received' => true]);
        }

        abort_unless(($request->input('event') ?? '') === 'payment_session.completed', 422, 'Unsupported payment event.');
        abort_unless(($data['session_type'] ?? '') === 'PAY' && ($data['mode'] ?? '') === 'PAYMENT_LINK' && ($data['status'] ?? '') === 'COMPLETED', 422, 'Payment is not complete.');
        abort_unless(($data['country'] ?? '') === 'ID' && ($data['currency'] ?? '') === 'IDR' && (int) ($data['amount'] ?? -1) === (int) $order->amount, 422, 'Payment amount or currency mismatch.');
        abort_unless((bool) ($data['payment_id'] ?? null), 422, 'Payment reference is missing.');

        $this->fulfill($order, (string) ($data['payment_id'] ?? ''));
        return response()->json(['received' => true]);
    }

    private function fulfill(BookingPayment $payment, string $gatewayPaymentId): void
    {
        DB::transaction(function () use ($payment, $gatewayPaymentId) {
            $order = BookingPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if (in_array($order->status, ['paid', 'refund_required'], true)) return;

            $this->slotService->lockResources((int) $order->employee_id, (int) $order->bed_id);
            $expired = $order->status !== 'pending' || !$order->expires_at?->isFuture();
            $conflict = !$expired && $this->slotService->hasConflict(
                (int) $order->employee_id,
                (int) $order->bed_id,
                $order->session_date,
                $order->session_start,
                $order->session_end,
                ignorePaymentId: $order->id,
            );

            if ($expired || $conflict) {
                $order->update(['status' => 'refund_required', 'gateway_payment_id' => $gatewayPaymentId]);
                Log::critical('Captured booking payment could not be fulfilled.', [
                    'booking_payment_id' => $order->id,
                    'reason' => $expired ? 'hold_expired' : 'slot_conflict',
                ]);
                return;
            }

            $branch = \App\Models\Branch::findOrFail($order->branch_id);
            $treatment = \App\Models\Treatment::findOrFail($order->treatment_id);
            $wallet = Wallet::findOrFail($branch->cash_account);
            abort_unless($branch->walkin_account, 500, 'Branch sales account is not configured.');

            $date = now()->toDateString();
            $reference = 'BOOK-' . str_pad((string) $order->id, 10, '0', STR_PAD_LEFT);
            $income = Income::create([
                'journal_reference' => $reference,
                'date' => $date,
                'partner_type' => 'customer',
                'partner' => $order->customer_id,
                'description' => 'Online booking ' . $order->id,
            ]);
            $sale = Sales::create([
                'branch_id' => $order->branch_id,
                'customer_id' => $order->customer_id,
                'employee_id' => $order->employee_id,
                'date' => $date,
                'time' => now()->format('H:i:s'),
                'subtotal' => $order->amount,
                'discount' => 0,
                'rounding' => 0,
                'total' => $order->amount,
                'income_id' => $income->id,
            ]);
            $record = $sale->records()->create([
                'treatment_id' => $order->treatment_id,
                'quantity' => 1,
                'price' => $order->amount,
                'discount' => 0,
                'redeem_type' => 'walkin',
                'total_price' => $order->amount,
                'description' => 'Paid appointment booking ' . $order->id,
            ]);

            $session = Session::create([
                'sales_id' => $sale->id,
                'order_time' => now()->format('H:i:s'),
                'bed_id' => $order->bed_id,
                'customer_id' => $order->customer_id,
                'payment' => 'online',
                'date' => $order->session_date,
                'start' => $order->session_start,
                'end' => $order->session_end,
                'employee_id' => $order->employee_id,
                'treatment_id' => $order->treatment_id,
                'status' => 'waiting',
            ]);
            Walkin::create([
                'treatment_id' => $order->treatment_id,
                'customer_id' => $order->customer_id,
                'sales_id' => $sale->id,
                'session_id' => $session->id,
            ]);

            $income->items()->create([
                'type' => 'penjualan', 'transaction' => $record->id,
                'amount' => $order->amount, 'description' => 'Online spa appointment: ' . $treatment->name,
            ]);
            $income->payments()->create([
                'wallet_id' => $wallet->id, 'amount' => $order->amount,
                'type' => 'qr', 'description' => 'Xendit ' . $gatewayPaymentId,
            ]);
            $journal = Journal::create([
                'reference' => $reference, 'date' => $date,
                'description' => 'Online booking ' . $order->id . ' - ' . $treatment->name,
            ]);
            $journal->records()->createMany([
                ['account_id' => $wallet->account_id, 'debit' => $order->amount, 'credit' => 0, 'description' => 'Xendit payment'],
                ['account_id' => $branch->walkin_account, 'debit' => 0, 'credit' => $order->amount, 'description' => $treatment->name],
            ]);

            $order->update([
                'status' => 'paid', 'gateway_payment_id' => $gatewayPaymentId,
                'sales_id' => $sale->id, 'session_id' => $session->id,
            ]);
            if ($order->cart_record_id) CartRecords::whereKey($order->cart_record_id)->delete();
        }, 3);
    }

    private function gatewayReady(): bool
    {
        $success = (string) config('services.xendit.success_url');
        $cancel = (string) config('services.xendit.cancel_url');
        return (bool) config('services.xendit.secret_key')
            && parse_url($success, PHP_URL_SCHEME) === 'https'
            && parse_url($cancel, PHP_URL_SCHEME) === 'https';
    }

    private function checkoutPayload(BookingPayment $order): array
    {
        return ['id' => $order->id, 'status' => $order->status, 'payment_link_url' => $order->payment_link_url, 'amount' => $order->amount];
    }

    private function returnUrl(string $baseUrl, int $orderId, string $result): string
    {
        $separator = str_contains($baseUrl, '?') ? '&' : '?';
        return $baseUrl . $separator . http_build_query(['booking_payment_id' => $orderId, 'payment' => $result]);
    }
}
