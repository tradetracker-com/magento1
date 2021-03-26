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

class TradeTracker_Connect_Block_Conversionpixel
    extends Mage_Core_Block_Template
{

    /**
     * @return mixed
     */
    public function getPixelData()
    {
        try {
            $orderId = Mage::getSingleton('checkout/session')->getLastRealOrderId();
            $order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
            return Mage::getModel('tradetracker/conversion')->getPixelData($order);
        } catch (\Exception $e) {
            Mage::helper('tradetracker')->addToLog('getPixelData', $e->getMessage());
            return array();
        }
    }

    /**
     *
     */
    protected function _construct()
    {
        parent::_construct();
        $enabled = Mage::getStoreConfig('tradetracker/general/enabled');
        $pixel = Mage::getStoreConfig('tradetracker/pixel/enabled');
        if ($enabled && $pixel) {
            $this->setTemplate('tradetracker/connect/conversionpixel.phtml');
        }
    }
}