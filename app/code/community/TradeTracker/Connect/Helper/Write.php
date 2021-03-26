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

class TradeTracker_Connect_Helper_Write extends Mage_Core_Helper_Abstract
{

    /**
     * @param $config
     *
     * @return Varien_Io_File
     * @throws Exception
     */
    public function createFeed($config)
    {
        $io = new Varien_Io_File();
        $io->setAllowCreateFolders(true);
        $io->open(array('path' => Mage::getBaseDir('tmp')));
        $io->streamOpen($config['file_name_temp']);
        $io->streamWrite('<?xml version="1.0" encoding="utf-8"?>' . PHP_EOL);
        $io->streamWrite('<productFeed>' . PHP_EOL);
        return $io;
    }

    /**
     * @param                $row
     * @param Varien_Io_File $io
     */
    public function writeRow($row, Varien_Io_File $io)
    {
        $io->streamWrite($this->getXmlFromArray($row, 'item'));
    }

    /**
     * @param $data
     * @param $type
     *
     * @return string
     */
    public function getXmlFromArray($data, $type)
    {
        $xml = '  <' . $type . '>' . PHP_EOL;
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $key = preg_replace('/[0-9]*$/', '', $key);
                $xml .= '   <' . $key . '>' . PHP_EOL;
                foreach ($value as $ks => $vs) {
                    if (!empty($vs)) {
                        $xml .= '      <' . $ks . '>' . $this->cleanValue($vs) . '</' . $ks . '>' . PHP_EOL;
                    }
                }

                $xml .= '   </' . $key . '>' . PHP_EOL;
            } else {
                if (!empty($value)) {
                    $xml .= '   <' . $key . '>' . $this->cleanValue($value) . '</' . $key . '>' . PHP_EOL;
                }
            }
        }

        $xml .= '  </' . $type . '>' . PHP_EOL;

        return $xml;
    }

    /**
     * @param $value
     *
     * @return string
     */
    public function cleanValue($value)
    {
        if (version_compare(PHP_VERSION, '5.4.0') >= 0) {
            return htmlspecialchars($value, ENT_XML1);
        } else {
            return htmlspecialchars($value);
        }
    }

    /**
     * @param Varien_Io_File $io
     * @param                $config
     */
    public function closeFeed(Varien_Io_File $io, $config)
    {
        $io->streamWrite('</productFeed>' . PHP_EOL);
        $io->streamClose();

        $tmp = Mage::getBaseDir('tmp') . DS . $config['file_name_temp'];
        $new = $config['file_path'] . DS . $config['file_name'];

        if (!file_exists($config['file_path'])) {
            mkdir($config['file_path']);
        }

        rename($tmp, $new);
    }

}