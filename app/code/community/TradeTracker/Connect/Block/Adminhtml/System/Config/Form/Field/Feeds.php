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

class TradeTracker_Connect_Block_Adminhtml_System_Config_Form_Field_Feeds
    extends Mage_Adminhtml_Block_Abstract implements Varien_Data_Form_Element_Renderer_Interface
{

    /**
     * @param Varien_Data_Form_Element_Abstract $element
     *
     * @return string
     * @throws Mage_Core_Model_Store_Exception
     */
    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        /** @var TradeTracker_Connect_Helper_Data $helper */
        $helper = Mage::helper('tradetracker');
        $storeIds = $helper->getStoreIds('tradetracker/generate/enabled');
        $htmlFeedlinks = '';
        foreach ($storeIds as $storeId) {
            $generateUrl = $this->getUrl('*/tradetracker/generateManual/store_id/' . $storeId);
            $previewUrl = $this->getUrl('*/tradetracker/preview/store_id/' . $storeId);
            $downloadUrl = $this->getUrl('*/tradetracker/download/store_id/' . $storeId);
            $feedText = $helper->getUncachedConfigValue('tradetracker/generate/feed_result', $storeId);
            if (empty($feedText)) {
                $feedText = Mage::helper('tradetracker')->__('No active feed found');
                $downloadUrl = '';
            }

            $storeTitle = Mage::app()->getStore($storeId)->getName();
            $storeCode = Mage::app()->getStore($storeId)->getCode();
            $htmlFeedlinks .= '<tr>
             <td valign="top">' . $storeTitle . '<br/><small>Code: ' . $storeCode . '</small></td>
             <td>' . $feedText . '</td>
             <td>
              » <a href="' . $generateUrl . '">' . Mage::helper('tradetracker')->__('Generate New') . '</a><br/>
              » <a href="' . $previewUrl . '">' . Mage::helper('tradetracker')->__('Preview 100') . '</a><br/>
              » <a href="' . $downloadUrl . '">' . Mage::helper('tradetracker')->__('Download Last') . '</a>              
             </td>
            </tr>';
        }

        if (empty($htmlFeedlinks)) {
            $htmlFeedlinks = Mage::helper('tradetracker')->__('No enabled feed(s) found');
        } else {
            $htmlHeader = '<div class="grid">
             <table cellpadding="0" cellspacing="0" class="border" style="width: 100%">
              <tbody>
               <tr class="headings"><th>Store</th><th>Feed</th><th>Generate</th></tr>';
            $htmlFooter = '</tbody></table></div>';
            $htmlFeedlinks = $htmlHeader . $htmlFeedlinks . $htmlFooter;
        }

        return sprintf(
            '<tr id="row_%s"><td colspan="6" class="label" style="margin-bottom: 10px;">%s</td></tr>',
            $element->getHtmlId(),
            $htmlFeedlinks
        );
    }

}
