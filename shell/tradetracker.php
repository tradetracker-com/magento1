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

require_once 'abstract.php';

class Tradetracker_Shell_GenerateFeed extends Mage_Shell_Abstract
{

    const XPATH_ENABLED = 'tradetracker/generate/enabled';
    const XPATH_RESULT = 'tradetracker/generate/feed_result';

    /**
     *
     */
    public function run()
    {
        if ($generate = $this->getArg('generate')) {
            $storeIds = $this->getStoreIds($generate);
            foreach ($storeIds as $storeId) {
                $timeStart = microtime(true);
                $feed = Mage::getModel('tradetracker/tradetracker')->generateFeed($storeId, 'cli');
                echo $this->getResults($storeId, $feed, $timeStart) . PHP_EOL;
            }
        } else {
            echo $this->usageHelp();
        }
    }

    /**
     * Returns all available storeIds for feed generation.
     *
     * @param $generate
     *
     * @return array
     */
    public function getStoreIds($generate)
    {
        $allStores = Mage::helper('tradetracker')->getStoreIds(self::XPATH_ENABLED);
        if ($generate == 'next') {
            $nextStore = Mage::helper('tradetracker')->getUncachedConfigValue('tradetracker/generate/cron_next');
            if (empty($nextStore) || ($nextStore >= count($allStores))) {
                $nextStore = 0;
            }

            Mage::getModel('core/config')->saveConfig('tradetracker/generate/cron_next', ($nextStore + 1), 'default', 0);
            return array($allStores[$nextStore]);
        }

        if ($generate == 'all') {
            return $allStores;
        }

        return explode(',', trim($generate));
    }

    /**
     * Parse and saves result.
     *
     * @param $storeId
     * @param $result
     * @param $timeStart
     *
     * @return string
     */
    public function getResults($storeId, $result, $timeStart)
    {
        if (!empty($result)) {
            $html = sprintf(
                '<a href="%s" target="_blank">%s</a><br/><small>On: %s (cli) - Products: %s/%s - Time: %s</small>',
                $result['url'],
                $result['url'],
                $result['date'],
                $result['qty'],
                $result['pages'],
                Mage::helper('tradetracker')->getTimeUsage($timeStart)
            );
            Mage::getModel('core/config')->saveConfig(self::XPATH_RESULT, $html, 'stores', $storeId);

            return sprintf(
                'Generated %s - Products: %s/%s - Time: %s',
                $result['url'],
                $result['qty'],
                $result['pages'],
                Mage::helper('tradetracker')->getTimeUsage($timeStart)
            );
        } else {
            return 'No feed found, please check storeId or is module is enabled';
        }
    }

    /**
     * Retrieve Usage Help Message.
     */
    public function usageHelp()
    {
        return <<<USAGE
Usage:  php -f tradetracker.php -- [options]
  --generate next     Generate next available store
  --generate all      Generate all stores    
  --generate <id>     Generate store <id> (comma separated supported)

USAGE;
    }

}

$shell = new TradeTracker_Shell_GenerateFeed();
$shell->run();