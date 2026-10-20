<?php

declare(strict_types=1);

namespace Stoneholl\RelatedCustomerOrders\Model;

use Magento\Framework\App\ResourceConnection;

/** Bounded internal reader. Verify site-specific row restrictions before granting view permission. */
class OrderReader
{
    private const WINDOW = 1000;
    private const RESULTS = 10;

    /** @var ResourceConnection */
    private $resource;
    /** @var Identity */
    private $identity;
    /** @var GuestEmail */
    private $email;

    public function __construct(ResourceConnection $resource, Identity $identity, GuestEmail $email)
    {
        $this->resource = $resource;
        $this->identity = $identity;
        $this->email = $email;
    }

    public function readAccount(int $orderId, int $storeId, int $customerId, int $page = 1): array
    {
        $this->assertOrder($orderId, $storeId);
        if ($customerId <= 0) {
            throw new \InvalidArgumentException('A positive account identifier is required.');
        }
        $db = $this->resource->getConnection();
        $window = $db->fetchAll($this->candidateSql('customer_id', 'entity_id, store_id, customer_is_guest'), [
            'scope_id' => $customerId, 'order_id' => $orderId
        ]);
        $truncated = count($window) > self::WINDOW;
        $window = array_slice($window, 0, self::WINDOW);
        $ids = [];
        foreach ($window as $candidate) {
            if ((int) $candidate['store_id'] === $storeId
                && $this->identity->classify($customerId, $candidate['customer_is_guest']) === 'account'
            ) {
                $ids[] = (int) $candidate['entity_id'];
            }
        }
        $pagination = $this->paginate($ids, $page);
        $ids = $pagination['ids'];
        $rows = [];
        if ($ids) {
            $bind = $this->idBindings($ids);
            $select = $this->displaySelect(false)
                ->where($this->idCondition($bind))->where('store_id = :store_id')
                ->where('customer_id = :customer_id')->where('customer_is_guest = 0')
                ->order('entity_id DESC')->limit(self::RESULTS);
            $rows = $db->fetchAll($select, $bind + ['store_id' => $storeId, 'customer_id' => $customerId]);
        }
        return $this->result($rows, count($window), $truncated, $pagination);
    }

    public function readGuest(int $orderId, int $storeId, string $normalizedEmail, int $page = 1): array
    {
        $this->assertOrder($orderId, $storeId);
        if ($this->email->normalize($normalizedEmail) !== $normalizedEmail) {
            throw new \InvalidArgumentException('A normalized guest email is required.');
        }
        $db = $this->resource->getConnection();
        $window = $db->fetchAll($this->candidateSql('store_id', 'entity_id'), [
            'scope_id' => $storeId, 'order_id' => $orderId
        ]);
        $truncated = count($window) > self::WINDOW;
        $window = array_slice($window, 0, self::WINDOW);
        $ids = array_map('intval', array_column($window, 'entity_id'));
        $matches = [];
        if ($ids) {
            $bind = $this->idBindings($ids);
            $select = $db->select()->from($this->resource->getTableName('sales_order'), [
                'entity_id', 'customer_id', 'customer_is_guest', 'customer_email'
            ])->where($this->idCondition($bind))->where('store_id = :store_id')->order('entity_id DESC');
            foreach ($db->fetchAll($select, $bind + ['store_id' => $storeId]) as $candidate) {
                if ($this->isGuestMatch($candidate, $normalizedEmail)) {
                    $matches[] = (int) $candidate['entity_id'];
                }
            }
        }
        $pagination = $this->paginate($matches, $page);
        $matches = $pagination['ids'];
        $rows = [];
        if ($matches) {
            $bind = $this->idBindings($matches);
            $select = $this->displaySelect(true)
                ->where($this->idCondition($bind))->where('store_id = :store_id')
                ->where('(customer_id IS NULL OR customer_id = 0)')->where('customer_is_guest = 1')
                ->order('entity_id DESC')->limit(self::RESULTS);
            foreach ($db->fetchAll($select, $bind + ['store_id' => $storeId]) as $candidate) {
                // Recheck bytes in PHP, never through a case-insensitive database email comparison.
                if ($this->isGuestMatch($candidate, $normalizedEmail)) {
                    $rows[] = $candidate;
                }
            }
        }
        return $this->result($rows, count($window), $truncated, $pagination);
    }

