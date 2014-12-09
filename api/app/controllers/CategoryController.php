<?php

class CategoryController extends BaseAPIController {
    public function getCategoryFilters($id){
        return Response::json($this->api->getCategoryFilters($id));
    }

}