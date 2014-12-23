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

    public function getProductsByFilter($pagesize, $currentpage){
        $layerBlock = $this->getLayerBlock();

        $request = \Mage::app()->getRequest();

        /** @var \Mage_Catalog_Model_Layer $layer */
        $layer = $layerBlock->getLayer();

        $collection = $layer->getProductCollection();

        $collection->setPageSize($pagesize);
        $collection->setCurPage($currentpage);

        switch($request->getParam('sort')){
            case 'price ASC':
                $collection->setOrder('price', 'ASC');
                break;
            case 'price DESC':
                $collection->setOrder('price', 'DESC');
                break;
            default:
                $collection->setOrder('entity_id', 'DESC');

        }

        $products = $collection->load();

        $output = [];

        /** @var \Mage_Catalog_Model_Product $product */
        foreach($products as $product){

            /** @var \Mage_Catalog_Helper_Image $image */
            $image = \Mage::helper('catalog/image')->init($product, 'small_image')->resize(135)->__toString();

            $output[] = [
                'id'                => (int) $product->getId(),
                'name'              => trim($product->getName()),
                'ident'             => trim($this->createIdent($product->getName())),
                'size'              => $product->getAttributeText('size'),
                'designer'          => $product->getAttributeText('designer'),
                'price'             => (float) $product->getFinalPrice(),
                'oldPrice'          => (float) $product->getPrice(),
                'status'            => intval($product->getIsSalable()),
                'image'             => $product->getMediaConfig()->getMediaShortUrl($product->getData('small_image')),
                'smallImage'        => strstr($image, 'catalog/product'),
                'manufacturer'      => (int) $product->getData('manufacturer'),
                // 'raw' => $product->getData()
            ];
        }

        return $output;
    }

    /**
     * @return \AW_Layerednavigation_Block_Layer
     */
    protected function getLayerBlock(){
        $items = [];

        $request = \Mage::app()->getRequest();

        $layer = \Mage::getSingleton('catalog/layer');

        if($request->getParam('category')){
            $layer->setCurrentCategory(\Mage::getModel('catalog/category')->load($request->getParam('category')));
        }


        /** @var \AW_Layerednavigation_Block_Layer $layer */
        $layerBlock = \Mage::app()->getLayout()->createBlock('aw_layerednavigation/layer');


        return $layerBlock;
    }

    public function getFiltersByFilter(){
        $items = [];
        $request = \Mage::app()->getRequest();

        $layerBlock = $this->getLayerBlock();
        $layer = $layerBlock->getLayer();

        // this has to be done in a seperate pass so all filters are set
        foreach($layerBlock->getFilterList() as $filter){
            // $filter->setLayer($layer)->apply($request);
        }

        /** @var \AW_Layerednavigation_Model_Filter $filter */
        foreach($layerBlock->getFilterList() as $filter){
            // $filter->setLayer($layer)->apply($request);
            $code = $filter->getData('code');

            if($code == 'category' || count($filter->getCount()) == 0) continue;


            // $filter->setLayer($layer->getLayer())->apply($request);
            // $filter->getCount();
            // if($code != 'style') $filter->apply($request);


            $itemData = $filter->getData();

            $items[$code] = [
                'id' => $itemData['entity_id'],
                'title' => $itemData['title'],
                'type' => $itemData['type'],
                'code' => $itemData['additional_data']['attribute_code'],
                // 'raw' => $itemData
            ];
            $items[$code]['count'] = $filter->getCount();
            $items[$code]['options'] = [];

            /** @var \AW_Layerednavigation_Block_Filter_Type_Abstract $block */
            $block = \Mage::helper('aw_layerednavigation/filter')->createFrontendFilterRendererBlock($filter);


            /** @var \AW_Layerednavigation_Model_Filter_Option $option */
            foreach($block->getOptionList() as $option){
                $data = $option->getData();
                $item = [
                    'title' => $data['title'],
                    'id' => $data['option_id'],
                    // 'raw' => $data
                ];

                if($items[$code]['count'][$data['option_id']]){
                    $items[$code]['options'][] = $item;
                }
            }
        }

        return $items;
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