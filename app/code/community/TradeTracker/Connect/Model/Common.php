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

class TradeTracker_Connect_Model_Common extends Mage_Catalog_Model_Resource_Abstract
{

    /**
     * @param        $config
     *
     * @return TradeTracker_Connect_Model_Resource_Product_Collection
     * @throws Mage_Core_Exception
     */
    public function getProducts($config)
    {
        $storeId = $config['store_id'];
        $websiteId = $config['website_id'];
        $limit = $config['limit'];

        /** @var TradeTracker_Connect_Model_Resource_Product_Collection $collection */
        $collection = Mage::getModel('tradetracker/resource_product_collection')
            ->setStore($storeId)
            ->addStoreFilter($storeId)
            ->addUrlRewrite()
            ->addAttributeToFilter('status', 1);

        if ($limit > 0) {
            $collection->setPageSize($limit);
        }

        $this->addStatusFilters($collection, $config);
        $this->addCategoryFilters($collection, $config);

        if (!empty($config['filters'])) {
            $this->addFilters($config['filters'], $collection);
        }

        if (!empty($config['hide_no_stock'])) {
            Mage::getSingleton('cataloginventory/stock')->addInStockFilterToCollection($collection);
        }

        $attributes = $this->getAttributes($config['field']);
        $collection->addAttributeToSelect($attributes);

        $collection->joinTable(
            'cataloginventory/stock_item',
            'product_id=entity_id',
            $config['inventory']['attributes']
        );

        $this->joinPriceIndexLeft($collection, $websiteId);
        $collection->getSelect()->group('e.entity_id');

        if (!empty($config['filters'])) {
            $this->addFilters($config['filters'], $collection);
        }

        return $collection;
    }

    /**
     * @param TradeTracker_Connect_Model_Resource_Product_Collection   $collection
     * @param                                                             $config
     */
    public function addStatusFilters($collection, $config)
    {
        if (!empty($config['filter_status'])) {
            $visibility = $config['filter_status'];
            if (strlen($visibility) > 1) {
                $visibility = explode(',', $visibility);
                if ($config['conf_enabled']) {
                    $visibility[] = '1';
                }

                $collection->addAttributeToFilter('visibility', array('in' => array($visibility)));
            } else {
                if (!empty($config['conf_enabled'])) {
                    $visibility = '1,' . $visibility;
                    $visibility = explode(',', $visibility);
                    $collection->addAttributeToFilter('visibility', array('in' => array($visibility)));
                } else {
                    $collection->addAttributeToFilter('visibility', array('eq' => array($visibility)));
                }
            }
        }
    }

    /**
     * @param TradeTracker_Connect_Model_Resource_Product_Collection   $collection
     * @param                                                             $config
     */
    public function addCategoryFilters($collection, $config)
    {
        if (!empty($config['filter_enabled'])) {
            $type = $config['filter_type'];
            $categories = $config['filter_cat'];
            if ($type && $categories) {
                $table = Mage::getSingleton('core/resource')->getTableName('catalog_category_product');
                if ($type == 'include') {
                    $collection->getSelect()->join(array('cats' => $table), 'cats.product_id = e.entity_id');
                    $collection->getSelect()->where('cats.category_id in (' . $categories . ')');
                } else {
                    $collection->getSelect()->join(array('cats' => $table), 'cats.product_id = e.entity_id');
                    $collection->getSelect()->where('cats.category_id not in (' . $categories . ')');
                }
            }
        }
    }

