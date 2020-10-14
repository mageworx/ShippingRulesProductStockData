<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\ShippingRulesProductStockData\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\InventoryConfigurationApi\Api\GetStockItemConfigurationInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\GetProductSalableQtyInterface;
use Magento\InventorySalesApi\Api\StockResolverInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class UpdateProductStockData
 *
 * Updates product original extension attribute - stock item,
 * set actual values using MSI for further validation.
 *
 */
class UpdateProductStockData implements ObserverInterface
{
    private GetStockItemConfigurationInterface $getStockItemConfiguration;
    private GetProductSalableQtyInterface $productSalableQty;
    private StockResolverInterface $stockResolver;
    private StoreManagerInterface $storeManager;

    /**
     * UpdateProductStockData constructor.
     *
     * @param StoreManagerInterface $storeManager
     * @param GetProductSalableQtyInterface $productSalableQty
     * @param GetStockItemConfigurationInterface $getStockItemConfiguration
     * @param StockResolverInterface $stockResolver
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        GetProductSalableQtyInterface $productSalableQty,
        GetStockItemConfigurationInterface $getStockItemConfiguration,
        StockResolverInterface $stockResolver
    ) {
        $this->getStockItemConfiguration = $getStockItemConfiguration;
        $this->productSalableQty         = $productSalableQty;
        $this->stockResolver             = $stockResolver;
        $this->storeManager              = $storeManager;
    }

    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        $model   = $observer->getEvent()->getData('model');
        $product = $observer->getEvent()->getData('product');

        if (!$model instanceof \Magento\Quote\Model\Quote\Item) {
            return;
        }

        if (!$product instanceof \Magento\Catalog\Model\Product) {
            return;
        }

        if (!$product->getId()) {
            return;
        }

        $websiteId   = $this->storeManager->getStore($model->getStoreId())->getWebsiteId();
        $websiteCode = $this->storeManager->getWebsite($websiteId)->getCode();

        $stock   = $this->stockResolver->execute(SalesChannelInterface::TYPE_WEBSITE, $websiteCode);
        $stockId = $stock->getStockId();
        $stockItemActual = $this->getStockItemConfiguration->execute($product->getSku(), $stockId);
        $salableQty = $this->productSalableQty->execute($product->getSku(), $stockId);

        /** @var \Magento\CatalogInventory\Model\Stock\Item $stockItem */
        $stockItem = $product->getExtensionAttributes()->getStockItem();
        $stockItem->setQty($salableQty);

        $minQty    = $stockItemActual->getMinQty();
        if ($salableQty >= $minQty && $salableQty > 0) {
            $isInStock = true;
        } else {
            $isInStock = false;
        }
        $stockItem->setIsInStock($isInStock);
    }
}
