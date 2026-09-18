<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Models\GcashManualPayment;
use App\Models\BillingStatement;
use App\Models\Invoice;
use App\Models\OrganizationGcashSetting;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
class GcashController extends Controller {
    public function __construct(private AuditLogger $auditLogger) {}
    private function setting(Request $request): OrganizationGcashSetting { return OrganizationGcashSetting::firstOrNew(['user_id' => $request->user()->id]); }
    public function show(Request $request): JsonResponse { $s = $this->setting($request); return response()->json(['data' => ['settings' => ['enabled' => (bool) $s->enabled, 'account_name' => $s->account_name ?? '', 'mobile_number' => $s->mobile_number ?? '', 'instructions' => $s->instructions ?? '']]]); }
    public function update(Request $request): JsonResponse { $s = $this->setting($request); $s->fill($request->validate(['enabled' => ['boolean'], 'account_name' => ['nullable','string','max:190'], 'mobile_number' => ['nullable','string','max:40'], 'instructions' => ['nullable','string','max:2000']])); $s->save(); $this->auditLogger->record($request, 'gcash.settings.updated', $s, [], $s->toArray()); return $this->show($request); }
    public function payments(Request $request): JsonResponse { return response()->json(['data' => GcashManualPayment::with(['invoice.billingAccount.customer','statement.billingAccount.customer'])->latest()->paginate($request->integer('per_page', 20))]); }
    public function invoices(Request $request): JsonResponse { return response()->json(['data' => Invoice::with('billingAccount.customer')->where('balance_due_minor', '>', 0)->latest()->paginate($request->integer('per_page', 100))]); }
    public function statements(Request $request): JsonResponse { return response()->json(['data' => BillingStatement::with('billingAccount.customer')->where('status', 'open')->where('balance_due_minor', '>', 0)->latest()->paginate($request->integer('per_page', 100))]); }
    public function store(Request $request): JsonResponse { $data = $request->validate(['statement_id' => ['required','string'], 'reference_number' => ['required','string','max:100'], 'amount_minor' => ['required','integer','min:1'], 'transferred_on' => ['required','date'], 'notes' => ['nullable','string'], 'receipt' => ['nullable','file','mimes:jpg,jpeg,png,pdf','max:5120']]); $statement = BillingStatement::where('public_id',$data['statement_id'])->where('status','open')->firstOrFail(); $payment = new GcashManualPayment([...$data, 'user_id' => $request->user()->id, 'statement_id' => $statement->id, 'currency' => $statement->currency]); if ($request->hasFile('receipt')) $payment->receipt_path = $request->file('receipt')->store('gcash-receipts','public'); $payment->save(); $this->auditLogger->record($request, 'gcash.payment.submitted', $payment, [], ['reference_number' => $payment->reference_number]); return response()->json(['data' => $payment->load('statement.billingAccount.customer')], 201); }
    public function review(Request $request, int $id): JsonResponse { $payment = GcashManualPayment::findOrFail($id); $status = $request->validate(['status' => ['required','in:approved,rejected']])['status']; $payment->update(['status' => $status, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]); $this->auditLogger->record($request, 'gcash.payment.'.$status, $payment, [], ['status' => $status]); return response()->json(['data' => $payment->fresh()]); }
    public function receipt(Request $request, int $id) { $payment = GcashManualPayment::findOrFail($id); abort_unless($payment->receipt_path && Storage::disk('public')->exists($payment->receipt_path), 404); return Storage::disk('public')->response($payment->receipt_path); }
}
