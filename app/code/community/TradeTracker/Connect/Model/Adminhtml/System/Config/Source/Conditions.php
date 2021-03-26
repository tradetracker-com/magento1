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

class TradeTracker_Connect_Model_Adminhtml_System_Config_Source_Conditions
{

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $type = array();
        $type[] = array('value' => '', 'label' => Mage::helper('tradetracker')->__(''));
        $type[] = array('value' => 'eq', 'label' => Mage::helper('tradetracker')->__('Equal'));
        $type[] = array('value' => 'neq', 'label' => Mage::helper('tradetracker')->__('Not equal'));
        $type[] = array('value' => 'gt', 'label' => Mage::helper('tradetracker')->__('Greater than'));
        $type[] = array('value' => 'gteq', 'label' => Mage::helper('tradetracker')->__('Greater than or equal to'));
        $type[] = array('value' => 'lt', 'label' => Mage::helper('tradetracker')->__('Less than'));
        $type[] = array('value' => 'lteg', 'label' => Mage::helper('tradetracker')->__('Less than or equal to'));
        $type[] = array('value' => 'in', 'label' => Mage::helper('tradetracker')->__('In'));
        $type[] = array('value' => 'nin', 'label' => Mage::helper('tradetracker')->__('Not in'));
        $type[] = array('value' => 'like', 'label' => Mage::helper('tradetracker')->__('Like'));
        $type[] = array('value' => 'empty', 'label' => Mage::helper('tradetracker')->__('Empty'));
        $type[] = array('value' => 'not-empty', 'label' => Mage::helper('tradetracker')->__('Not Empty'));
        return $type;
    }

}