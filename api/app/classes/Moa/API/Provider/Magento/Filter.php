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

        /** @var \AW_Layerednavigation_Model_Filter $filter */
        $filter = \Mage::getResourceModel('aw_layerednavigation/filter_collection')->getItemById($id);

        foreach ($filter->getOptionCollection() as $option) {
            $data = $option->getData();
            $item = [
                'title' => $data['title'],
                'id' => $data['additional_data']['option_id'],
            ];

            $items[] = $item;
        }
        return $items;
    }

}