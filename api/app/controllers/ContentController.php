<?php

class ContentController extends BaseAPIController {

    /**
     * @method getContent
     * @param string $identifier
     * @return string
     */
    public function getContent($identifier) {
        return Response::json([ 'content' => $this->api->getContent($identifier) ]);
    }

}