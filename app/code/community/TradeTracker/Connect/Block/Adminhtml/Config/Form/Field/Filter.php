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

class TradeTracker_Connect_Block_Adminhtml_Config_Form_Field_Filter
    extends Mage_Adminhtml_Block_System_Config_Form_Field_Array_Abstract
{

    /**
     * @var array
     */
    protected $_renders = array();

    /**
     * TradeTracker_Connect_Block_Adminhtml_Config_Form_Field_Filter constructor.
     */
    public function __construct()
    {
        $layout = Mage::app()->getFrontController()->getAction()->getLayout();
        $rendererAttributes = $layout->createBlock(
            'tradetracker/adminhtml_config_form_renderer_select',
            '',
            array('is_render_to_js_template' => true)
        );
        $rendererAttributes->setOptions(
            Mage::getModel('tradetracker/adminhtml_system_config_source_attribute')->toOptionArray()
        );

        $rendererConditions = $layout->createBlock(
            'tradetracker/adminhtml_config_form_renderer_select',
            '',
            array('is_render_to_js_template' => true)
        );

        $rendererConditions->setOptions(
            Mage::getModel('tradetracker/adminhtml_system_config_source_conditions')->toOptionArray()
        );

        $rendererTypes = $layout->createBlock(
            'tradetracker/adminhtml_config_form_renderer_select',
            '',
            array('is_render_to_js_template' => true)
        );

        $rendererTypes->setOptions(
            Mage::getModel('tradetracker/adminhtml_system_config_source_producttypes')->toOptionArray()
        );

        $this->addColumn(
            'attribute', array(
                'label'    => Mage::helper('tradetracker')->__('Attribute'),
                'style'    => 'width:100px',
                'renderer' => $rendererAttributes
            )
        );

        $this->addColumn(
            'condition', array(
                'label'    => Mage::helper('tradetracker')->__('Condition'),
                'style'    => 'width:100px',
                'renderer' => $rendererConditions
            )
        );

        $this->addColumn(
            'value', array(
                'label' => Mage::helper('tradetracker')->__('Value'),
                'style' => 'width:100px',
            )
        );

        $this->addColumn(
            'product_type', array(
                'label'    => Mage::helper('tradetracker')->__('Apply To'),
                'style'    => 'width:150px',
                'renderer' => $rendererTypes
            )
        );

        $this->_renders['attribute'] = $rendererAttributes;
        $this->_renders['condition'] = $rendererConditions;
        $this->_renders['product_type'] = $rendererTypes;

        $this->_addAfter = false;
        $this->_addButtonLabel = Mage::helper('tradetracker')->__('Add Filter');
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