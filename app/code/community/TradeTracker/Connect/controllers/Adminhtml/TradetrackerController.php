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

class TradeTracker_Connect_Adminhtml_TradetrackerController
    extends Mage_Adminhtml_Controller_Action
{

    const XPATH_RESULT = 'tradetracker/generate/feed_result';

    /**
     * @var TradeTracker_Connect_Helper_Data
     */
    public $helper;

    /**
     * @var Mage_Core_Model_Config
     */
    public $config;

    /**
     * @var TradeTracker_Connect_Model_Tradetracker
     */
    public $feed;

    /**
     * TradeTracker_Connect_Model_Tradetracker constructor.
     */
    public function _construct()
    {
        $this->helper = Mage::helper('tradetracker');
        $this->config = Mage::getModel('core/config');
        $this->feed = Mage::getModel('tradetracker/tradetracker');
    }

    /**
     * Generate Action
     */
    public function generateManualAction()
    {
        try {
            if (Mage::getStoreConfig('tradetracker/general/enabled')) {
                $storeId = $this->getRequest()->getParam('store_id');
                if (!empty($storeId)) {
                    $timeStart = microtime(true);
                    /** @var Mage_Core_Model_App_Emulation $appEmulation */
                    $appEmulation = Mage::getSingleton('core/app_emulation');
                    $initialEnvironmentInfo = $appEmulation->startEnvironmentEmulation($storeId);
                    if ($result = $this->feed->generateFeed($storeId)) {
                        $this->feed->updateConfig($result, 'manual', $timeStart, $storeId);
                        $downloadUrl = $this->getUrl('*/tradetracker/download/store_id/' . $storeId);
                        $msg = $this->helper->__(
                            'Generated feed with %s products. %s',
                            $result['qty'],
                            '<a style="float:right;" href="' . $downloadUrl . '">Download XML</a>'
                        );
                        Mage::getSingleton('adminhtml/session')->addSuccess($msg);
                    } else {
                        $this->config->saveConfig(self::XPATH_RESULT, '', 'stores', $storeId);
                        $msg = $this->helper->__('No products found, make sure your filters are configured with existing values.');
                        Mage::getSingleton('adminhtml/session')->addError($msg);
                    }

                    $appEmulation->stopEnvironmentEmulation($initialEnvironmentInfo);
                }
            } else {
                $msg = $this->helper->__('Please enable the extension before generating the xml');
                Mage::getSingleton('adminhtml/session')->addError($msg);
            }
        } catch (\Exception $e) {
            $this->helper->addToLog('previewAction', $e->getMessage());
            if (strpos($e->getMessage(), 'SQLSTATE[42S22]') !== false) {
                $msg = $this->helper->__(
                    'SQLSTATE[42S22]: Column not found, plese go to %s and rebuild required indexes.',
                    '<a href="' . $this->getUrl('adminhtml/process/list') . '">Index Management</a>'
                );
                Mage::getSingleton('adminhtml/session')->addError($msg);
            } else {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }

        $this->_redirect('adminhtml/system_config/edit/section/tradetracker');
    }

    /**
     * Preview Action
     */
    public function previewAction()
    {
        try {
            if (Mage::getStoreConfig('tradetracker/general/enabled')) {
                $storeId = $this->getRequest()->getParam('store_id');

                if (!empty($storeId)) {
                    /** @var Mage_Core_Model_App_Emulation $appEmulation */
                    $appEmulation = Mage::getSingleton('core/app_emulation');
                    $initialEnvironmentInfo = $appEmulation->startEnvironmentEmulation($storeId);
                    $this->feed->generateFeed($storeId, 'preview');
                    $appEmulation->stopEnvironmentEmulation($initialEnvironmentInfo);

                    $filePath = '';
                    if ($fileName = $this->feed->getFileName('tradetracker', $storeId, 'preview')) {
                        $filePath = Mage::getBaseDir() . DS . 'media' . DS . 'tradetracker' . DS . $fileName;
                    }

                    if (!empty($filePath) && file_exists($filePath)) {
                        $this->getResponse()
                            ->setHttpResponseCode(200)
                            ->setHeader(
                                'Cache-Control', 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0',
                                true
                            )
                            ->setHeader('Pragma', 'no-cache', 1)
                            ->setHeader('Content-type', 'application/force-download')
                            ->setHeader('Content-Length', filesize($filePath))
                            ->setHeader('Content-Disposition', 'attachment' . '; filename=' . basename($filePath));
                        $this->getResponse()->clearBody();
                        $this->getResponse()->sendHeaders();
                        readfile($filePath);
                    } else {
                        $msg = $this->helper->__('Error creating preview XML');
                        Mage::getSingleton('adminhtml/session')->addError($msg);
                        $this->_redirect('adminhtml/system_config/edit/section/tradetracker');
                    }
                }
            } else {
                $msg = $this->helper->__('Please enable the extension before generating the xml');
                Mage::getSingleton('adminhtml/session')->addError($msg);
                $this->_redirect('adminhtml/system_config/edit/section/tradetracker');
            }
        } catch (\Exception $e) {
            $this->helper->addToLog('previewAction', $e->getMessage());
            if (strpos($e->getMessage(), 'SQLSTATE[42S22]') !== false) {
                $msg = $this->helper->__(
                    'SQLSTATE[42S22]: Column not found, plese go to %s and rebuild required indexes.',
                    '<a href="' . $this->getUrl('adminhtml/process/list') . '">Index Management</a>'
                );
                Mage::getSingleton('adminhtml/session')->addError($msg);
            } else {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }

            $this->_redirect('adminhtml/system_config/edit/section/tradetracker');
        }
    }

    /**
     * Download Action
     */
    public function downloadAction()
    {
        try {
            $filePath = '';
            $storeId = $this->getRequest()->getParam('store_id');
            if ($fileName = $this->feed->getFileName('tradetracker', $storeId)) {
                $filePath = Mage::getBaseDir() . DS . 'media' . DS . 'tradetracker' . DS . $fileName;
            }

            if (!empty($filePath) && file_exists($filePath)) {
                $this->getResponse()
                    ->setHttpResponseCode(200)
                    ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0', true)
                    ->setHeader('Pragma', 'no-cache', 1)
                    ->setHeader('Content-type', 'application/force-download')
                    ->setHeader('Content-Length', filesize($filePath))
                    ->setHeader('Content-Disposition', 'attachment' . '; filename=' . basename($filePath));
                $this->getResponse()->clearBody();
                $this->getResponse()->sendHeaders();
                readfile($filePath);
            }
        } catch (\Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            $this->_redirect('adminhtml/system_config/edit/section/googleshopping');
        }
    }

    /**
     * @return mixed
     */
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('admin/system/config/tradetracker');
    }
}