    /**
     * @param                                                             $filters
     * @param TradeTracker_Connect_Model_Resource_Product_Collection   $collection
     * @param                                                             $type
     *
     * @return TradeTracker_Connect_Model_Resource_Product_Collection
     * @throws Mage_Core_Exception
     */
    public function addFilters($filters, $collection, $type = 'simple')
    {
        $cType = array(
            'eq'   => '=',
            'neq'  => '!=',
            'gt'   => '>',
            'gteq' => '>=',
            'lt'   => '<',
            'lteg' => '<='
        );

        foreach ($filters as $filter) {
            $attribute = $filter['attribute'];
            $condition = $filter['condition'];
            $value = $filter['value'];
            $productFilterType = $filter['product_type'];
            $filterExpr = array();

            if ($type == 'simple' && $productFilterType == 'parent') {
                continue;
            }

            if ($type == 'parent' && $productFilterType == 'simple') {
                continue;
            }

            $productEntity = Mage_Catalog_Model_Product::ENTITY;
            /** @var Mage_Eav_Model_Config $eavConfig */
            $eavConfig = Mage::getSingleton('eav/config');
            $attributeModel = $eavConfig->getAttribute($productEntity, $attribute);
            if(!$attributeModel->getAttributeCode()) {
                continue;
            }

            $frontendInput = $attributeModel->getFrontendInput();
            if ($frontendInput == 'select' || $frontendInput == 'multiselect') {
                $options = $attributeModel->getSource()->getAllOptions();
                if (strpos($value, ',') !== false) {
                    $values = array();
                    $value = explode(',', $value);
                    foreach ($value as $v) {
                        $valueId = array_search(trim($v), array_column($options, 'label'));
                        if ($valueId) {
                            $values[] = $options[$valueId]['value'];
                        }
                    }

                    $value = implode(',', $values);
                } else {
                    $valueId = array_search($value, array_column($options, 'label'));
                    if ($valueId) {
                        $value = $options[$valueId]['value'];
                    }
                }
            }

            if ($attribute == 'final_price') {
                if (isset($cType[$condition])) {
                    $collection->getSelect()
                        ->where('price_index.final_price ' . $cType[$condition] . ' ' . $value);
                }

                continue;
            }

            if ($attribute == 'min_sale_qty') {
                if (isset($cType[$condition])) {
                    $collection->getSelect()
                        ->where('cataloginventory_stock_item.min_sale_qty ' . $cType[$condition] . ' ' . $value);
                }

                continue;
            }

            switch ($condition) {
                case 'nin':
                    if (strpos($value, ',') !== false) {
                        $value = explode(',', $value);
                    }

                    $filterExpr[] = array('attribute' => $attribute, $condition => $value);
                    $filterExpr[] = array('attribute' => $attribute, 'null' => true);
                    break;
                case 'in';
                    if (strpos($value, ',') !== false) {
                        $value = explode(',', $value);
                    }

                    $filterExpr[] = array('attribute' => $attribute, $condition => $value);
                    break;
                case 'neq':
                    $filterExpr[] = array('attribute' => $attribute, $condition => $value);
                    $filterExpr[] = array('attribute' => $attribute, 'null' => true);
                    break;
                case 'empty':
                    $filterExpr[] = array('attribute' => $attribute, 'null' => true);
                    break;
                case 'not-empty':
                    $filterExpr[] = array('attribute' => $attribute, 'notnull' => true);
                    break;
                case 'gt':
                case 'gteq':
                case 'lt':
                case 'lteq':
                    if (is_numeric($value)) {
                        $filterExpr[] = array('attribute' => $attribute, $condition => $value);
                    }
                    break;
                default:
                    $filterExpr[] = array('attribute' => $attribute, $condition => $value);
                    break;
            }

            if (!empty($filterExpr)) {
                if ($productFilterType == 'parent') {
                    $filterExpr[] = array('attribute' => 'type_id', 'eq' => 'simple');
                    /** @noinspection PhpParamsInspection */
                    $collection->addAttributeToFilter($filterExpr, '', 'left');
                } elseif ($productFilterType == 'simple') {
                    $filterExpr[] = array('attribute' => 'type_id', 'neq' => 'simple');
                    /** @noinspection PhpParamsInspection */
                    $collection->addAttributeToFilter($filterExpr, '', 'left');
                } else {
                    /** @noinspection PhpParamsInspection */
                    $collection->addAttributeToFilter($filterExpr);
                }
            }
        }

        return $collection;
    }

