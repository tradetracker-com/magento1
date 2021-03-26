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

class TradeTracker_Connect_Model_Adminhtml_System_Config_Source_Category
{

    /**
     * Options array
     *
     * @var array
     */
    public $options = null;

    /***
     * @return array
     */
    public function toOptionArray()
    {
        if (!$this->options) {
            $options = array();
            $collection = Mage::getResourceModel('catalog/category_collection');
            $collection->addAttributeToSelect('name')->addPathFilter('^1/[0-9/]+')->load();
            $cats = array();

            foreach ($collection as $category) {
                $cat = new stdClass();
                $cat->label = $category->getName();
                $cat->value = $category->getId();
                $cat->level = $category->getLevel();
                $cat->parentid = $category->getParentId();
                $cats[$cat->value] = $cat;
            }

            foreach ($cats as $id => $cat) {
                if (isset($cats[$cat->parentid])) {
                    if (!isset($cats[$cat->parentid]->child)) {
                        $cats[$cat->parentid]->child = array();
                    }

                    $cats[$cat->parentid]->child[] =& $cats[$id];
                }
            }

            foreach ($cats as $id => $cat) {
                if (!isset($cats[$cat->parentid])) {
                    $stack = array($cats[$id]);
                    while (count($stack) > 0) {
                        $opt = array_pop($stack);
                        $option = array(
                            'label' => ($opt->level > 1 ? str_repeat('- ', $opt->level - 1) : '') . $opt->label,
                            'value' => $opt->value
                        );
                        array_push($options, $option);
                        if (isset($opt->child) && count($opt->child)) {
                            foreach (array_reverse($opt->child) as $child) {
                                array_push($stack, $child);
                            }
                        }
                    }
                }
            }

            $this->options = $options;
        }

        return $this->options;
    }

}