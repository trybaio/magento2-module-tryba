<?php
namespace WAF\Tryba\Model\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Checkout\Model\Session;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;

class ControllerActionPredispatch implements ObserverInterface {
	protected $checkoutSession;
	protected $orderFactory;
	protected $_redirect;
	
    public function __construct (
		Session $checkoutSession,
		OrderFactory $orderFactory,
		\Magento\Framework\App\Response\Http $redirect
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->orderFactory = $orderFactory;
		$this->_redirect = $redirect;
    }
	
    public function execute(\Magento\Framework\Event\Observer $observer) {
		$request = $observer->getData('request'); 
		if ($request->getModuleName() == "checkout" && $request->getActionName() == "success") {
			$orderId = $this->checkoutSession->getLastOrderId();
			if ($orderId) {
				$order = $this->orderFactory->create()->load($orderId);
				if ($order->getPayment()->getMethodInstance()->getCode()== "tryba" && $order->getState() == Order::STATE_NEW){
					$this->urlBuilder = \Magento\Framework\App\ObjectManager::getInstance()
							->get('Magento\Framework\UrlInterface');
					$url = $this->urlBuilder->getUrl("tryba/redirect");
					$this->_redirect->setRedirect($url);
				}
			}
		}
	}
}