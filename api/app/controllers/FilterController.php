<?php

class FilterController extends BaseAPIController {
    public function getFilterOptions($id){
        return Response::json($this->api->getFilterOptions($id));
    }

}