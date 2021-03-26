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

class TradeTracker_Connect_Model_Tradetracker extends TradeTracker_Connect_Model_Common
{

    /**
     * @var TradeTracker_Connect_Helper_Data
     */
    public $helper;
    /**
     * @var Mage_Tax_Helper_Data
     */
    public $taxHelper;
    /**
     * @var Mage_Core_Model_Config
     */
    public $config;

    /**
     * TradeTracker_Connect_Model_Tradetracker constructor.
     */
    public function __construct()
    {
        $this->helper = Mage::helper('tradetracker');
        $this->taxHelper = Mage::helper('tax');
        $this->config = Mage::getModel('core/config');
    }

    /**
     * @param string $type
     * @param null   $storeId
     * @param bool   $return
     *
     * @return array|null
     */
    public function runScheduled($type = 'cron', $storeId = null, $return = false)
    {
        $returnValue = null;
        $enabled = $this->helper->getConfigData('general/enabled');
        $cron = $this->helper->getConfigData('generate/cron');
        $timeStart = microtime(true);

        if ($enabled && $cron) {
            if ($storeId == null) {
                $nextStore = $this->helper->getUncachedConfigValue('tradetracker/generate/cron_next');
                $storeIds = $this->helper->getStoreIds('tradetracker/generate/enabled');
                if(!count($storeIds)) {
                    return $returnValue;
                }

                if (empty($nextStore) || ($nextStore >= count($storeIds))) {
                    $nextStore = 0;
                }

                $storeId = $storeIds[$nextStore];
                $nextStore++;
            }

            try {
                /** @var Mage_Core_Model_App_Emulation $appEmulation */
                $appEmulation = Mage::getSingleton('core/app_emulation');
                $initialEnvironmentInfo = $appEmulation->startEnvironmentEmulation($storeId);
                if ($result = $this->generateFeed($storeId)) {
                    $this->updateConfig($result, $type, $timeStart, $storeId);
                }

                $appEmulation->stopEnvironmentEmulation($initialEnvironmentInfo);
                $returnValue = ($return) ? $result : null;
            } catch (\Exception $e) {
                $this->helper->addToLog('runScheduled', $e->getMessage(), null, true);
            }

            if (!empty($nextStore)) {
                $this->config->saveConfig('tradetracker/generate/cron_next', $nextStore, 'default', 0);
            }
        }

        return $returnValue;
    }

    /**
     * @param $result
     * @param $type
     * @param $timeStart
     * @param $storeId
     */
    public function updateConfig($result, $type, $timeStart, $storeId)
    {
        $html = sprintf(
            '<a href="%s" target="_blank">%s</a><br/><small>On: %s (%s) - Products: %s/%s - Time: %s</small>',
            $result['url'],
            $result['url'],
            $result['date'],
            $type,
            $result['qty'],
            $result['pages'],
            $this->helper->getTimeUsage($timeStart)
        );

        $this->config->saveConfig('tradetracker/generate/feed_result', $html, 'stores', $storeId);

        if ($this->helper->getConfigData('generate/log_generation', $storeId)) {
            $msg = strip_tags(str_replace('<br/>', ' => ', $html));
            $this->helper->addToLog('Feed Generation Store ID ' . $storeId, $msg, null, true);
        }
    }

    /**
     * @param        $storeId
     * @param string $type
     *
     * @return array
     * @throws Mage_Core_Exception
     */
    public function generateFeed($storeId, $type = 'xml')
    {
        $this->setMemoryLimit($storeId);
        $config = $this->getFeedConfig($storeId, $type);
        $io = $this->helper->createFeed($config);
        $products = $this->getProducts($config);

        if ($type == 'preview') {
            $pages = 1;
        } else {
            $pages = $products->getLastPageNumber();
        }

        $curPage = 1;
        $processed = 0;

        do {
            $products->setCurPage($curPage);
            $products->load();

            $parentRelations = $this->helper->getParentsFromCollection($products, $config);
            $parents = $this->getParents($parentRelations, $config);
            $prices = $this->helper->getTypePrices($config, $parents);
            $parentAttributes = $this->helper->getConfigurableAttributesAsArray($parents, $config);
            $processed += $this->getFeedData(
                $products, $parents, $config, $parentAttributes, $prices, $io,
                $parentRelations
            );

            if ($config['debug_memory'] && ($type != 'preview')) {
                $this->helper->addLog($curPage, $pages, $processed);
            }

            $products->clear();
            $parents = null;
            $prices = null;
            $parentAttributes = null;
            $curPage++;
        } while ($curPage <= $pages);

        $feedStats = array();
        $feedStats['qty'] = $processed;
        $feedStats['date'] = date("Y-m-d H:i:s", Mage::getModel('core/date')->timestamp(time()));
        $feedStats['url'] = $config['file_url'];
        $feedStats['shop'] = Mage::app()->getStore($config['store_id'])->getCode();
        $feedStats['pages'] = $pages;

        $this->helper->closeFeed($io, $config);

        return $feedStats;
    }

