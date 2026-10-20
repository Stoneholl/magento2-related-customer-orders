<?php

declare(strict_types=1);

namespace Stoneholl\RelatedCustomerOrders\Model\Core;

use Stoneholl\Core\Api\ProductProviderInterface;
use Stoneholl\Core\Model\Data\ProductDescriptor;

class ProductProvider implements ProductProviderInterface
{
    public function getProducts(): array
    {
        return [new ProductDescriptor(
            'related-customer-orders',
            'Stoneholl Related Customer Orders',
            ['Stoneholl_RelatedCustomerOrders'],
            ['stoneholl/module-related-customer-orders']
        )];
    }
}
