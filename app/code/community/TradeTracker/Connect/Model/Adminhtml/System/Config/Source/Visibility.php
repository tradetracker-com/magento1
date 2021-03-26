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

class TradeTracker_Connect_Model_Adminhtml_System_Config_Source_Visibility
{

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $type = array();
        $type[] = array('value' => '1', 'label' => Mage::helper('adminhtml')->__('Not Visible Individually'));
        $type[] = array('value' => '2', 'label' => Mage::helper('adminhtml')->__('Catalog'));
        $type[] = array('value' => '3', 'label' => Mage::helper('adminhtml')->__('Search'));
        $type[] = array('value' => '4', 'label' => Mage::helper('adminhtml')->__('Catalog, Search'));
        return $type;
    }

}