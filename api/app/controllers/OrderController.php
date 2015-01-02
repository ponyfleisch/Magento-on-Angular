<?php

class OrderController extends BaseAPIController
{
    public function getQuote()
    {
        $quote = $this->api->getQuote();
        return Response::json($quote);
    }

    public function getState()
    {
        $state = $this->api->getState();
        return Response::json($state);
    }

    public function setShippingAddress(){
        $address = Input::get('address');

        return Response::json($this->api->setShippingAddress($address));
    }

    public function setBillingAddress(){
        $address = Input::get('address');

        return Response::json($this->api->setBillingAddress($address));

    }
    public function setPaymentMethod(){
        return Response::json($this->api->setPaymentMethod(Input::get('code')));

    }
    public function setShippingMethod(){
        return Response::json($this->api->setShippingMethod(Input::get('code')));
    }

    public function setDiscountCode(){

    }

    public function getCountryList(){
        return Response::json($this->api->getCountryList());
    }
}