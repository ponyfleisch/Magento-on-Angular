<?php
namespace Moa\API\Provider\Magento;

/**
 * Magento API provider traits for Laravel
 *
 * @author Raja Kapur <raja.kapur@gmail.com>
 * @author Adam Timberlake <adam.timberlake@gmail.com>
 */
trait Product {

    /**
     * Returns product information for one product.
     *
     * @method getProduct
     * @param int $productId
     * @return array
     */
    public function getProduct($productId)
    {
        /** @var \Mage_Catalog_Model_Product $product */
        $product    = \Mage::getModel('catalog/product')->load((int) $productId);

        $products   = array();
        $models     = array();

        if ($product->getTypeId() === 'configurable') {

            $products   = $this->getProductVariations($productId);
            $productIds = array_flatten(array_map(function($product) {
                return $product['id'];
            }, $products['collection']));

            foreach ($productIds as $productId) {
                array_push($models, $this->getProduct($productId));
            }

        }

        /** @var \Mage_Sendfriend_Model_Sendfriend $friendModel */
        $friendModel = \Mage::getModel('sendfriend/sendfriend');

        /** @var Mage_CatalogInventory_Model_Stock_Item $stockModel */
        $stockModel = \Mage::getModel('cataloginventory/stock_item')->loadByProduct($product);

        return array(
            'id'            => $product->getId(),
            'sku'           => $product->getSku(),
            'name'          => $product->getName(),
            'type'          => $product->getTypeId(),
            'quantity'      => (int) $stockModel->getQty(),
            'friendUrl'     => $friendModel->canEmailToFriend() ? \Mage::app()->getHelper('catalog/product')->getEmailToFriendUrl($product) : null,
            'price'         => (float) $product->getPrice(),
            'colour'        => (int) $product->getData('color'),
            'manufacturer'  => (int) $product->getData('manufacturer'),
            'description'   => nl2br(trim($product->getDescription())),
            'largeImage'    => (string) str_replace('localhost', self::IMAGE_PATH, $product->getMediaConfig()->getMediaUrl($product->getData('image'))),
            'similar'       => $product->getRelatedProductIds(),
            'gallery'       => $product->getMediaGalleryImages(),
            'products'      => $products,
            'models'        => $models
        );
    }

    /**
     * Returns product information for child SKUs of product (colors, sizes, etc).
     * 
     * @method getProductVariations
     * @param int $productId
     * @return array
     */
    public function getProductVariations($productId)
    {
        /** @var \Mage_Catalog_Model_Product $product */
        $product = \Mage::getModel('catalog/product')->load((int) $productId);

        /** @var \Mage_Catalog_Model_Product $children */
        $children = \Mage::getModel('catalog/product_type_configurable')->getUsedProducts(null, $product);

        /** @var \Mage_Catalog_Model_Product $attributes */
        $attributes = $product->getTypeInstance(true)->getConfigurableAttributesAsArray($product);

        $products = array('label' => null, 'collection' => array());

        foreach ($children as $child) {

            foreach ($attributes as $attribute) {

                $products['label'] = $attribute['store_label'];

                foreach ($attribute['values'] as $value) {

                    $childValue = $child->getData($attribute['attribute_code']);

                    if ($value['value_index'] == $childValue) {
                        $products['collection'][] = array(
                            'id'    => (int) $child->getId(),
                            'label' => $value['store_label']
                        );
                    }

                }

            }

        }

        return $products;
    }

    /**
     * @method getProductOptions
     * @param string $attributeName
     * @param bool $processCounts
     * @return string
     */
    public function getProductOptions($attributeName, $processCounts)
    {
        /**
         * @method getCount
         * @param number $value
         * @return int
         */
        $getCount = function ($value) use ($attributeName) {
            $collection = \Mage::getModel('catalog/product')->getCollection();
            $collection->addFieldToFilter(array(array('attribute' => $attributeName, 'eq' => $value)));
            return count($collection);
        };

        $attribute = \Mage::getSingleton('eav/config')->getAttribute('catalog_product', $attributeName);
        $options   = array();

        if ($attribute->usesSource()) {
            $options = $attribute->getSource()->getAllOptions(false);
        }

        $response = array();

        foreach ($options as $option) {

            $current = array(
                'id'    => (int) $option['value'],
                'label' => $option['label']
            );

            if ($processCounts) {

                // Process the counts if the developer wants them to be!
                $response['count'] = $getCount($option['value']);

            }

            $response[] = $current;

        }

        return $response;
    }


