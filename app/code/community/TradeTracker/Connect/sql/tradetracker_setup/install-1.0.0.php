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

$installer = new Mage_Catalog_Model_Resource_Eav_Mysql4_Setup('core_setup');
$installer->startSetup();

$attributeSetId = Mage::getModel('catalog/product')->getDefaultAttributeSetId();
$attributeSet = Mage::getModel('eav/entity_attribute_set')->load($attributeSetId);
$installer->addAttributeGroup('catalog_product', $attributeSet->getAttributeSetName(), 'Tradetracker', 1000);

if (!$installer->getAttributeId('catalog_product', 'tradetracker_exclude')) {
    $installer->addAttribute(
        'catalog_product', 'tradetracker_exclude', array(
            'group'                      => 'Tradetracker',
            'input'                      => 'select',
            'type'                       => 'int',
            'source'                     => 'eav/entity_attribute_source_boolean',
            'label'                      => 'Exclude for Tradetracker',
            'visible'                    => 1,
            'required'                   => 0,
            'user_defined'               => 1,
            'searchable'                 => 0,
            'filterable'                 => 0,
            'comparable'                 => 0,
            'visible_on_front'           => 0,
            'visible_in_advanced_search' => 0,
            'is_html_allowed_on_front'   => 0,
            'used_in_product_listing'    => 1,
            'global'                     => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_WEBSITE,
        )
    );
}

if (!$installer->getAttributeId('catalog_category', 'tradetracker_category')) {
    $installer->addAttribute(
        'catalog_category', 'tradetracker_category', array(
            'group'        => 'Feeds',
            'input'        => 'text',
            'type'         => 'varchar',
            'label'        => 'TradeTracker Product Category',
            'required'     => false,
            'user_defined' => true,
            'visible'      => true,
            'global'       => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
        )
    );
}

if (!$installer->getAttributeId('catalog_category', 'tradetracker_product_id')) {
    $installer->addAttribute(
        'catalog_category',
        'tradetracker_product_id',
        array(
            'group'        => 'Feeds',
            'input'        => 'text',
            'type'         => 'varchar',
            'label'        => 'TradeTracker Product ID',
            'required'     => false,
            'user_defined' => true,
            'visible'      => true,
            'global'       => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
        )
    );
}

$installer->endSetup();