    private function isGuestMatch(array $row, string $email): bool
    {
        return $this->identity->classify($row['customer_id'], $row['customer_is_guest']) === 'guest'
            && $this->email->normalize((string) $row['customer_email']) === $email;
    }

    private function candidateSql(string $field, string $columns): string
    {
        $db = $this->resource->getConnection();
        // All identifiers/projections are internal constants; values remain bound. InnoDB secondary indexes
        // include the PK suffix, allowing a descending candidate window without scanning other accounts/stores.
        $table = $db->quoteIdentifier($this->resource->getTableName('sales_order'));
        $index = $db->quoteIdentifier($this->resource->getIdxName('sales_order', [$field]));
        // phpcs:ignore Magento2.SQL.RawQuery.FoundRawSql
        return 'SELECT ' . $columns . ' FROM ' . $table . ' FORCE INDEX (' . $index . ')'
            . ' WHERE ' . $field . ' = :scope_id AND entity_id <> :order_id'
            . ' ORDER BY entity_id DESC LIMIT 1001';
    }

    private function displaySelect(bool $guest): \Magento\Framework\DB\Select
    {
        $fields = ['entity_id', 'increment_id', 'created_at', 'status'];
        if ($guest) {
            $fields = array_merge($fields, ['customer_id', 'customer_is_guest', 'customer_email']);
        }
        // Resolve the native Admin label in the same query; no per-row status model loads.
        return $this->resource->getConnection()->select()
            ->from(['main_table' => $this->resource->getTableName('sales_order')], $fields)
            ->joinLeft(
                ['order_status' => $this->resource->getTableName('sales_order_status')],
                'main_table.status = order_status.status',
                ['status_label' => 'label']
            );
    }

    private function idBindings(array $ids): array
    {
        $bind = [];
        foreach (array_values($ids) as $index => $id) {
            $bind['related_id_' . $index] = (int) $id;
        }
        return $bind;
    }

    private function idCondition(array $bind): string
    {
        return 'entity_id IN (:' . implode(', :', array_keys($bind)) . ')';
    }

    private function result(array $rows, int $searched, bool $truncated, array $pagination): array
    {
        $display = [];
        foreach ($rows as $row) {
            // No email or identity metadata can escape into presentation state, even in guest mode.
            $display[] = [
                'entity_id' => (int) $row['entity_id'],
                'increment_id' => (string) $row['increment_id'],
                'created_at' => (string) $row['created_at'],
                'status' => (string) ($row['status_label'] ?? $row['status'])
            ];
        }
        return ['rows' => $display, 'searched' => $searched, 'truncated' => $truncated,
            'matched' => $pagination['matched'], 'page' => $pagination['page'], 'pages' => $pagination['pages']];
    }

    private function paginate(array $ids, int $requestedPage): array
    {
        // Counts describe only this bounded request's eligible snapshots, never complete order history.
        $matched = count($ids);
        $pages = max(1, (int) ceil($matched / self::RESULTS));
        $page = min($pages, max(1, $requestedPage));
        return ['matched' => $matched, 'page' => $page, 'pages' => $pages,
            'ids' => array_slice($ids, ($page - 1) * self::RESULTS, self::RESULTS)];
    }

    private function assertOrder(int $orderId, int $storeId): void
    {
        if ($orderId <= 0 || $storeId <= 0) {
            throw new \InvalidArgumentException('A persisted order and storefront are required.');
        }
    }
}
