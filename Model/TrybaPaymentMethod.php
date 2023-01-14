<?php
namespace WAF\Tryba\Model;

class TrybaPaymentMethod extends \Magento\Payment\Model\Method\AbstractMethod
{
	protected $_isInitializeNeeded = false;
    protected $redirect_uri;
    protected $_code = 'tryba';
 	protected $_canOrder = true;
	protected $_isGateway = true;
	
    public function getOrderPlaceRedirectUrl() {
	  	return \Magento\Framework\App\ObjectManager::getInstance()->get('Magento\Framework\UrlInterface')->getUrl("tryba/redirect");
 	} 
}