    /**
     * @param int $id
     */
    public function getProductsByCategory($id){
        $collection = \Mage::getModel('catalog/category')->load($id)
            ->getProductCollection()
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('status', 1)
            ->addAttributeToFilter('visibility', array('neq' => 1))
            ->load();

        $output = [];

        /** @var \Mage_Catalog_Model_Product $product */
        foreach($collection as $product){
            $output[] = [
                'id'                => (int) $product->getId(),
                'name'              => trim($product->getName()),
                'ident'             => trim($this->createIdent($product->getName())),
                'price'             => (float) $product->getPrice(),
                'image'             => $product->getMediaConfig()->getMediaShortUrl($product->getData('image')),
                'manufacturer'      => (int) $product->getData('manufacturer'),
            ];
        }

        return $output;

    }

    /**
     * @method getCollectionForCache
     * @param callable $infolog
     * @return array
     */
    public function getCollectionForCache(callable $infolog = null)
    {
        $collection = array();
        $index = 1;

        $products = \Mage::getResourceModel('catalog/product_collection');
        $products->addAttributeToSelect('*');
        $products->addAttributeToFilter('visibility', array('neq' => 1));
        $products->addAttributeToFilter('status', 1);
        $products->load();

        foreach ($products as $product) {

            if (!is_null($infolog)) {
                $infolog(sprintf('Resolving model %d/%d', $index++, count($products)));
            }

            $ids         = array();
            $categoryIds = (int) $product->getCategoryIds();
            $categoryId  = $categoryIds[0];
            $type        = \Mage::getModel('catalog/category')->load($categoryId);

            foreach ($product->getCategoryIds() as $id) {
                array_push($ids, (int) $id);

                // Add any parent IDs as well.
                $category = \Mage::getModel('catalog/category')->load($id);

                if ($category->parent_id) {
                    $parentCategory = \Mage::getModel('catalog/category')->load($category->parent_id);

                    if ($parentCategory->parent_id) {;
                        array_push($ids, (int) $parentCategory->parent_id);
                    }

                    array_push($ids, (int) $category->parent_id);
                }
            }

            $collection[] = array(
                'id'                => (int) $product->getId(),
                'name'              => trim($product->getName()),
                'ident'             => trim($this->createIdent($product->getName())),
                'price'             => (float) $product->getPrice(),
                'image'             => (string) str_replace('localhost', self::IMAGE_PATH, $product->getMediaConfig()->getMediaUrl($product->getData('image'))),
                'colour'            => (int) $product->getData('color'),
                'manufacturer'      => (int) $product->getData('manufacturer'),
                'categories'        => array_unique($ids),
                'type'              => $type
            );

        }

        return $collection;
    }

    public function getProductsByFilter($filter, $pagesize, $currentpage){
        $collection = \Mage::getModel('catalog/product')->getCollection();
        $collection->addAttributeToSelect('*')
            ->addAttributeToFilter('status', 1)
            ->addAttributeToFilter('visibility', array('neq' => 1))
            ->setPageSize($pagesize)
            ->setCurPage($currentpage);

        // throw new \Exception("$pagesize $currentpage");


        $baseCategory = intval($filter['base_category']);

        $filterCategory = $baseCategory;

        $filters = $this->getCategoryFilters($baseCategory);

        foreach($filter as $filterName => $filterOptions){
            if($filters[$filterName]){
                switch($filters[$filterName]['type']){
                    case 'option_attribute':
                        $collection->addAttributeToFilter($filters[$filterName]['code'], array("in" => $filterOptions));
                        break;
                    case 'decimal_price':
                        $collection->addAttributeToFilter('price', array("gt" => $filterOptions[0], "lt" => $filterOptions[1]));
                }
            }else{
                if($filterName == 'subcategory'){
                    $filterCategory = $filterOptions;
                }
            }
            $collection->addCategoryFilter(\Mage::getModel('catalog/category')->load($filterCategory));
        };



        $products = $collection->load();

        $output = [];

        /** @var \Mage_Catalog_Model_Product $product */
        foreach($products as $product){
            $output[] = [
                'id'                => (int) $product->getId(),
                'name'              => trim($product->getName()),
                'ident'             => trim($this->createIdent($product->getName())),
                'price'             => (float) $product->getPrice(),
                'image'             => $product->getMediaConfig()->getMediaShortUrl($product->getData('image')),
                'manufacturer'      => (int) $product->getData('manufacturer'),
            ];
        }

        return $output;
    }

    public function getCategoryFilters($id){
        $items = [];
        $filterCollection = \Mage::getResourceModel('aw_layerednavigation/filter_collection')
            ->addFilterAttributes(\Mage::app()->getStore()->getId())
            ->addIsEnabledFilter()
            ->addCategoryFilter($id)
            ->sortByPosition()
        ;
        foreach ($filterCollection as $filter) {
            $filter->setStoreId(\Mage::app()->getStore()->getId());
            $itemData = $filter->getData();

            $item = [
                'id' => $itemData['entity_id'],
                'title' => $itemData['title'],
                'type' => $itemData['type'],
                'code' => $itemData['additional_data']['attribute_code'],
                'raw' => $itemData
            ];

            $items[$itemData['code']] = $item;
        }
        return $items;
    }


}