    /**
     * @param $storeId
     */
    public function setMemoryLimit($storeId)
    {
        if ($this->helper->getConfigData('generate/overwrite', $storeId)) {
            if ($memoryLimit = $this->helper->getConfigData('generate/memory_limit', $storeId)) {
                ini_set('memory_limit', $memoryLimit);
            }

            if ($maxExecutionTime = $this->helper->getConfigData('generate/max_execution_time', $storeId)) {
                ini_set('max_execution_time', $maxExecutionTime);
            }
        }
    }

    /**
     * @param        $storeId
     * @param string $type
     *
     * @return array
     * @throws Mage_Core_Exception
     */
    public function getFeedConfig($storeId, $type = 'xml')
    {

        $config = array();
        /** @var Mage_Core_Model_Store $store */
        $store = Mage::getModel('core/store')->load($storeId);
        $websiteId = $store->getWebsiteId();
        /** @var Mage_Core_Model_Website $website */
        $website = Mage::getModel('core/website')->load($websiteId);
        $websiteUrl = $store->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA);
        /** @var Mage_Eav_Model_Entity_Attribute $attribute */
        $attribute = Mage::getResourceModel('eav/entity_attribute');

        // DEFAULTS
        $config['store_id'] = $storeId;
        $config['website_id'] = $websiteId;
        $config['website_name'] = $this->helper->cleanData($website->getName(), 'striptags');
        $config['website_url'] = $websiteUrl;
        $config['website_url'] = $store->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK);
        $config['media_url'] = $store->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA);
        $config['media_image_url'] = $config['media_url'] . 'catalog' . DS . 'product';
        $config['media_gallery_id'] = $attribute->getIdByCode('catalog_product', 'media_gallery');
        $config['filters'] = $this->helper->getSerializedConfigData('filter/advanced', $storeId);
        $config['file_name_temp'] = $this->getFileName('tradetracker', $storeId, $type, true);
        $config['file_name'] = $this->getFileName('tradetracker', $storeId, $type);
        $config['file_path'] = Mage::getBaseDir() . DS . 'media' . DS . 'tradetracker';
        $config['file_url'] = $websiteUrl . 'tradetracker' . DS . $config['file_name'];

        // PRODUCT & CATEGORY 
        $config['field'] = $this->getFeedAttributes($storeId);
        $config['filter_enabled'] = $this->helper->getConfigData('filter/category_enabled', $storeId);
        $config['version'] = (string)Mage::getConfig()->getNode()->modules->TradeTracker_Connect->version;
        $config['filter_cat'] = $this->helper->getConfigData('filter/categories', $storeId);
        $config['filter_type'] = $this->helper->getConfigData('filter/category_type', $storeId);
        $config['filter_status'] = $this->helper->getConfigData('filter/visibility_inc', $storeId);
        $config['hide_no_stock'] = $this->helper->getConfigData('filter/stock', $storeId);
        $config['conf_enabled'] = $this->helper->getConfigData('advanced/conf_enabled', $storeId);
        $config['conf_fields'] = $this->helper->getConfigData('advanced/conf_fields', $storeId);
        $config['conf_switch_urls'] = $this->helper->getConfigData('advanced/conf_switch_urls', $storeId);
        $config['simple_price'] = $this->helper->getConfigData('advanced/simple_price', $storeId);
        $config['stock_manage'] = Mage::getStoreConfig('cataloginventory/item_options/manage_stock');
        $config['use_qty_increments'] = Mage::getStoreConfig('cataloginventory/item_options/enable_qty_increments');
        $config['qty_increments'] = Mage::getStoreConfig('cataloginventory/item_options/qty_increments');
        $config['backorders'] = Mage::getStoreConfig('cataloginventory/item_options/backorders');
        $config['category_type'] = $this->helper->getConfigData('data/category_type', $storeId);
        $config['category_attribute'] = $this->helper->getConfigData('data/category_attribute', $storeId);
        $config['category_default'] = $this->helper->getConfigData('data/default_category', $storeId);
        $config['category_default_t'] = $this->helper->getConfigData('data/category_tradetracker_default', $storeId);

        // PRICE		
        $config['price_scope'] = Mage::getStoreConfig('catalog/price/scope');
        $config['price_add_tax'] = $this->helper->getConfigData('advanced/add_tax', $storeId);
        $config['price_add_tax_perc'] = $this->helper->getConfigData('advanced/tax_percentage', $storeId);
        $config['price_grouped'] = $this->helper->getConfigData('advanced/grouped_price', $storeId);
        $config['force_tax'] = $this->helper->getConfigData('advanced/force_tax', $storeId);
        $config['currency'] = $store->getDefaultCurrencyCode();
        $config['base_currency_code'] = $store->getBaseCurrencyCode();
        $config['markup'] = $this->helper->getPriceMarkup($config);
        $config['use_tax'] = $this->helper->getTaxUsage($config);
        $config['hide_currency'] = true;
        $config['inventory'] = $this->getInventoryData();

        // Config Values
        $config['extra_info'] = $this->helper->getConfigData('data/extra_info', $storeId);
        $config['shipping_prices'] = $this->helper->getSerializedConfigData('data/shipping_price', $storeId);
        $config['delivery'] = $this->helper->getConfigData('data/delivery', $storeId);
        $config['delivery_att'] = $this->helper->getConfigData('data/delivery_att', $storeId);
        $config['delivery_in'] = $this->helper->getConfigData('data/delivery_in', $storeId);
        $config['delivery_out'] = $this->helper->getConfigData('data/delivery_out', $storeId);
        $config['stock_manage'] = Mage::getStoreConfig('cataloginventory/item_options/manage_stock');
        $config['images'] = $this->helper->getConfigData('data/images', $storeId);
        $config['main_image'] = $this->helper->getConfigData('data/main_image', $storeId);
        $config['bypass_flat'] = $this->helper->getConfigData('generate/bypass_flat', $storeId);
        $config['debug_memory'] = $this->helper->getConfigData('generate/debug_memory', $storeId);
        $config['product_url_suffix'] = $this->helper->getProductUrlSuffix($storeId);

        // CHECK CUSTOM ATTRIBUTES
        $eavAttribute = new Mage_Eav_Model_Mysql4_Entity_Attribute();
        if ($eavAttribute->getIdByCode('catalog_category', 'tradetracker_category')) {
            $config['category_custom'] = 'tradetracker_category';
        }

        if ($eavAttribute->getIdByCode('catalog_product', 'tradetracker_exclude')) {
            $config['filter_exclude'] = 'tradetracker_exclude';
        }

        if ($this->helper->getConfigData('data/name', $storeId) == 'use_custom') {
            $config['custom_name'] = $this->helper->getConfigData('data/name_custom', $storeId);
        }

        if ($this->helper->getConfigData('data/description', $storeId) == 'use_custom') {
            $config['custom_description'] = $this->helper->getConfigData('data/description_custom', $storeId);
        }

        if ($this->helper->getConfigData('generate/paging', $storeId)) {
            $config['limit'] = $this->helper->getConfigData('generate/limit', $storeId);
        } else {
            $config['limit'] = '';
        }

        if ($type == 'preview') {
            $config['limit'] = 100;
        }

        $config['field'] = $this->getFeedAttributes($storeId, $type, $config);
        $config['parent_att'] = $this->getParentAttributeSelection($config['field']);
        $config['root_category_id'] = $store->getRootCategoryId();
        $config['category_data'] = $this->helper->getCategoryData($config, $storeId);
        return $config;
    }

    /**
     * @param      $feed
     * @param      $storeId
     * @param null $type
     * @param bool $temp
     *
     * @return mixed|string
     */
    public function getFileName($feed, $storeId, $type = null, $temp = false)
    {
        if (!$fileName = Mage::getStoreConfig($feed . '/generate/filename', $storeId)) {
            $fileName = $feed . '.xml';
        }

        if (substr($fileName, -3) != 'xml') {
            $fileName = $fileName . '-' . $storeId . '.xml';
        } else {
            $fileName = substr($fileName, 0, -4) . '-' . $storeId . '.xml';
        }

        if ($type == 'preview') {
            $fileName = str_replace('.xml', '-preview.xml', $fileName);
        }

        if ($temp) {
            $fileName = time() . '-' . $fileName;
        }

        return $fileName;
    }

    /**
     * @return array
     */
    public function getInventoryData()
    {
        $invAtt = array();
        $invAtt['attributes'][] = 'qty';
        $invAtt['attributes'][] = 'is_in_stock';
        $invAtt['attributes'][] = 'use_config_manage_stock';
        $invAtt['attributes'][] = 'use_config_qty_increments';
        $invAtt['attributes'][] = 'enable_qty_increments';
        $invAtt['attributes'][] = 'use_config_enable_qty_inc';
        $invAtt['attributes'][] = 'use_config_min_sale_qty';
        $invAtt['attributes'][] = 'backorders';
        $invAtt['attributes'][] = 'use_config_backorders';
        $invAtt['attributes'][] = 'manage_stock';
        $invAtt['config_backorders'] = Mage::getStoreConfig('cataloginventory/item_options/backorders');
        $invAtt['config_manage_stock'] = Mage::getStoreConfig('cataloginventory/item_options/manage_stock');
        $invAtt['config_qty_increments'] = Mage::getStoreConfig('cataloginventory/item_options/qty_increments');
        $invAtt['config_enable_qty_inc'] = Mage::getStoreConfig('cataloginventory/item_options/enable_qty_increments');
        $invAtt['config_min_sale_qty'] = Mage::getStoreConfig('cataloginventory/item_options/min_qty');

        return $invAtt;
    }

    /**
     * @param int    $storeId
     * @param string $type
     * @param string $config
     *
     * @return array|mixed
     */
    public function getFeedAttributes($storeId = 0, $type = '', $config = '')
    {
        $attributes = array();
        $attributes['id'] = array(
            'label'  => 'ID',
            'source' => 'entity_id'
        );
        $attributes['title'] = array(
            'label'  => 'name',
            'source' => $this->helper->getConfigData('data/name', $storeId),
            'action' => 'striptags'
        );
        $attributes['description'] = array(
            'label'  => 'description',
            'source' => $this->helper->getConfigData('data/description', $storeId),
            'action' => 'striptags'
        );
        $attributes['product_url'] = array(
            'label'  => 'productURL',
            'source' => ''
        );
        $attributes['image_link'] = array(
            'label'  => 'imageURL',
            'source' => ''
        );
        $attributes['price'] = array(
            'label'  => 'price',
            'source' => ''
        );
        $attributes['brand'] = array(
            'label'  => 'brand',
            'source' => $this->helper->getConfigData('data/brand', $storeId)
        );
        $attributes['color'] = array(
            'label'  => 'color',
            'source' => $this->helper->getConfigData('data/color', $storeId)
        );
        $attributes['material'] = array(
            'label'  => 'material',
            'source' => $this->helper->getConfigData('data/material', $storeId)
        );
        $attributes['size'] = array(
            'label'  => 'size',
            'source' => $this->helper->getConfigData('data/size', $storeId)
        );
        $attributes['gender'] = array(
            'label'  => 'gender',
            'source' => $this->helper->getConfigData('data/gender', $storeId)
        );
        $attributes['ean'] = array(
            'label'  => 'EAN',
            'source' => $this->helper->getConfigData('data/ean', $storeId)
        );
        $attributes['availability'] = array(
            'label'  => 'deliveryCosts',
            'source' => ''
        );
        $attributes['categories'] = array(
            'label'  => 'categories',
            'source' => ''
        );

        if ($this->helper->getConfigData('data/delivery', $storeId) == 'attribute') {
            $attributes['delivery'] = array(
                'label'  => 'deliveryTime',
                'source' => $this->helper->getConfigData('data/delivery_att', $storeId)
            );
        }

        if ($this->helper->getConfigData('data/stock', $storeId)) {
            $limit = $this->helper->getConfigData('data/stock_limit', $storeId);
            if ($limit > 0) {
                $attributes['qty'] = array(
                    'label'  => 'stock',
                    'source' => 'qty',
                    'action' => 'round_' . $limit
                );
            } else {
                $attributes['qty'] = array(
                    'label'  => 'stock',
                    'source' => 'qty',
                    'action' => 'round'
                );
            }
        }

        if ($type == 'flatcheck') {
            if ($filters = @unserialize($this->helper->getConfigData('filter/advanced'))) {
                foreach ($filters as $filter) {
                    $attributes[$filter['attribute']] = array(
                        'label'  => $filter['attribute'],
                        'source' => $filter['attribute']
                    );
                }
            }

            $customValues = '';
            if ($this->helper->getConfigData('data/name', $storeId) == 'use_custom') {
                $customValues .= $this->helper->getConfigData('data/name_custom', $storeId);
            }

            if ($this->helper->getConfigData('data/description', $storeId) == 'use_custom') {
                $customValues .= $this->helper->getConfigData('data/description_custom', $storeId);
            }

            preg_match_all("/{{([^}]*)}}/", $customValues, $foundAtts);
            if (!empty($foundAtts)) {
                foreach ($foundAtts[1] as $att) {
                    $attributes[$att] = array(
                        'label'  => $att,
                        'source' => $att
                    );
                }
            }
        }

        if ($extraFields = @unserialize($this->helper->getConfigData('data/extra', $storeId))) {
            foreach ($extraFields as $extraField) {
                $attributes[$extraField['attribute']] = array(
                    'label'  => $extraField['name'],
                    'source' => $extraField['attribute'],
                    'action' => $extraField['action']
                );
            }
        }

        if ($type != 'config') {
            return $this->helper->addAttributeData($attributes, $config);
        } else {
            return $attributes;
        }
    }

    /**
     * @param $products
     * @param $parents
     * @param $config
     * @param $parentAttributes
     * @param $prices
     * @param $io
     * @param $parentRelations
     *
     * @return int
     */
    public function getFeedData($products, $parents, $config, $parentAttributes, $prices, $io, $parentRelations)
    {
        $qty = 0;

        foreach ($products as $product) {
            $parent = null;
            if (!empty($parentRelations[$product->getEntityId()])) {
                foreach ($parentRelations[$product->getEntityId()] as $parentId) {
                    if ($parent = $parents->getItemById($parentId)) {
                        continue;
                    }
                }
            }

            $productData = $this->helper->getProductDataRow($product, $config, $parent, $parentAttributes);

            if ($productData) {
                $productRow = array();
                foreach ($productData as $key => $value) {
                    if (!is_array($value)) {
                        $productRow[$key] = $value;
                    }
                }

                if ($extraData = $this->getExtraDataFields($productData, $config, $product, $prices, $parent)) {
                    $productRow = array_merge($productRow, $extraData);
                }

                $productRow = new Varien_Object($this->processUnset($productRow));
                Mage::dispatchEvent(
                    'tradetracker_feed_item_before',
                    array('feed_data' => $productRow, 'product' => $product)
                );
                $productRow = $productRow->getData();
                $this->helper->writeRow($productRow, $io);

                $productRow = null;
                $qty++;
            }
        }

        return $qty;
    }

    /**
     * @param                            $productData
     * @param                            $config
     * @param Mage_Catalog_Model_Product $product
     * @param                            $prices
     * @param Mage_Catalog_Model_Product $parent
     *
     * @return array
     */
    public function getExtraDataFields($productData, $config, $product, $prices, $parent)
    {
        $_extra = array();

        if ($_custom = $this->getCustomData($config, $product)) {
            $_extra = array_merge($_extra, $_custom);
        }

        if ($_categoryData = $this->getCategoryData($productData, $config, $product)) {
            $_extra = array_merge($_extra, $_categoryData);
        }

        if (!isset($productData['price']['final_price_clean'])) {
            $productData['price'] = array();
            $price = '';
        } else {
            $price = $productData['price']['final_price_clean'];
        }

        if (!empty($config['hide_currency'])) {
            $currency = '';
        } else {
            $currency = ' ' . $config['currency'];
        }

        if (!empty($parent)) {
            $itemGroupId = $parent->getEntityId();
        } else {
            $itemGroupId = '';
        }

        if ($_prices = $this->getPrices($productData['price'], $prices, $product, $config, $itemGroupId)) {
            $_extra = array_merge($_extra, $_prices);
            $price = trim(str_replace($currency, '', $_prices['price']));
        }

        if ($_shipping = $this->getShipping($productData, $config, $product, $price)) {
            $_extra = array_merge($_extra, $_shipping);
        }

        if ($_usp = $this->getUspText($config)) {
            $_extra = array_merge($_extra, $_usp);
        }

        if ($_images = $this->getImages($productData, $config)) {
            $_extra = array_merge($_extra, $_images);
        }

        return $_extra;
    }

    /**
     * @param                            $config
     * @param Mage_Catalog_Model_Product $product
     *
     * @return array
     */
    public function getCustomData($config, $product)
    {
        $custom = array();
        if (isset($config['custom_name'])) {
            $custom['name'] = $this->reformatString($config['custom_name'], $product, '-');
        }

        if (isset($config['custom_description'])) {
            $custom['description'] = $this->reformatString($config['custom_description'], $product, '-');
        }

        return $custom;
    }

    /**
     * @param                            $data
     * @param Mage_Catalog_Model_Product $product
     * @param                            $symbol
     *
     * @return string
     */
    public function reformatString($data, $product, $symbol)
    {
        preg_match_all("/{{([^}]*)}}/", $data, $attributes);
        if (!empty($attributes)) {
            foreach ($attributes[0] as $key => $value) {
                if (!empty($product[$attributes[1][$key]])) {
                    if ($product->getAttributeText($attributes[1][$key])) {
                        $data = str_replace($value, $product->getAttributeText($attributes[1][$key]), $data);
                    } else {
                        $data = str_replace($value, $product[$attributes[1][$key]], $data);
                    }
                } else {
                    $data = str_replace($value, '', $data);
                }

                if ($symbol) {
                    $data = preg_replace(
                        '/' . $symbol . '+/', ' ' . $symbol . ' ',
                        rtrim(str_replace(' ' . $symbol . ' ', $symbol, $data), $symbol)
                    );
                }
            }
        }

        return trim($data);
    }

    /**
     * @param                            $productData
     * @param                            $config
     * @param Mage_Catalog_Model_Product $product
     *
     * @return array
     */
    public function getCategoryData($productData, $config, $product)
    {
        $category = array();
        $level = 0;
        if (!empty($productData['categories'])) {
            if (empty($config['category_type'])) {
                foreach ($productData['categories'] as $categories) {
                    if (count($categories['path']) > $level) {
                        $category['categoryPath'] = implode(' > ', $categories['path']);
                        $level = count($categories['custom_path']);
                    }
                }
            }

            if ($config['category_type'] == 'custom') {
                foreach ($productData['categories'] as $categories) {
                    if (count($categories['custom_path']) > $level) {
                        if (!empty($categories['custom'])) {
                            $category['categoryPath'] = implode(' > ', $categories['custom_path']);
                            $level = count($categories['custom_path']);
                        }
                    }
                }

                if (empty($category['categoryPath'])) {
                    $category['categoryPath'] = $config['category_default_t'];
                }
            }
        }

        if ($config['category_type'] == 'attribute') {
            if (!empty($config['category_attribute'])) {
                $category['categoryPath'] = $product->getAttributeText($config['category_attribute']);
                if (empty($category['categoryPath'])) {
                    $category['categoryPath'] = $product->getData($config['category_attribute']);
                }
            } else {
                $cat = $this->reformatString($config['category_default'], $product, '>');
                $category['categoryPath'] = Mage::helper('tradetracker')->cleanData($cat, 'stiptags');
            }
        }

        if (!empty($category['categoryPath'])) {
            $catdata = explode(' > ', $category['categoryPath']);
            if (isset($catdata[0])) {
                $category['categories'] = $catdata[0];
            }

            if (isset($catdata[1])) {
                $category['subcategories'] = $catdata[1];
            }

            if (isset($catdata[2])) {
                $category['subsubcategories'] = $catdata[2];
            }
        }

        return $category;
    }

    /**
     * @param                            $data
     * @param                            $confPrices
     * @param Mage_Catalog_Model_Product $product
     * @param                            $config
     * @param                            $parentId
     *
     * @return array
     */
    public function getPrices($data, $confPrices, $product, $config, $parentId)
    {
        $prices = array();
        $id = $product->getEntityId();
        $parentPriceIndex = $parentId . '_' . $id;
        if ($parentId && !empty($confPrices[$parentPriceIndex])) {
            $confPrice = $this->taxHelper->getPrice($product, $confPrices[$parentPriceIndex], true);
            $confPriceReg = $this->taxHelper->getPrice($product, $confPrices[$parentPriceIndex . '_reg'], true);
            if ($confPriceReg > $confPrice) {
                $prices['special_price'] = $this->helper->formatPrice($confPrice, $config);
                $prices['price'] = $this->helper->formatPrice($confPriceReg, $config);
            } else {
                $prices['price'] = $this->helper->formatPrice($confPrice, $config);
            }
        } else {
            if (isset($data['sales_price'])) {
                $prices['price'] = $data['regular_price'];
                $prices['sale_price'] = $data['sales_price'];
                if (isset($prices['fromPrice']) && $prices['fromPrice'] > 0) {
                    $discount = round(
                        ((($prices['price'] - $prices['fromPrice']) / $prices['fromPrice']) * -100),
                        2
                    ) . '%';
                } else {
                    $discount = null;
                }

                $prices['discount'] = $discount;
            } else {
                $prices['price'] = $data['price'];
            }
        }

        if (isset($data['sales_price'])) {
            $prices['fromPrice'] = $data['regular_price'];
            $prices['price'] = $data['sales_price'];
            $discount = round(((($prices['price'] - $prices['fromPrice']) / $prices['fromPrice']) * -100), 2) . '%';

            $prices['discount'] = $discount;
        } else {
            $prices['fromPrice'] = $data['price'];
            $prices['price'] = $data['price'];
        }

        return $prices;
    }

    /**
     * @param                            $data
     * @param                            $config
     * @param Mage_Catalog_Model_Product $product
     * @param                            $price
     *
     * @return array
     */
    public function getShipping($data, $config, $product, $price)
    {
        $shippingArray = array();
        if (($config['delivery'] == 'attribute') && ($config['delivery_att'])) {
            if (!empty($data[$config['delivery_att']])) {
                $shippingArray['deliveryTime'] = $data[$config['delivery_att']];
            }
        } else {
            if ($config['stock_manage']) {
                if ($product->getIsInStock()) {
                    $shippingArray['deliveryTime'] = $config['delivery_in'];
                } else {
                    $shippingArray['deliveryTime'] = $config['delivery_out'];
                }
            } else {
                $shippingArray['deliveryTime'] = $config['delivery_in'];
            }
        }

        if (!empty($data['price']['final_price_clean'])) {
            foreach ($config['shipping_prices'] as $shippingPrice) {
                if (($price >= $shippingPrice['price_from']) && ($price <= $shippingPrice['price_to'])) {
                    if ($shippingPrice['cost'] > 0) {
                        $shippingCost = $shippingPrice['cost'];
                        $shippingCost = $this->helper->formatPrice($shippingCost, $config);
                        $shippingArray['deliveryCosts'] = $shippingCost;
                    }
                }
            }
        }

        return $shippingArray;
    }

    /**
     * @param $config
     *
     * @return mixed
     */
    public function getUspText($config)
    {
        if (!empty($config['extra_info'])) {
            $extraInfo['extraInfo'] = $config['extra_info'];
            return $extraInfo;
        }
    }

    /**
     * @param $productData
     * @param $config
     *
     * @return array
     */
    public function getImages($productData, $config)
    {
        $_images = array();
        $i = 2;
        if ($config['images'] == 'all') {
            if (!empty($config['main_image'])) {
                if (!empty($productData['image'][$config['main_image']])) {
                    $_images['imageURL'] = $productData['image'][$config['main_image']];
                }
            } else {
                if (!empty($productData['image']['base'])) {
                    $_images['imageURL'] = $productData['image']['base'];
                }
            }

            if (empty($_images['imageURL'])) {
                $_images['imageURL'] = $productData['image_link'];
            }

            if (!empty($productData['image']['all'])) {
                foreach ($productData['image']['all'] as $image) {
                    if (!in_array($image, $_images)) {
                        $_images['imageURL' . $i] = $image;
                        $i++;
                    }
                }
            }
        }

        return $_images;
    }

    /**
     * @param $productRow
     *
     * @return mixed
     */
    public function processUnset($productRow)
    {
        if (isset($productRow['additional_imagelinks'])) {
            unset($productRow['additional_imagelinks']);
        }

        if (isset($productRow['image_link'])) {
            unset($productRow['image_link']);
        }

        return $productRow;
    }
}