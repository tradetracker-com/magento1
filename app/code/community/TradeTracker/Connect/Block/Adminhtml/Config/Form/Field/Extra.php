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

class TradeTracker_Connect_Block_Adminhtml_Config_Form_Field_Extra
    extends Mage_Adminhtml_Block_System_Config_Form_Field_Array_Abstract
{

    /**
     * @var array
     */
    protected $_renders = array();

    /**
     * TradeTracker_Connect_Block_Adminhtml_Config_Form_Field_Extra constructor.
     */
    public function __construct()
    {
        $layout = Mage::app()->getFrontController()->getAction()->getLayout();

        $attributeArray = Mage::getModel('tradetracker/adminhtml_system_config_source_attribute')
            ->toOptionArray();

        $rendererAttribute = $layout->createBlock(
            'tradetracker/adminhtml_config_form_renderer_select',
            '',
            array('is_render_to_js_template' => true)
        );
        $rendererAttribute->setOptions($attributeArray);

        $actionsArray = Mage::getModel('tradetracker/adminhtml_system_config_source_action')
            ->toOptionArray();

        $rendererAction = $layout->createBlock(
            'tradetracker/adminhtml_config_form_renderer_select',
            '',
            array('is_render_to_js_template' => true)
        );
        $rendererAction->setOptions($actionsArray);

        $this->addColumn(
            'name', array(
                'label' => Mage::helper('tradetracker')->__('Field Name'),
                'style' => 'width:120px',
            )
        );

        $this->addColumn(
            'attribute', array(
                'label'    => Mage::helper('tradetracker')->__('Attribute'),
                'style'    => 'width:180px',
                'renderer' => $rendererAttribute
            )
        );

        $this->addColumn(
            'action', array(
                'label'    => Mage::helper('tradetracker')->__('Actions'),
                'style'    => 'width:120px',
                'renderer' => $rendererAction
            )
        );

        $this->_renders['attribute'] = $rendererAttribute;
        $this->_renders['action'] = $rendererAction;

        $this->_addAfter = false;
        $this->_addButtonLabel = Mage::helper('tradetracker')->__('Add Field');
        parent::__construct();
    }

    /**
     * @param Varien_Object $row
     */
    protected function _prepareArrayRow(Varien_Object $row)
    {
        foreach ($this->_renders as $key => $render) {
            $row->setData(
                'option_extra_attr_' . $render->calcOptionHash($row->getData($key)),
                'selected="selected"'
            );
        }
    }
}
