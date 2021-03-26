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

class TradeTracker_Connect_Model_Adminhtml_System_Config_Source_Frequency
{

    /**
     * @var
     */
    protected static $_options;

    const CRON_DAILY = 'D';
    const CRON_WEEKLY = 'W';
    const CRON_MONTHLY = 'M';

    /**
     * @return array
     */
    public function toOptionArray()
    {
        if (!self::$_options) {
            self::$_options = array(
                array('label' => Mage::helper('adminhtml')->__('Daily'), 'value' => self::CRON_DAILY),
                array('label' => Mage::helper('adminhtml')->__('Weekly'), 'value' => self::CRON_WEEKLY),
                array('label' => Mage::helper('adminhtml')->__('Monthly'), 'value' => self::CRON_MONTHLY),
            );
        }

        return self::$_options;
    }

}