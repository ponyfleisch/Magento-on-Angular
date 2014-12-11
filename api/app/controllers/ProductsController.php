<?php

class ProductsController extends BaseAPIController {

    /**
     * @const PRODUCTS_CACHE_KEY
     * @type string
     */
    const PRODUCTS_CACHE_KEY = 'products';

    /**
     * @method getProducts
     * @return string
     */
    public function getProducts() {
        Artisan::call('products');
        return Cache::get(self::PRODUCTS_CACHE_KEY);
    }
    /**
     * @method getCategoryProducts
     * @return string
     */
    public function getCategoryProducts($id) {
        return Response::json($this->api->getProductsByCategory($id));
    }

    public function getFilterProducts($pagesize, $currentpage){
        $filter = json_decode(Input::get('filter'));
        return Response::json($this->api->getProductsByFilter($filter, $pagesize, $currentpage));
    }



}