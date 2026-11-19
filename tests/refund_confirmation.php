<?php
require __DIR__.'/bootstrap.php';

// get_posts() in bootstrap.php always resolves to attendee #1 regardless of
// its meta_query (see bootstrap.php) — these tests exercise the confirmation
// logic itself, not WordPress's own meta_query matching, same limitation the
// rest of this suite already documents.

function confirm($m, $event) {
    return invoke($m, 'handle_refund_confirmation', $event);
}

// --- Whole-order refund: payout confirms TS -> cleared, CampTix notified ---
fixture(); $m = method();
update_post_meta(1, 'tix_payment_token', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');
update_post_meta(1, 'tix_transaction_id', 'mock-tid');
update_post_meta(1, '_shashura_refund_hold', array(
    'phase' => 'reconciliation_required', 'via' => 'full',
    'result' => array('refund_transaction_id' => 'mock-refund', 'transaction_id' => 'mock-tid', 'refund_transaction_details' => array()),
));
$res = confirm($m, array('tid' => 'mock-tid', 'payout' => array('status' => 'TS')));
check($res->status === 200 && ($res->data['state'] ?? '') === 'refunded', 'confirmed full-refund payout reports refunded');
check(!get_post_meta(1, '_shashura_refund_hold', true), 'hold cleared after confirmed full refund');
check(count($GLOBALS['camptix']->reports) === 1 && $GLOBALS['camptix']->reports[0][1] === CampTix_Plugin::PAYMENT_STATUS_REFUNDED, 'CampTix replayed as refunded, non-interactively');

// --- Whole-order refund: payout confirms FAILED -> hold stays, no replay ---
fixture(); $m = method();
update_post_meta(1, 'tix_payment_token', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');
update_post_meta(1, '_shashura_refund_hold', array(
    'phase' => 'reconciliation_required', 'via' => 'full',
    'result' => array('refund_transaction_id' => 'mock-refund'),
));
$res = confirm($m, array('tid' => 'mock-tid', 'payout' => array('status' => 'FAILED', 'message' => 'Payee not found')));
check(($res->data['state'] ?? '') === 'still_held', 'unconfirmed payout keeps the order held');
$hold = get_post_meta(1, '_shashura_refund_hold', true);
check(is_array($hold) && 'reconciliation_required' === $hold['phase'], 'hold phase unchanged while unconfirmed');
check(!$GLOBALS['camptix']->reports, 'no CampTix replay while payout remains unconfirmed');

// --- Partial refund: payout confirms TS -> hold cleared, CampTix NOT mutated ---
fixture(); $m = method();
update_post_meta(1, 'tix_payment_token', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');
update_post_meta(1, '_shashura_refund_hold', array(
    'phase' => 'reconciliation_required', 'via' => 'partial',
    'result' => array('refund_transaction_id' => 'mock-refund'),
));
$res = confirm($m, array('tid' => 'mock-tid', 'payout' => array('status' => 'TS')));
check(($res->data['state'] ?? '') === 'cleared_partial', 'confirmed partial-refund payout clears without auto-mutating CampTix');
check(!get_post_meta(1, '_shashura_refund_hold', true), 'hold cleared after confirmed partial refund');
check(!$GLOBALS['camptix']->reports, 'partial refund confirmation never calls payment_result (per-ticket split is not reconstructable from the hold)');
check(count($GLOBALS['mail']) === 1, 'admin alerted to confirm the per-ticket status manually');

// --- No hold at all (already resolved, or a retried/duplicate delivery) ---
fixture(); $m = method();
update_post_meta(1, 'tix_payment_token', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');
$res = confirm($m, array('tid' => 'mock-tid', 'payout' => array('status' => 'TS')));
check(!empty($res->data['ignored']), 'no hold present is a quiet no-op, not an error');
check(!$GLOBALS['camptix']->reports && !$GLOBALS['mail'], 'no side effects when there is nothing to confirm');

// --- Missing tid in the event body ---
fixture(); $m = method();
$res = confirm($m, array('payout' => array('status' => 'TS')));
check(!empty($res->data['ignored']), 'a webhook body with no tid is ignored, not fatal');

// --- An event for an older refund must not release a newer refund's hold (#28) ---
function held_refund($at, $extra = array()) {
    fixture();
    update_post_meta(1, 'tix_payment_token', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');
    update_post_meta(1, 'tix_transaction_id', 'mock-tid');
    update_post_meta(1, '_shashura_refund_hold', array_merge(array(
        'phase' => 'reconciliation_required', 'via' => 'full', 'at' => $at, 'env' => 'sandbox',
        'result' => array('refund_transaction_id' => 'mock-refund-2', 'transaction_id' => 'mock-tid',
            'refund_transaction_details' => array('raw' => array('amount_refunded' => 60))),
    ), $extra));
    return method();
}
$now = time();

$m = held_refund($now);
$res = confirm($m, array('tid' => 'mock-tid', 'timestamp' => gmdate('c', $now - 3600), 'payout' => array('status' => 'TS')));
check(!empty($res->data['ignored']) && is_array(get_post_meta(1, '_shashura_refund_hold', true)) && !$GLOBALS['camptix']->reports, 'an event older than the held refund request is ignored and the hold stays');

$m = held_refund($now);
$res = confirm($m, array('tid' => 'mock-tid', 'timestamp' => $now - 3600, 'payout' => array('status' => 'TS')));
check(!empty($res->data['ignored']) && is_array(get_post_meta(1, '_shashura_refund_hold', true)), 'numeric timestamps are compared the same way');

$m = held_refund($now);
$res = confirm($m, array('tid' => 'mock-tid', 'refund_ref' => 'mock-refund-1', 'payout' => array('status' => 'TS')));
check(!empty($res->data['ignored']) && is_array(get_post_meta(1, '_shashura_refund_hold', true)), 'an event for a different refund reference is ignored');

$m = held_refund($now);
$res = confirm($m, array('tid' => 'mock-tid', 'amount_refunded' => 40, 'payout' => array('status' => 'TS')));
check(!empty($res->data['ignored']) && is_array(get_post_meta(1, '_shashura_refund_hold', true)), 'an event for a different refunded amount is ignored');

$m = held_refund($now);
$res = confirm($m, array('tid' => 'mock-tid', 'environment' => 'production', 'payout' => array('status' => 'TS')));
check(!empty($res->data['ignored']) && is_array(get_post_meta(1, '_shashura_refund_hold', true)), 'an event from the other environment is ignored');

$m = held_refund($now);
$res = confirm($m, array('tid' => 'mock-tid', 'environment' => 'sandbox', 'timestamp' => gmdate('c', $now + 30), 'refund_ref' => 'mock-refund-2', 'amount_refunded' => 60, 'payout' => array('status' => 'TS')));
check(($res->data['state'] ?? '') === 'refunded' && !get_post_meta(1, '_shashura_refund_hold', true), 'a matching event still clears the hold and records the refund');

$m = held_refund($now);
$res = confirm($m, array('tid' => 'mock-tid', 'timestamp' => gmdate('c', $now - 30), 'payout' => array('status' => 'TS')));
check(($res->data['state'] ?? '') === 'refunded', 'a minute of clock difference is tolerated');
check(!$GLOBALS['wpdb']->locks, 'order lock released after a confirmation');

$m = held_refund($now);
$lock = invoke($m, 'lock', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');
$res = confirm($m, array('tid' => 'mock-tid', 'payout' => array('status' => 'TS')));
check(503 === $res->status && is_array(get_post_meta(1, '_shashura_refund_hold', true)), 'a confirmation arriving while the order is locked is retried later, not applied');
$m->unlock($lock);