    /**
     * @param $config
     *
     * @return array
     */

    /**
     * @param $selectedAttrs
     *
     * @return array
     */
    public function getAttributes($selectedAttrs)
    {
        $attributes = $this->getDefaultAttributes();
        foreach ($selectedAttrs as $selectedAtt) {
            if (!empty($selectedAtt['source'])) {
                $attributes[] = $selectedAtt['source'];
            }

            if (!empty($selectedAtt['multi']) && is_array($selectedAtt['multi'])) {
                foreach ($selectedAtt['multi'] as $attribute) {
                    $attributes[] = $attribute['source'];
                }
            }

            if (!empty($selectedAtt['main'])) {
                $attributes[] = $selectedAtt['main'];
            }
        }

        return array_unique($attributes);
    }

    /**
     * @return array
     */
    public function getDefaultAttributes()
    {
        $attributes = array();
        $attributes[] = 'url_key';
        $attributes[] = 'url_path';
        $attributes[] = 'sku';
        $attributes[] = 'price';
        $attributes[] = 'final_price';
        $attributes[] = 'price_model';
        $attributes[] = 'price_type';
        $attributes[] = 'special_price';
        $attributes[] = 'special_from_date';
        $attributes[] = 'special_to_date';
        $attributes[] = 'type_id';
        $attributes[] = 'tax_class_id';
        $attributes[] = 'tax_percent';
        $attributes[] = 'weight';
        $attributes[] = 'visibility';
        $attributes[] = 'type_id';
        $attributes[] = 'image';
        $attributes[] = 'small_image';
        $attributes[] = 'thumbnail';
        $attributes[] = 'status';

        return $attributes;
    }

    /**
     * @param $collection
     * @param $websiteId
     */
    public function joinPriceIndexLeft($collection, $websiteId)
    {
        $resource = Mage::getResourceSingleton('core/resource');
        $tableName = array('price_index' => $resource->getTable('catalog/product_index_price'));
        $joinCond = join(
            ' AND ', array(
            'price_index.entity_id = e.entity_id',
            'price_index.website_id = ' . $websiteId,
            'price_index.customer_group_id = 0'
            )
        );
        $colls = array('final_price', 'min_price', 'max_price');
        $collection->getSelect()->joinLeft($tableName, $joinCond, $colls);
    }

    /**
     * @param $atts
     *
     * @return array
     */
    public function getParentAttributeSelection($atts)
    {
        $attributes = $this->getDefaultAttributes();
        foreach ($atts as $attribute) {
            if (!empty($attribute['parent'])) {
                if (!empty($attribute['source'])) {
                    if ($attribute['source'] != 'entity_id') {
                        $attributes[] = $attribute['source'];
                    }
                }
            }
        }

        return $attributes;
    }

    /**
     * @param array $parentRelations
     * @param array $config
     *
     * @return TradeTracker_Connect_Model_Resource_Product_Collection
     */
    public function getParents($parentRelations, $config)
    {
        if (!empty($config['conf_enabled']) && !empty($parentRelations)) {

            /** @var TradeTracker_Connect_Model_Resource_Product_Collection $collection */
            $collection = Mage::getModel('tradetracker/resource_product_collection')
                ->setStore($config['store_id'])
                ->addStoreFilter($config['store_id'])
                ->addUrlRewrite()
                ->addAttributeToFilter('entity_id', array('in' => array_values($parentRelations)))
                ->addAttributeToSelect(array_unique($config['parent_att']))
                ->addAttributeToFilter('status', 1);

            if (!empty($config['hide_no_stock'])) {
                Mage::getSingleton('cataloginventory/stock')->addInStockFilterToCollection($collection);
            }

            $this->joinPriceIndexLeft($collection, $config['website_id']);
            $collection->getSelect()->group('e.entity_id');

            if (!empty($config['filters'])) {
                $collection = $this->addFilters($config['filters'], $collection, 'parent');
            }

            return $collection->load();
        }
    }
}