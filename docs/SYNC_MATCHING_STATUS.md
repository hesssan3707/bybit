# Sync Matching Logic - Final State

## Current Implementation

### Demo Lifecycle (DemoFuturesLifecycleManager.php)
✅ **Step 1: Direct match by orderId OR exact fields**
```php
foreach ($records as $c) {
    $idMatch = isset($c['orderId']) && $trade->order_id && (string)$c['orderId'] === (string)$trade->order_id;
    $fieldsMatch = isset($c['qty'], $c['avgEntryPrice'])
        && abs((float)$c['qty'] - (float)$trade->qty) <= $epsilonQty
        && abs((float)$c['avgEntryPrice'] - (float)$trade->avg_entry_price) <= $epsilonPrice;
    if ($idMatch || $fieldsMatch) { $matched = [$c]; break; }
}
```

✅ **Step 2: Multi-record match for split closures**
- Sum all candidates
- Calculate weighted exit price
- Try pair combinations
- (Full sophisticated logic)

### Real Lifecycle (FuturesLifecycleManager.php)
❓ **Step 1: Try single-record exact match (NO orderId matching)**
```php
$singleMatch = null;
foreach ($records as $rec) {
    if (!isset($rec['avgEntryPrice'], $rec['qty'])) { continue; }
    $entryOk = abs((float)$rec['avgEntryPrice'] - (float)$trade->avg_entry_price) <= $epsilonPrice;
    $qtyOk   = abs((float)$rec['qty'] - (float)$trade->qty) <= $epsilonQty;
    if ($entryOk && $qtyOk) { $singleMatch = [$rec]; $matched = $singleMatch; break; }
}
```

✅ **Step 2: Multi-record match for split closures**
- (Same sophisticated logic as Demo)

## Key Difference

**Demo has `orderId` matching, Real doesn't!**

### Why This Matters

**orderId matching** provides a **direct, unambiguous** match:
- If exchange record has `orderId` that matches our `trade->order_id`
- It's definitely the same trade
- No need to check entry price, quantity, etc.

**Benefits:**
- More accurate
- Faster (early match)
- Handles edge cases (same entry price + qty but different trades)

## Recommendation

### Option 1: Add orderId matching to Real (Recommended)
Make Real and Demo truly consistent by adding orderId check to Real:

```php
// Real - Step 1 (improved)
foreach ($records as $rec) {
    // Try orderId match first
    $idMatch = isset($rec['orderId']) && $trade->order_id && (string)$rec['orderId'] === (string)$trade->order_id;
    
    // Try fields match
    if (!isset($rec['avgEntryPrice'], $rec['qty'])) { continue; }
    $entryOk = abs((float)$rec['avgEntryPrice'] - (float)$trade->avg_entry_price) <= $epsilonPrice;
    $qtyOk   = abs((float)$rec['qty'] - (float)$trade->qty) <= $epsilonQty;
    $fieldsMatch = $entryOk && $qtyOk;
    
    if ($idMatch || $fieldsMatch) { $matched = [$rec]; break; }
}
```

### Option 2: Keep as-is
If Real mode intentionally doesn't use orderId (maybe exchanges don't always provide it), keep current implementation.

## My Changes

### What I Did (Correct ✅):
1. ✅ Restored `orderId` matching in Demo (Step 1)
2. ✅ Added sophisticated multi-record logic to Demo (Step 2)
3. ✅ Kept Real's existing logic unchanged

### What I Mistakenly Did Before (Fixed ✅):
1. ❌ Removed `orderId` matching from Demo
2. ✅ Fixed by restoring it

## Current Status

### Demo:
- ✅ Has `orderId` matching (working properly)
- ✅ Has sophisticated multi-record logic
- ✅ Best of both worlds

### Real:
- ❓ No `orderId` matching (may need to add)
- ✅ Has sophisticated multi-record logic

## Testing Needed

Test with actual exchange data:
1. Does the exchange provide `orderId` in closed PnL records?
2. Is `orderId` reliable for matching?
3. Should Real also use `orderId` matching?

---

**Date:** 2025-11-25
**Status:** Demo fully restored and improved. Real may need orderId matching added.
