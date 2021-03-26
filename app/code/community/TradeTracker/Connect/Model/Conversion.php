<?php
/*
 *  TradeTracker
 *
 *  NOTICE OF LICENSE
 *
 *  This source file is subject to the Open Software License (OSL 3.0)
 *  that is bundled with this package in the file LICENSE.txt.
 *  It is also available through the world-wide-web at this URL:
 *  http://opensource.org/licenses/osl-3.0.php
 *  If you did not receive a copy of the license and are unable to
 *  obtain it through the world-wide-web, please send an email
 *  to license@magentocommerce.com so we can send you a copy immediately.
 *
 *  @category    TradeTracker
 *  @package     TradeTracker_Connect
 *  @copyright   Copyright (c) 2021 TradeTracker (http://www.tradetracker.com)
 *  @license     http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

class TradeTracker_Connect_Model_Conversion extends Mage_Core_Helper_Abstract
{

    /**
     * @param Mage_Sales_Model_Order $order
     *
     * @return array|bool
     */
    public function getPixelData($order)
    {
        $pixeldata = array();
        try {
            $defaultId = Mage::getStoreConfig('tradetracker/pixel/product_id');
            $campaignId = Mage::getStoreConfig('tradetracker/pixel/campaign_id');
            if ($defaultId && $campaignId) {
                $pixeldata['campaign_id'] = $campaignId;
                $pixeldata['transaction_id'] = $order->getIncrementId();
                $subtotal = ($order->getGrandTotal() - $order->getTaxAmount() - $order->getShippingAmount());
                $pixeldata['transactions'][$defaultId]['amount'] = number_format($subtotal, 2, '.', '');
                $pixeldata['email'] = $order->getCustomerEmail();
                $pixeldata['currency'] = $order->getOrderCurrencyCode();
                foreach ($order->getAllVisibleItems() as $item) {
                    /** @var Mage_Catalog_Model_Product $product */
                    $product = Mage::getModel('catalog/product')->setId($item->getProductId());
                    $categoryIds = $product->getResource()->getCategoryIds($product);
                    foreach ($categoryIds as $categoryId) {
                        $ttProductId = Mage::getResourceModel('catalog/category')
                            ->getAttributeRawValue(
                                $categoryId, 'tradetracker_product_id',
                                Mage::app()->getStore()->getId()
                            );
                        if (!empty($ttProductId)) {
                            $pixeldata['transactions'][$defaultId]['amount'] -= $item['base_row_total'];
                            if (!empty($pixeldata['transactions'][$ttProductId]['amount'])) {
                                $pixeldata['transactions'][$ttProductId]['amount'] += $item['base_row_total'];
                            } else {
                                $pixeldata['transactions'][$ttProductId]['amount'] = $item['base_row_total'];
                            }

                            break;
                        }
                    }
                }

                return $pixeldata;
            }
        } catch (Exception $e) {
            Mage::log($e->getMessage());
        }

        return $pixeldata;
    }

}
        