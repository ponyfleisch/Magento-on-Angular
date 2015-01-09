<?php
namespace Moa\API\Provider\Magento;

/**
 * Magento API provider traits for Laravel
 *
 * @author Claudio Mettler <github@ponyfleisch.ch>
 */
trait Order {
    protected $addressFields = ['firstname', 'lastname', 'email', 'prefix', 'middlename', 'suffix', 'company', 'street', 'city', 'region', 'region_id', 'country_id', 'postcode', 'telephone'];

    /**
     * @return \Mage_Sales_Model_Order
     */
    protected function getNewOrder(){
        /** @var \Mage_Sales_Model_Order $order */
        $order = \Mage::getModel('sales/order');
        return $order;
    }

    /**
     * @return \Mage_Checkout_Model_Session
     */
    protected function getCheckoutSession(){
        return \Mage::getSingleton('checkout/session');
    }

    /**
     * @return \Mage_Customer_Model_Customer
     */
    protected function getCustomer(){
        return $this->getCustomerSession()->getCustomer();
    }

    /**
     * @return \Mage_Customer_Model_Session
     */
    protected function getCustomerSession(){
        return \Mage::getSingleton('customer/session');
    }

    protected function getPaymentMethods(){
        /** @var \Mage_Payment_Helper_Data $helper */
        $helper = \Mage::helper('payment/data');

        $result = [];

        $quote = $this->getQuote();

        /** @var \Mage_Payment_Model_Method_Abstract $method */
        foreach($helper->getStoreMethods(null, $quote) as $method){
            if($method->canUseForCountry($quote->getBillingAddress()->getCountryId())){
                $result[] = ['title' => $method->getTitle(), 'id' => $method->getCode()];
            }
        }

        return $result;
    }

    protected function getShippingMethods(){
        $result = [];

        /** @var \Mage_Shipping_Model_Config $c */
        $c = \Mage::getSingleton('shipping/config');

        $methods = \Mage::getSingleton('shipping/config')->getActiveCarriers();

        $address = $this->getQuote()->getShippingAddress();

        $address->setCollectShippingRates(true)->collectShippingRates()->save();

        $rates = $address->getGroupedAllShippingRates();

        foreach($rates as $rate){
            foreach($rate as $choice){
                $result[] = ['id' => $choice->getCode(), 'title' => $choice->getMethodTitle(), 'price' => $choice->getPrice()];
            }
        }


        return $result;
    }

    /**
     * @return \Mage_Sales_Model_Quote
     */
    public function getQuote(){
        return $this->getCheckoutSession()->getQuote();
    }

    public function getState()
    {
        $this->frontEndSession();

        // i don't know why, but without this, not all shipping methods show.
        $this->saveState();

        $customerSession = $this->getCustomerSession();
        $result = [];

        $result['payment_methods'] = $this->getPaymentMethods();
        $result['shipping_methods'] = $this->getShippingMethods();

        /** @var \Mage_Customer_Model_Customer $customer */
        $customer = $this->getCustomer();

        $defaultShipping = $customer->getDefaultShippingAddress();
        $defaultBilling = $customer->getDefaultBillingAddress();

        $result['logged_in'] = $customerSession->isLoggedIn();


        $quote = $this->getCheckoutSession()->getQuote();


        /** @var \Mage_Sales_Model_Quote_Address $shippingAddress */
        $shippingAddress = $quote->getShippingAddress();

        /** @var \Mage_Sales_Model_Quote_Address $billingAddress */
        $billingAddress = $quote->getBillingAddress();


        // potentially overwritten further down. beware.
        $result['billing_address'] = $this->getAddressValues($billingAddress);
        $result['billing_address']['id'] = $billingAddress->getId();

        $result['shipping_address'] = $this->getAddressValues($shippingAddress);
        $result['shipping_address']['id'] = $shippingAddress->getId();

        $result['shipping_address_validation'] = $shippingAddress->validate();

        $result['billing_address_validation'] = $billingAddress->validate();

        if ($defaultShipping) {
            if ($shippingAddress->getCustomerAddressId() == $defaultShipping->getId()) {
                $result['shipping_address'] = 'default';
            }

            $result['default_shipping_address'] = $this->getAddressValues($defaultShipping);
            $result['default_shipping_address']['id'] = $defaultShipping->getId();

            if ($defaultShipping->getCountryModel()) {
                $result['default_shipping_address']['country'] = $defaultShipping->getCountryModel()->getName();
            }
            if ($defaultShipping->getRegionModel()) {
                $result['default_shipping_address']['region'] = $defaultShipping->getRegionModel()->getName();
            }
        }else{
            $result['default_shipping_address'] = false;
        }

        if($shippingAddress->getSameAsBilling() == 1){
            $result['shipping_address'] = 'use_billing';
        }

        if($defaultBilling){
            if($billingAddress->getCustomerAddressId() == $defaultBilling->getId()){
                $result['billing_address'] = 'default';
            }

            $result['default_billing_address'] = $this->getAddressValues($defaultBilling);
            $result['default_billing_address']['id'] = $defaultBilling->getId();

            if($defaultBilling->getCountryModel()){
                $result['default_billing_address']['country'] = $defaultBilling->getCountryModel()->getName();
            }
            if($defaultBilling->getRegionModel()){
                $result['default_billing_address']['region'] = $defaultBilling->getRegionModel()->getName();
            }
        }else{
            $result['default_billing_address'] = false;
        }

        $result['cart'] = $this->getCartItems();

        $result['session_id'] = $this->getCheckoutSession()->getId();
        $result['quote_id'] = $quote->getId();

        $result['customer_id'] = $quote->getCustomer()->getId();

        $result['shipping_method'] = $quote->getShippingAddress()->getShippingMethod();
        $result['payment_method'] = $quote->getPayment()->getMethod();

        return $result;
    }

