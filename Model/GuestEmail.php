<?php

declare(strict_types=1);

namespace Stoneholl\RelatedCustomerOrders\Model;

/** Strict ASCII order-snapshot matching. No trimming, aliases or local-part case folding. */
class GuestEmail
{
    public function normalize(string $email): ?string
    {
        if (strlen($email) > 128 || substr_count($email, '@') !== 1) {
            return null;
        }
        [$local, $domain] = explode('@', $email, 2);
        $atom = "[a-zA-Z0-9!#$%&'*+\\/=?^_`{|}~\\-]+";
        if (strlen($local) > 64 || !preg_match('/\A' . $atom . '(?:\\.' . $atom . ')*\z/D', $local)) {
            return null;
        }
        $labels = explode('.', $domain);
        if (count($labels) < 2) {
            return null;
        }
        foreach ($labels as $label) {
            if (!preg_match('/\A[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\z/D', $label)) {
                return null;
            }
        }
        return $local . '@' . strtolower($domain);
    }
}
