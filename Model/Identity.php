<?php

declare(strict_types=1);

namespace Stoneholl\RelatedCustomerOrders\Model;

/** Interpret order snapshots, never the current customer profile. Internal, not an API. */
class Identity
{
    public function classify($customerId, $guestFlag): string
    {
        if (in_array($guestFlag, [0, '0', false], true)
            && (is_int($customerId) || (is_string($customerId) && preg_match('/\A[0-9]+\z/D', $customerId)))
            && (int) $customerId > 0
        ) {
            return 'account';
        }
        if (in_array($guestFlag, [1, '1', true], true) && in_array($customerId, [null, 0, '0'], true)) {
            return 'guest';
        }
        return 'unavailable';
    }
}