    public function getCountryList(){
        $result = [];
        /** @var \Mage_Directory_Model_Resource_Country_Collection $countryCollection */
        $countryCollection = \Mage::getModel('directory/country')->getResourceCollection()->load();


        /** @var \Mage_Directory_Model_Country $country */
        foreach($countryCollection as $country){
            $result[] = [
                'id' => $country->getCountryId(),
                'name' => $country->getName()
            ];

        }

        usort($result, function($a, $b){
            return $a['name'] > $b['name'];
        });

        return $result;
    }

    public function getRegionsByCountry($country){
        $collection = \Mage::getResourceModel('directory/region_collection')->addCountryFilter($country)->load();

        $results = [];

        foreach($collection as $region){
            $results[] = [
                'id' => $region->getId(),
                'name' => $region->getName()
            ];
        }

        if(count($results)){
            usort($result, function($a, $b){
                return $a['name'] > $b['name'];
            });

            return $results;
        }else{
            return false;
        }
    }

    public function setShippingAddress($values){
        $quote = $this->getCheckoutSession()->getQuote();

        /** @var \Mage_Sales_Model_Quote_Address $shippingAddress */
        $address = $quote->getShippingAddress();

        if(!is_array($values)){
            if($values == 'default'){
                /** @var \Mage_Customer_Model_Customer $customer */
                $customer = $this->getCustomer();
                $defaultShipping = $customer->getDefaultShippingAddress();
                $this->setAddressValues($address, $defaultShipping->getData());
                $address->setCustomerAddressId($defaultShipping->getId());
                $address->setSameAsBilling(false);
            }else if($values == 'use_billing'){
                $this->setAddressValues($address, $quote->getBillingAddress()->getData());
                $address->setSameAsBilling(true);
//                $address->setCustomerAddressId($)
            }
        }else{
            $this->setAddressValues($address, $values);
            $address->setCustomerAddressId(null);
            $address->setSameAsBilling(0);
        }

        $quote->getShippingAddress()->setCollectShippingRates(true);
        $quote->getShippingAddress()->collectShippingRates();

        $this->saveState();

        return $this->getState();
    }

    protected function setAddressValues($address, $values){
        foreach($this->addressFields as $field){
            if(array_key_exists($field, $values)){
                $address->setData($field, $values[$field]);
            }else{
                $address->setData($field, '');
            }
        }

        return $address;
    }

    protected function getAddressValues($address){
        $result = [];
        foreach($address->getData() as $key => $item){
            if(in_array($key, $this->addressFields)){
                $result[$key] = $item;
            }
        }

        return $result;
    }

    protected function saveState(){
        $quote = $this->getCheckoutSession()->getQuote();
        $quote->collectTotals()->save();

        $this->getCheckoutSession()->setQuoteId($quote->getId());
    }

    public function setBillingAddress($values){
        $quote = $this->getCheckoutSession()->getQuote();

        /** @var \Mage_Sales_Model_Quote_Address $address */
        $address = $quote->getBillingAddress();

        if(!is_array($values)){
            if($values == 'default'){
                /** @var \Mage_Customer_Model_Customer $customer */
                $customer = $this->getCustomer();
                $defaultBilling = $customer->getDefaultBillingAddress();
                $this->setAddressValues($address, $defaultBilling->getData());
                $address->setCustomerAddressId($defaultBilling->getId());
            }
        }else{
            $this->setAddressValues($address, $values);
            $address->setCustomerAddressId(null);
        }

        $this->saveState();

        return $this->getState();
    }

    public function setShippingMethod($code){
        $quote = $this->getCheckoutSession()->getQuote();

        $quote->getShippingAddress()->setFreeMethodWeight(0);
        $quote->getShippingAddress()->setShippingMethod($code);

        $r = $quote->getShippingAddress()->requestShippingRates();

        $quote->getShippingAddress()->setCollectShippingRates(true);
        $quote->getShippingAddress()->collectShippingRates();

        $this->saveState();

        return $this->getState();
    }

    public function setPaymentMethod($code){
        $quote = $this->getCheckoutSession()->getQuote();

        $quote->getPayment()->importData(array('method' => $code));

        $this->saveState();

        return $this->getState();
    }

    public function setDiscountCode($code){


        $this->saveState();
        return $this->getState();
    }

    public function sendOrder(){
        $quote = $this->getCheckoutSession()->getQuote();
        $quote->collectTotals()->save();
        try{
            $service = \Mage::getModel('sales/service_quote', $quote);
            $service->submitAll();
            $order = $service->getOrder();
            $order->setStatus('complete');
            $order->save();
        }catch(\Exception $e){
            return ['success' => false, 'message' => $e->getMessage()];
        }

        return ['success' => true];
    }

}