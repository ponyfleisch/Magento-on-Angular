<?php
namespace Moa\API\Provider\Magento;

/**
 * Magento API provider traits for Laravel
 *
 * @author Claudio Mettler <github@ponyfleisch.ch>
 */
trait Filter {
    public function getFilterOptions($id){
        $items = [];

        $filter = \Mage::getResourceModel('aw_layerednavigation/filter_collection')->getItemById($id);

        foreach ($filter->getOptionCollection() as $option) {
            $items[] = $option->getData();
        }
        return $items;
    }

}