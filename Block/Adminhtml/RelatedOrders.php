<?php

declare(strict_types=1);

namespace Stoneholl\RelatedCustomerOrders\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Block\Adminhtml\Order\View\Tab\Info;
use Stoneholl\RelatedCustomerOrders\Model\RelatedOrders as RelatedOrdersModel;

class RelatedOrders extends Template
{
    /** @var RelatedOrdersModel */
    private $relatedOrders;

    public function __construct(
        Context $context,
        RelatedOrdersModel $relatedOrders,
        array $data = []
    ) {
        $this->relatedOrders = $relatedOrders;
        parent::__construct($context, $data);
    }

    public function getRelatedOrdersState(): array
    {
        if (!$this->relatedOrders->isAllowed()) {
            return ['status' => 'hidden'];
        }
        $info = $this->getLayout()->getBlock('order_tab_info');
        if (!$info instanceof Info) {
            return ['status' => 'unavailable'];
        }
        try {
            $order = $info->getOrder();
            return $order instanceof OrderInterface
                ? $this->relatedOrders->getState($order, $this->getPageNumber()) : ['status' => 'unavailable'];
        } catch (\Exception $exception) {
            $this->_logger->warning('STONEHOLL_RELATED_CUSTOMER_ORDERS_CURRENT_ORDER_UNAVAILABLE');
            return ['status' => 'unavailable'];
        }
    }

    public function getOutputEscaper(): \Magento\Framework\Escaper
    {
        return $this->_escaper;
    }

    public function getOrderUrl(int $orderId): string
    {
        return $this->getUrl('sales/order/view', ['order_id' => $orderId]);
    }

    public function getPageNumber(): int
    {
        $page = $this->getRequest()->getParam('related_orders_page', 1);
        if (!is_int($page) && (!is_string($page) || !preg_match('/\A[1-9][0-9]{0,2}\z/D', $page))) {
            return 1;
        }
        return max(1, min(100, (int) $page));
    }

    public function getPageUrl(int $page): string
    {
        return $this->buildPageUrl(max(1, min(100, $page)));
    }

    public function getPagerActionUrl(): string
    {
        return $this->buildPageUrl(null);
    }

    public function getAjaxUrl(): string
    {
        $info = $this->getLayout()->getBlock('order_tab_info');
        $order = $info instanceof Info ? $info->getOrder() : null;
        return $order instanceof OrderInterface && (int) $order->getEntityId() > 0
            ? $this->getUrl('stoneholl_related_orders/order/page', ['order_id' => (int) $order->getEntityId()]) : '';
    }

    private function buildPageUrl(?int $page): string
    {
        // Only this already authorized order, a bounded numeric page and the native backend key.
        $info = $this->getLayout()->getBlock('order_tab_info');
        $order = $info instanceof Info ? $info->getOrder() : null;
        if (!$order instanceof OrderInterface || (int) $order->getEntityId() <= 0) {
            return '';
        }
        $params = ['order_id' => (int) $order->getEntityId(),
            '_fragment' => 'stoneholl-related-customer-orders-heading'];
        if ($page !== null) {
            $params['related_orders_page'] = $page;
        }
        return $this->getUrl('sales/order/view', $params);
    }

    /**
     * Never cache identity/permission/page-dependent Admin HTML, even if a layout supplies a lifetime.
     *
     * @return null
     */
    protected function getCacheLifetime()
    {
        return null;
    }

    public function getCreatedAtLabel(string $createdAt): string
    {
        try {
            // Database order timestamps are UTC; Magento supplies display timezone and Admin locale.
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $createdAt, new \DateTimeZone('UTC'));
            if (!$date || $date->format('Y-m-d H:i:s') !== $createdAt) {
                return (string) __('Unavailable');
            }
            return (string) $this->formatDate($date, \IntlDateFormatter::MEDIUM, true);
        } catch (\Exception $exception) {
            $this->_logger->warning('STONEHOLL_RELATED_CUSTOMER_ORDERS_DATE_UNAVAILABLE');
            return (string) __('Unavailable');
        }
    }
}
