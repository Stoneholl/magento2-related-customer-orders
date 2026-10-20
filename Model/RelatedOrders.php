<?php

declare(strict_types=1);

namespace Stoneholl\RelatedCustomerOrders\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Psr\Log\LoggerInterface;

/** Request-only presentation state, with no persistent cache or customer profile reads. */
class RelatedOrders
{
    /** @var ScopeConfigInterface */
    private $config;
    /** @var AuthorizationInterface */
    private $authorization;
    /** @var StoreRepositoryInterface */
    private $stores;
    /** @var OrderReader */
    private $reader;
    /** @var Identity */
    private $identity;
    /** @var GuestEmail */
    private $email;
    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        ScopeConfigInterface $config,
        AuthorizationInterface $authorization,
        StoreRepositoryInterface $stores,
        OrderReader $reader,
        Identity $identity,
        GuestEmail $email,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->authorization = $authorization;
        $this->stores = $stores;
        $this->reader = $reader;
        $this->identity = $identity;
        $this->email = $email;
        $this->logger = $logger;
    }

    public function isAllowed(): bool
    {
        return $this->flag('enabled')
            && $this->authorization->isAllowed('Magento_Sales::actions_view')
            && $this->authorization->isAllowed('Stoneholl_RelatedCustomerOrders::view');
    }

    public function getState(OrderInterface $order, int $page = 1): array
    {
        if (!$this->isAllowed()) {
            return ['status' => 'hidden'];
        }
        $storeId = (int) $order->getStoreId();
        $orderId = (int) $order->getEntityId();
        if ($storeId <= 0) {
            return ['status' => 'store_unavailable'];
        }
        if ($orderId <= 0) {
            return ['status' => 'unavailable'];
        }
        try {
            $this->stores->getById($storeId);
        } catch (NoSuchEntityException $exception) {
            return ['status' => 'store_unavailable'];
        } catch (\Exception $exception) {
            return $this->unavailable();
        }
        try {
            $mode = $this->identity->classify($order->getCustomerId(), $order->getCustomerIsGuest());
            if ($mode === 'unavailable') {
                return ['status' => 'identity_unavailable'];
            }
            if ($mode === 'account') {
                $result = $this->reader->readAccount($orderId, $storeId, (int) $order->getCustomerId(), $page);
            } else {
                if (!$this->flag('guest_email_matching')
                    || !$this->authorization->isAllowed('Stoneholl_RelatedCustomerOrders::guest_matches')
                ) {
                    return ['status' => 'guest_unavailable'];
                }
                $email = $this->email->normalize((string) $order->getCustomerEmail());
                if ($email === null) {
                    return ['status' => 'email_unavailable'];
                }
                $result = $this->reader->readGuest($orderId, $storeId, $email, $page);
            }
            return array_merge(['status' => 'ready', 'mode' => $mode], $result);
        } catch (\Exception $exception) {
            return $this->unavailable();
        }
    }

    private function unavailable(): array
    {
        // Exception text/context may contain SQL parameters or order snapshots. Never attach them.
        $this->logger->warning('STONEHOLL_RELATED_CUSTOMER_ORDERS_READ_FAILED');
        return ['status' => 'unavailable'];
    }

    private function flag(string $name): bool
    {
        return $this->config->isSetFlag(
            'stoneholl_related_customer_orders/general/' . $name,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT
        );
    }
}
