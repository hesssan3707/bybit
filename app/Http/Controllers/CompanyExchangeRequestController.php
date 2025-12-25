<?php

namespace App\Http\Controllers;

use App\Models\CompanyExchangeRequest;
use App\Models\UserExchange;
use App\Services\Exchanges\ExchangeFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CompanyExchangeRequestController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Store a new company-provided exchange request (no user API credentials).
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'exchange_name' => 'required|string|in:' . implode(',', ExchangeFactory::getSupportedExchanges()),
            'account_types' => 'required|array|min:1',
            'account_types.*' => 'string|in:demo,live',
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return redirect()->route('exchanges.create')->withErrors($validator)->withInput();
        }

        try {
            $user = auth()->user();

            $selectedTypes = collect($request->input('account_types', []))
                ->map(function ($t) { return strtolower(trim($t)); })
                ->unique()
                ->values();

            $createdTypes = [];
            $skippedMessages = [];

            foreach ($selectedTypes as $type) {
                // Guardrail: prevent submitting request if user already has an active
                // exchange with the same name and same active account type (demo/live)
                $existingActive = UserExchange::query()
                    ->where('user_id', $user->id)
                    ->where('exchange_name', $request->exchange_name)
                    ->where('is_active', true)
                    ->get();

                $hasActiveSameType = $existingActive->contains(function ($ex) use ($type) {
                    return ($type === 'demo' && (bool)$ex->is_demo_active === true)
                        || ($type === 'live' && (bool)$ex->is_demo_active === false);
                });

                if ($hasActiveSameType) {
                    $skippedMessages[] = $type === 'demo'
                        ? 'برای نوع دمو قبلاً حساب فعال دارید'
                        : 'برای نوع واقعی قبلاً حساب فعال دارید';
                    continue;
                }

                // Prevent duplicate pending requests for same exchange and same type
                $existingPending = CompanyExchangeRequest::forUser($user->id)
                    ->where('exchange_name', $request->exchange_name)
                    ->where('account_type', $type)
                    ->pending()
                    ->first();

                if ($existingPending) {
                    $skippedMessages[] = $type === 'demo'
                        ? 'درخواست دمو شما قبلاً ثبت شده و در انتظار بررسی است'
                        : 'درخواست واقعی شما قبلاً ثبت شده و در انتظار بررسی است';
                    continue;
                }

                CompanyExchangeRequest::createRequest(
                    $user->id,
                    $request->exchange_name,
                    $type,
                    $request->reason
                );

                $createdTypes[] = $type;
            }

            if (count($createdTypes) > 0) {
                $createdLabel = collect($createdTypes)->map(function ($t) {
                    return $t === 'demo' ? 'دمو' : 'واقعی';
                })->implode(' و ');

                $message = 'درخواست استفاده از صرافی شرکت برای نوع(های) ' . $createdLabel . ' ثبت شد و در انتظار بررسی مدیر است.';

                if (!empty($skippedMessages)) {
                    $message .= ' (' . implode('، ', $skippedMessages) . ')';
                }

                return redirect()->route('exchanges.index')
                    ->with('success', $message);
            }

            // If none created, return errors
            return redirect()->route('exchanges.create')
                ->withErrors(['exchange_name' => implode('، ', $skippedMessages) ?: 'هیچ درخواستی ثبت نشد'])
                ->withInput();
        } catch (\Exception $e) {
            Log::error('Company exchange request store failed: ' . $e->getMessage());
            return redirect()->route('exchanges.create')
                ->withErrors(['general' => 'خطا در ثبت درخواست. لطفاً دوباره تلاش کنید.'])
                ->withInput();
        }
    }

    /**
     * Withdraw a pending company-provided exchange request (user-initiated).
     */
    public function withdraw(CompanyExchangeRequest $requestItem)
    {
        try {
            if ($requestItem->user_id !== auth()->user()->getAccountOwner()->id) {
                abort(403, 'دسترسی غیرمجاز');
            }

            if ($requestItem->status !== 'pending') {
                return redirect()->route('exchanges.index')
                    ->withErrors(['msg' => 'امکان لغو درخواست وجود ندارد یا قبلاً بررسی شده است.']);
            }

            $requestItem->update([
                'status' => 'rejected',
                'processed_at' => now(),
                'processed_by' => null,
                'admin_notes' => 'لغو درخواست توسط کاربر',
            ]);

            return redirect()->route('exchanges.index')
                ->with('success', 'درخواست شرکت برای صرافی با موفقیت لغو شد.');

        } catch (\Exception $e) {
            Log::error('Company exchange request withdraw failed: ' . $e->getMessage());
            return redirect()->route('exchanges.index')
                ->withErrors(['general' => 'خطا در لغو درخواست. لطفاً دوباره تلاش کنید.']);
        }
    }

    /**
     * Soft delete a rejected company-provided exchange request (user-initiated).
     */
    public function destroy(CompanyExchangeRequest $requestItem)
    {
        try {
            if ($requestItem->user_id !== auth()->user()->getAccountOwner()->id) {
                abort(403, 'دسترسی غیرمجاز');
            }

            if ($requestItem->status !== 'rejected') {
                return redirect()->route('exchanges.index')
                    ->withErrors(['msg' => 'فقط درخواست‌های رد شده قابل حذف هستند.']);
            }

            $requestItem->delete();

            return redirect()->route('exchanges.index')
                ->with('success', 'درخواست رد شده حذف شد.');
        } catch (\Exception $e) {
            Log::error('Company exchange request delete failed: ' . $e->getMessage());
            return redirect()->route('exchanges.index')
                ->withErrors(['general' => 'خطا در حذف درخواست. لطفاً دوباره تلاش کنید.']);
        }
    }
}
