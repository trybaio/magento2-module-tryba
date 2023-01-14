<?php
namespace WAF\Tryba\Controller\Response;

use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\App\Action\Context;
use Magento\Checkout\Model\Session;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use WAF\Tryba\Logger\Logger;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Response\Http;
use Magento\Sales\Model\Order\Payment\Transaction\Builder as TransactionBuilder;
use Magento\Sales\Model\Order\Payment\Transaction;

class Index extends \Magento\Framework\App\Action\Action
{
	protected $_objectmanager;
	protected $_checkoutSession;
	protected $_orderFactory;
	protected $urlBuilder;
	private $logger;
	protected $response;
	protected $config;
	protected $messageManager;
	protected $transactionRepository;
	protected $cart;
	protected $inbox;
	 
	public function __construct(Context $context,
			Session $checkoutSession,
			OrderFactory $orderFactory,
			Logger $logger,
			ScopeConfigInterface $scopeConfig,
			Http $response,
			TransactionBuilder $tb,
			 \Magento\Checkout\Model\Cart $cart,
			 \Magento\AdminNotification\Model\Inbox $inbox,
			 \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository
		) {

        $this->checkoutSession = $checkoutSession;
        $this->orderFactory = $orderFactory;
        $this->response = $response;
        $this->config = $scopeConfig;
        $this->transactionBuilder = $tb;
		$this->logger = $logger;					
        $this->cart = $cart;
        $this->inbox = $inbox;
        $this->transactionRepository = $transactionRepository;
		$this->urlBuilder = \Magento\Framework\App\ObjectManager::getInstance()
							->get('Magento\Framework\UrlInterface');
		parent::__construct($context);
    }

	public function execute() {
		$payment_id = $this->getRequest()->getParam('payment_id');
		$storedPaymentRequestId = $this->checkoutSession->getPaymentRequestId();
		
		if ($payment_id) {
			$this->logger->info("Callback called with payment ID: " . $payment_id);
			if ($payment_id != $storedPaymentRequestId) {
				$this->logger->info("Payment ID not matched");
				$this->_redirect($this->urlBuilder->getBaseUrl());
            }
     
			try {
				// get Client credentials from configurations
				$storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
				$public_key = $this->config->getValue("payment/tryba/public_key", $storeScope);
				$secret_key = $this->config->getValue("payment/tryba/secret_key", $storeScope);
				$this->logger->info("Public Key: $public_key | Secret Key: $secret_key");
				
				// get payment response from tryba
                $url = 'https://checkout.tryba.io/api/v1/payment-intent/' . $payment_id;
                $curl = curl_init();
                curl_setopt($curl, CURLOPT_URL, $url);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                $authorization = "SECRET-KEY: " . $secret_key;
                curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json' , $authorization));
                $response = curl_exec($curl);
                $err = curl_error($curl);
                curl_close($curl);

                if ($err) {
                    $this->logger->info("Get payment response from Tryba fail.");
			        $this->_redirect($this->urlBuilder->getBaseUrl());
                } else {
                    $response = json_decode($response);
                    $payment_status = $response->status;
                    $this->logger->info("Response from server: ". PHP_EOL . print_r($response, true));
                    if (isset($payment_status)) {
                        $orderId = $response->externalId;
                        $orderId = explode("-" , $orderId);
                        $orderId = $orderId[1];
                        $this->logger->info("Extracted order id from trasaction id: " . $orderId);
                        
                        // get order and payment objects
                        $order = $this->orderFactory->create()->loadByIncrementId($orderId);
                        $payment = $order->getPayment();
                        
                        if ($order) {
                            if ($payment_status === "SUCCESS") {
                                $order->setState(Order::STATE_PROCESSING)
                                        ->setStatus($order->getConfig()->getStateDefaultStatus(Order::STATE_PROCESSING));
                                $transaction = $this->transactionRepository->getByTransactionId(
                                        "-1",
                                        $payment->getId(),
                                        $order->getId()
                                );
                                
                                if ($transaction) {
                                    $transaction->setTxnId($payment_id);
                                    $transaction->setAdditionalInformation(  
                                        "Tryba Transaction Id", $payment_id
                                    );
                                    $transaction->setAdditionalInformation(  
                                        "status", "successful"
                                    );
                                    $transaction->setIsClosed(1);
                                    $transaction->save();
                                }
                                
                                $payment->addTransactionCommentsToOrder(
                                    $transaction,
                                   "Transaction is completed successfully"
                                );
                                $payment->setParentTransactionId(null); 
                                
                                // send new email
                                $order->setCanSendNewEmailFlag(true);
                                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                                $objectManager->create('Magento\Sales\Model\OrderNotifier')->notify($order);
                                
                                $payment->save();
                                $order->save();
                                
                                $this->logger->info("Payment for $payment_id was credited.");
                                $this->_redirect($this->urlBuilder->getUrl('checkout/onepage/success/',  ['_secure' => true]));
                            } else if ($payment_status === "CANCELLED") {
                                $transaction = $this->transactionRepository->getByTransactionId(
                                        "-1",
                                        $payment->getId(),
                                        $order->getId()
                                );
                                $transaction->setTxnId($payment_id);
                                $transaction->setAdditionalInformation(  
                                        "Tryba Transaction Id", $payment_id
                                    );
                                $transaction->setAdditionalInformation(  
                                        "status", "successful"
                                    );
                                $transaction->setIsClosed(1);
                                $transaction->save();
                                $payment->addTransactionCommentsToOrder(
                                    $transaction,
                                    "The transaction is failed"
                                );
                                try {
                                    $items = $order->getItemsCollection();
                                    foreach($items as $item) {
                                        $this->cart->addOrderItem($item);
                                    }
                                    $this->cart->save();
                                } catch(Exception $e) {
                                    $message = $e->getMessage();
                                    $this->logger->info("Not able to add Items to cart Exception Message: " . $message);
                                }
                                $order->cancel();
    
                                $payment->setParentTransactionId(null);
                                $payment->save();
                                $order->save();
                                $this->logger->info("Payment for $payment_id failed.");
                                $this->_redirect($this->urlBuilder->getUrl('checkout/cart',  ['_secure' => true]));
                            }
                        } else {
                            $this->logger->info("Order not found with order id $orderId");
                        }
                    }
                }
			} catch(CurlException $e) {
				$this->logger->info($e);
				$this->_redirect($this->urlBuilder->getBaseUrl());
			} catch(ValidationException $e) {
				// handle exceptions related to response from the server.
				$this->logger->info($e->getMessage() . " with ");
				// add message into inbox of admin if authorization error.
				if (stristr($e->getMessage(), "Authorization")) {
					$this->inbox->addCritical("Tryba Authorization Error", "Please contact to Tryba for troubleshooting. ". $e->getMessage());
				}
				$this->logger->info(print_r($e->getResponse(), true) . "");
			} catch(Exception $e) {
				$this->logger->info($e->getMessage());
				$this->logger->info("Payment for $payment_id was not credited.");
				$this->_redirect($this->urlBuilder->getBaseUrl());
			}	 
		} else {
			$this->logger->info("Callback called with no payment ID or payment_request Id.");
			$this->_redirect($this->urlBuilder->getBaseUrl());
		}
	}
}