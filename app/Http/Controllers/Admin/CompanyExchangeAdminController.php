<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompanyExchangeRequest;
use App\Models\UserExchange;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CompanyExchangeAdminController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'admin']);
    }

    /**
     * Show pending company-provided exchange requests.
     */
    public function pending()
    {
        $requests = CompanyExchangeRequest::pending()->orderByDesc('requested_at')->get();
        return view('admin.company-requests.pending', compact('requests'));
    }

    /**
     * Show all company-provided exchange requests.
     */
    public function all()
    {
        $requests = CompanyExchangeRequest::orderByDesc('requested_at')->paginate(20);
        return view('admin.company-requests.all', compact('requests'));
    }

    /**
     * Approve a company-provided exchange request and assign credentials.
     */
    public function approve(CompanyExchangeRequest $requestItem, Request $request)
    {
        $adminId = auth()->id();
        $accountType = $requestItem->account_type; // 'demo' or 'live'

        // Guardrail: prevent re-assignment if user already has an active exchange
        // of the same exchange_name and account type (demo/live)
        $existingActive = UserExchange::query()
            ->where('user_id', $requestItem->user_id)
            ->where('exchange_name', $requestItem->exchange_name)
            ->where('is_active', true)
            ->get();

        $hasActiveSameType = $existingActive->contains(function ($ex) use ($accountType) {
            // If requested type is demo, block when user's active mode is demo
            // If requested type is live, block when user's active mode is live
            return ($accountType === 'demo' && (bool)$ex->is_demo_active === true)
                || ($accountType === 'live' && (bool)$ex->is_demo_active === false);
        });

        if ($hasActiveSameType) {
            return redirect()->route('admin.company-requests.pending')
                ->with('error', 'کاربر قبلاً یک حساب فعال از همین نوع برای این صرافی دارد. ایجاد حساب جدید مجاز نیست.');
        }

        $rules = [
            'admin_notes' => 'nullable|string|max:500',
        ];

        if ($accountType === 'live') {
            $rules['api_key'] = 'required|string|min:5';
            $rules['api_secret'] = 'required|string|min:5';
        } else {
            $rules['demo_api_key'] = 'required|string|min:5';
            $rules['demo_api_secret'] = 'required|string|min:5';
        }

        $validated = $request->validate($rules);

        try {
            $userExchangeData = [
                'user_id' => $requestItem->user_id,
                'exchange_name' => $requestItem->exchange_name,
                'is_demo_active' => $accountType === 'demo',
                'status' => 'pending',
                'activation_requested_at' => now(),
                'admin_notes' => $validated['admin_notes'] ?? null,
            ];

            if ($accountType === 'live') {
                $userExchangeData['api_key'] = $validated['api_key'];
                $userExchangeData['api_secret'] = $validated['api_secret'];
            } else {
                $userExchangeData['demo_api_key'] = $validated['demo_api_key'];
                $userExchangeData['demo_api_secret'] = $validated['demo_api_secret'];
            }

            $userExchange = UserExchange::create($userExchangeData);
            $userExchange->activate($adminId, $validated['admin_notes'] ?? null);

            $requestItem->update([
                'status' => 'approved',
                'processed_at' => now(),
                'processed_by' => $adminId,
                'assigned_user_exchange_id' => $userExchange->id,
                'admin_notes' => $validated['admin_notes'] ?? null,
            ]);

            return redirect()->route('admin.company-requests.pending')
                ->with('success', 'درخواست با موفقیت تأیید شد و حساب شرکت به کاربر اختصاص یافت.');
        } catch (\Exception $e) {
            Log::error('Company request approve failed: ' . $e->getMessage(), [
                'request_id' => $requestItem->id,
            ]);
            return redirect()->route('admin.company-requests.pending')
                ->with('error', 'خطا در تأیید درخواست. لطفاً دوباره تلاش کنید.');
        }
    }

    /**
     * Reject a company-provided exchange request.
     */
    public function reject(CompanyExchangeRequest $requestItem, Request $request)
    {
        $validated = $request->validate([
            'admin_notes' => 'nullable|string|max:500',
        ]);

        $requestItem->update([
            'status' => 'rejected',
            'processed_at' => now(),
            'processed_by' => auth()->id(),
            'admin_notes' => $validated['admin_notes'] ?? null,
        ]);

        return redirect()->route('admin.company-requests.pending')
            ->with('success', 'درخواست رد شد.');
    }
}
