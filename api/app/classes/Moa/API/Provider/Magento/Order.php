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
                $result[$method->getCode()] = $method->getTitle();
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
                $result[$choice->getCode()] = ['title' => $choice->getMethodTitle(), 'price' => $choice->getPrice()];
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

    public function getState(){
        $this->frontEndSession();

        // i don't know why, but without this, not all shipping methods show.
        $this->saveState();

        $customerSession = $this->getCustomerSession();
        $result = [];

        $result['payment_methods'] = $this->getPaymentMethods();
        $result['shipping_methods'] = $this->getShippingMethods();

        $customer = $this->getCustomer();

        $result['logged_in'] = $customerSession->isLoggedIn();


        $quote = $this->getCheckoutSession()->getQuote();

//
//        $quote->getShippingAddress()->setCountryId('SG');
//        $quote->getBillingAddress()->setCountryId('SG');
//
//        $quote->getShippingAddress()->setShippingMethod('flatrate_flatrate');
//        $quote->getShippingAddress()->setCollectShippingRates(true);
//        $quote->getShippingAddress()->collectShippingRates();
//
//
//        $quote->getPayment()->importData(array('method' => 'cashondelivery'));
//        $quote->collectTotals()->save();


        // $result['countries'] = $countryCollection = \Mage::getModel('directory/country')->getResourceCollection()->load();

        // $result['quote'] = $quote->getData();


        $result['billing_address'] = $this->getAddressValues($quote->getBillingAddress());

        $result['shipping_address'] = $this->getAddressValues($quote->getShippingAddress());

        $result['cart'] = $this->getCartItems();

        $result['session_id'] = $this->getCheckoutSession()->getId();

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
            $result[$country->getCountryId()] = $country->getName();
        }

        return $result;
    }

    public function setShippingAddress($values){
        $quote = $this->getCheckoutSession()->getQuote();
        $address = $quote->getShippingAddress();

        $this->setAddressValues($address, $values);

        $quote->getShippingAddress()->setCollectShippingRates(true);
        $quote->getShippingAddress()->collectShippingRates();

        $this->saveState();

        return $this->getState();
    }

    protected function setAddressValues($address, $values){
        foreach($this->addressFields as $field){
            if(array_key_exists($field, $values)){
                $address->setData($field, $values[$field]);
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
        $address = $quote->getBillingAddress();

        $this->setAddressValues($address, $values);

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