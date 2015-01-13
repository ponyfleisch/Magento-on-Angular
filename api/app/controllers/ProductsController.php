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
        return Response::json($this->api->getProductsByFilter($pagesize, $currentpage));
    }

    public function getSearchProducts(){
        $keyword = Input::get('keyword');
        $queryId = intval(Input::get('queryId'));
        $pageSize = intval(Input::get('pageSize'))?:12;
        $pageNumber = intval(Input::get('pageNumber'))?:1;

        return Response::json($this->api->getProductsByKeyword($keyword, $queryId, $pageSize, $pageNumber));
    }

}