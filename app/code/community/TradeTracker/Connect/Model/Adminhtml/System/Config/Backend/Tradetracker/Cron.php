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

class TradeTracker_Connect_Model_Adminhtml_System_Config_Backend_Tradetracker_Cron
    extends Mage_Core_Model_Config_Data
{

    const CRON_MODEL_PATH = 'tradetracker/generate/cron_schedule';
    const CRON_STRING_PATH = 'crontab/jobs/tradetracker_generate/schedule/cron_expr';
    const CRON_RUNMODEL_PATH = 'crontab/jobs/tradetracker_generate/run/model';

    /**
     * @throws Exception
     */
    protected function _afterSave()
    {
        $time = $this->getData('groups/generate/fields/time/value');
        $frequency = $this->getData('groups/generate/fields/frequency/value');
        $frequencyDaily = TradeTracker_Connect_Model_Adminhtml_System_Config_Source_Frequency::CRON_DAILY;
        $frequencyWeekly = TradeTracker_Connect_Model_Adminhtml_System_Config_Source_Frequency::CRON_WEEKLY;
        $frequencyMonthly = TradeTracker_Connect_Model_Adminhtml_System_Config_Source_Frequency::CRON_MONTHLY;
        $cronDayOfWeek = date('N');
        $storeIds = Mage::helper('tradetracker')->getStoreIds('tradetracker/generate/enabled');

        $count = count($storeIds);
        if ($count > 0) {
            $minute[] = 0;
            $n = floor(60 / $count);
            if ($n == 60) {
                $n = 0;
            }

            for ($i = 1; $i < $count; $i++) {
                $min = ($i * $n);
                $minute[] = $min;
            }

            $minute = implode(',', $minute);
            $cronExprArray = array(
                $minute,
                intval($time[0]),
                ($frequency == $frequencyMonthly) ? '1' : '*',
                '*',
                ($frequency == $frequencyWeekly) ? '1' : '*',
            );
            $cronExprString = join(' ', $cronExprArray);
        } else {
            $cronExprString = '';
        }

        try {
            Mage::getModel('core/config_data')
                ->load(self::CRON_MODEL_PATH, 'path')
                ->setValue($cronExprString)
                ->setPath(self::CRON_MODEL_PATH)
                ->save();
            Mage::getModel('core/config_data')
                ->load(self::CRON_STRING_PATH, 'path')
                ->setValue($cronExprString)
                ->setPath(self::CRON_STRING_PATH)
                ->save();
            Mage::getModel('core/config_data')
                ->load(self::CRON_RUNMODEL_PATH, 'path')
                ->setValue((string)Mage::getConfig()->getNode(self::CRON_RUNMODEL_PATH))
                ->setPath(self::CRON_RUNMODEL_PATH)
                ->save();
        } catch (Exception $e) {
            throw new Exception(Mage::helper('cron')->__('Unable to save the cron expression.'));
        }
    }

}