<?php
namespace Moa\API\Provider\Magento;

/**
 * Magento API provider traits for Laravel
 *
 * @author Raja Kapur <raja.kapur@gmail.com>
 * @author Adam Timberlake <adam.timberlake@gmail.com>
 */
trait Account {

    protected $profileFields = ['dbirth', 'country', 'phone', 'shoe', 'bottom', 'top'];

    /**
     * @method getCustomerModel
     * @return Mage_Customer_Model_Customer
     * @private
     */
    private function getCustomerModel()
    {
        // Gather the website and store preferences.
        $websiteId = \Mage::app()->getWebsite()->getId();
        $store     = \Mage::app()->getStore();

        // Update the customer model to reflect the current user.
        $customer = \Mage::getModel('customer/customer');
        $customer->website_id = $websiteId;
        $customer->setStore($store);

        return $customer;

    }

    /**
     * @method login
     * @param string $email
     * @param string $password
     * @return array
     */
    public function login($email, $password)
    {
        $response = array('success' => true, 'error' => null, 'model' => array());

        $customer = $this->getCustomerModel();
        $customer->loadByEmail($email);

        try {

            // Attempt the login procedure.
            $session = \Mage::getSingleton('customer/session');
            $session->login($email, $password);

            $account = $this->getAccount();
            $response['model'] = $account['model'];
            
        } catch (\Exception $e) {

            $response['success'] = false;

            switch ($e->getMessage()) {

                case 'Invalid login or password.':
                    $response['error'] = 'credentials';
                    break;

                default:
                    $response['error'] = 'unknown';
                    break;

            }

        }

        return $response;
    }

    /**
     * @method logout
     * @return array
     */
    public function logout()
    {
        \Mage::getSingleton('customer/session')->logout();
        $account = $this->getAccount();
        return array('success' => true, 'error' => null, 'model' => $account['model']);
    }

    /**
     * @method getAccount
     * @return array
     */
    public function getAccount()
    {
        $isLoggedIn = \Mage::getSingleton('customer/session')->isLoggedIn();

        if (!$isLoggedIn) {

            // User isn't logged in.
            return array('loggedIn' => false, 'model' => array());

        }

        // Gather the user data, and MD5 the email address for use with Gravatar.
        /** @var \Mage_Customer_Model_Customer $customer */
        $customer = \Mage::helper('customer')->getCustomer();
        $datum = $customer->getData();

        $datum['dob'] = $customer->getDbirth();
        $datum['country'] = $customer->getCrap();

        $datum['gravatar'] = md5($datum['email']);

        // Otherwise the user is logged in. Voila!
        return array('success' => true, 'loggedIn' => true, 'model' => $datum);
    }

    /**
     * @method register
     * @param string $firstName
     * @param string $lastName
     * @param string $email
     * @param string $password
     * @return array
     */
    public function register($firstName, $lastName, $email, $password)
    {
        $response = array('success' => true, 'error' => null, 'model' => array());
        $customer = $this->getCustomerModel();

        try {

            // If new, save customer information
            $customer->firstname     = $firstName;
            $customer->lastname      = $lastName;
            $customer->email         = $email;
            $customer->password_hash = md5($password);
            $customer->save();

            // Log in the newly created user.
            $this->login($email, $password);

            $account = $this->getAccount();
            $response['model'] = $account['model'];

        } catch (\Exception $e) {

            $response['success'] = false;

            switch ($e->getMessage()) {

                case 'Customer email is required':
                    $response['error'] = 'email';
                    break;

                case 'This customer email already exists':
                    $response['error'] = 'exists';
                    break;

                default:
                    $response['error'] = 'unknown';
                    break;

            }

        }

        return $response;
    }

    public function fbLogin($token){
        /** @var \Mage_Customer_Model_Session $session */
        $session = \Mage::getSingleton('customer/session');

        $data = json_decode(file_get_contents('https://graph.facebook.com/me?access_token='.$token));

        $email = $data->email;

        if(!$email){
            return array('success' => false, 'loggedIn' => false);
        }

        /** @var \Mage_Customer_Model_Customer $customer */
        $customer = $this->getCustomerModel()->loadByEmail($data->email);

        if($customer->getId()){
            $session->setCustomerAsLoggedIn($customer);
            return $this->getAccount();
        }else{
            $password = \Mage::helper('core')->getRandomString($length = 12);
            return $this->register($data->first_name, $data->last_name, $email, $password);
        }
    }
}