<?php
namespace Moa\API\Provider\Magento;

/**
 * Magento API provider traits for Laravel
 *
 * @author Claudio Mettler <github@ponyfleisch.ch>
 */
trait Content {
    public function getContent($identifier){
        $block  = \Mage::getModel('cms/block')
            ->setStoreId(\Mage::app()->getStore()->getId())
            ->load($identifier);

        return $block->getContent();
    }
}