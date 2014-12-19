<?php

class FilterController extends BaseAPIController {
    public function getFilterOptions($id){
        return Response::json($this->api->getFilterOptions($id));
    }

    public function getFilterOptionsByFilter(){
        $filter = json_decode(Input::get('filter'), true);
        return Response::json($this->api->getFiltersByFilter($filter));
    }

}