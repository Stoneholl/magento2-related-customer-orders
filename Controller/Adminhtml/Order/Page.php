<?php

declare(strict_types=1);

namespace Stoneholl\RelatedCustomerOrders\Controller\Adminhtml\Order;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Sales\Controller\Adminhtml\Order\View;
use Stoneholl\RelatedCustomerOrders\Block\Adminhtml\RelatedOrders;

class Page extends View implements HttpGetActionInterface
{
    /**
     * Render only the helper using the native order initialization and layout context.
     *
     * @return \Magento\Framework\Controller\Result\Raw
     */
    public function execute()
    {
        $this->getResponse()->setNoCacheHeaders();
        $result = $this->resultRawFactory->create();
        $result->setHeader('Content-Type', 'text/html; charset=UTF-8', true);
        if (!$this->_initOrder()) {
            return $result->setHttpResponseCode(404)->setContents('');
        }
        $page = $this->resultPageFactory->create();
        $page->addHandle('sales_order_view');
        $block = $page->getLayout()->getBlock('stoneholl.related_customer_orders');
        return $block instanceof RelatedOrders
            ? $result->setContents($block->toHtml()) : $result->setHttpResponseCode(404)->setContents('');
    }

    /**
     * Both permissions are required before the current order is loaded.
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return parent::_isAllowed()
            && $this->_authorization->isAllowed('Stoneholl_RelatedCustomerOrders::view');
    }
}
