<?php
namespace Moa\API\Provider\Magento;

/**
 * Magento API provider traits for Laravel
 *
 * @author Raja Kapur <raja.kapur@gmail.com>
 * @author Adam Timberlake <adam.timberlake@gmail.com>
 */
trait Cart {

    /**
     * @method addCartItem
     * @param int $productId
     * @param int $quantity
     * @return array
     */
    public function addCartItem($productId, $quantity)
    {
        $response = array('success' => true, 'error' => null, 'cart' => array());

        try {

            $this->frontEndSession();

            $product = \Mage::getModel('catalog/product')->load((int) $productId);

            $basket = \Mage::getSingleton('checkout/cart');
            $basket->addProduct($product, $quantity ?: 1);
            $basket->save();

            // Fetch the items from the user's basket.
            $response['cart'] = $this->getCartItems();

        } catch (\Exception $e) {

            $response['success'] = false;

            switch ($e->getMessage()) {

                case 'This product is currently out of stock.':
                    $response['error'] = 'stock';
                    break;

                default:
                    $response['error'] = 'unknown';
                    break;

            }

        }

        return $response;
    }

    /**
     * @method removeCartItem
     * @param int $id
     * @return array
     */
    public function removeCartItem($id)
    {
        /** @var \Mage_Checkout_Model_Cart $cart */
        $cart = \Mage::getSingleton('checkout/cart');

        $cart->getQuote()->removeItem($id)->save();

        $result = array(
            'success' => $cart->save(),
            'cart'  => $this->getCartItems()
        );

        return $result;
    }

    /**
     * @method getCartItems
     * @return array
     */
    public function getCartItems()
    {
        $this->frontEndSession();

        $session    = \Mage::getSingleton('checkout/session');
        $quote      = $session->getQuote();
        $items      = $quote->getAllItems();
        $data       = array();

        // Calculate all of the totals.
        $totals     = $quote->getTotals();
        $subTotal   = $totals['subtotal']->getValue();
        $grandTotal = $totals['grand_total']->getValue();

        foreach ($items as $item) {
            $product = \Mage::getSingleton('catalog/product')->load($item->getProduct()->getEntityId());

            if ($product->getTypeId() === 'simple') {
                $parentIds = \Mage::getResourceSingleton('catalog/product_type_configurable')
                                 ->getParentIdsByChild($item->getProduct()->getEntityId());
                $parentId = (int) $parentIds[0];
            }

            /** @var \Mage_Catalog_Helper_Image $image */
            $image = \Mage::helper('catalog/image')->init($product, 'small_image')->resize(135)->__toString();

            $data[] = array(
                'id'        => (int) $product->getEntityId(),
                'parentId'  => $parentId ?: null,
                'itemId'    => (int) $item->getItemId(),
                'quantity'  => (int) $item->getQty(),
                'product'   => [
                    'name'              => trim($product->getName()),
                    'ident'             => trim($this->createIdent($product->getName())),
                    'size'              => $product->getAttributeText('size'),
                    'designer'          => $product->getAttributeText('designer'),
                    'price'             => (float) $product->getFinalPrice(),
                    'oldPrice'          => (float) $product->getPrice(),
                    'status'            => intval($product->getIsSalable()),
                    'image'             => $product->getMediaConfig()->getMediaShortUrl($product->getData('small_image')),
                    'smallImage'        => strstr($image, 'catalog/product'),
                    'manufacturer'      => (int) $product->getData('manufacturer'),
                ]
            );


        }

        return array('subTotal' => $subTotal, 'grandTotal' => $grandTotal, 'items' => $data);
    }

}