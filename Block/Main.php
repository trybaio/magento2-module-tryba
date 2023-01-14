<?php
namespace WAF\Tryba\Block;

use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\View\Element\Template\Context;
use Magento\Checkout\Model\Session;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use WAF\Tryba\Logger\Logger;
use Magento\Framework\App\Response\Http;
use Magento\Sales\Model\Order\Payment\Transaction\Builder as TransactionBuilder;

class Main extends \Magento\Framework\View\Element\Template {
    protected $_objectmanager;
    protected $checkoutSession;
    protected $orderFactory;
    protected $urlBuilder;
    private $logger;
    protected $response;
    protected $config;
    protected $messageManager;
    protected $transactionBuilder;
    protected $inbox;
    protected $_storeManager;
    
    public function __construct(Context $context,
        Session $checkoutSession,
        OrderFactory $orderFactory,
        Logger $logger,
        Http $response,
        TransactionBuilder $tb,
        \Magento\AdminNotification\Model\Inbox $inbox,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->orderFactory = $orderFactory;
        $this->response = $response;
        $this->config = $context->getScopeConfig();
        $this->transactionBuilder = $tb;
        $this->logger = $logger;
        $this->inbox = $inbox;
        $this->_storeManager = $storeManager;
        $this->urlBuilder = \Magento\Framework\App\ObjectManager::getInstance()->get('Magento\Framework\UrlInterface');
        parent::__construct($context);
    }

	protected function _prepareLayout() {
		$method_data = array();
		$orderId = $this->checkoutSession->getLastOrderId();
		$this->logger->info('Creating Order for orderId: ' . $orderId);
		$order = $this->orderFactory->create()->load($orderId);
		if ($order) {
			$billing = $order->getBillingAddress();
			$payment = $order->getPayment();
			
			$payment->setTransactionId("-1");
			$payment->setAdditionalInformation(  
			    [\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => array("Transaction is yet to complete")]
			);
			$trn = $payment->addTransaction(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE, null, true);
			$trn->setIsClosed(0)->save();
			$payment->addTransactionCommentsToOrder(
                $trn,
                "The transaction is yet to complete."
            );

            $payment->setParentTransactionId(null);
            $payment->save();
            $order->save();
 
			try {
				$storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
				$public_key = $this->config->getValue("payment/tryba/public_key", $storeScope);
				$secret_key = $this->config->getValue("payment/tryba/secret_key", $storeScope);
				$this->logger->info("Public Key: $public_key | Secret Key: $secret_key");
				
				$api_data['transaction_id'] = time() . "-" . $order->getRealOrderId();
				$api_data['email'] = $billing->getEmail();
				$api_data['first_name'] = $billing->getFirstname();
                $api_data['last_name'] = $billing->getLastname();
				$api_data['amount'] = round((int)$order->getGrandTotal(), 2);
				$api_data['currency'] = $this->_storeManager->getStore()->getCurrentCurrencyCode();
				$api_data['redirect_url'] = $this->urlBuilder->getUrl("tryba/response");

                // validate data before send to Tryba payment
                foreach($api_data as $key => $value) {
                    if ($key == 'currency') {
                        // get supported currency from Tryba
                        $url = 'https://tryba.io/api/currency-supported2';
                        $curl = curl_init();
                        curl_setopt($curl, CURLOPT_URL, $url);
                        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                        $response = curl_exec($curl);
                        $err = curl_error($curl);
                        curl_close($curl);

                        if ($err) {
                            $this->logger->info("Get supported currency from Tryba fail.");
                            $method_data['errors'][] = "Get supported currency from Tryba fail.";
                        } else {
                            $currencies = json_decode($response);
                            $supported_currency_arr = array();
                            if ($currencies->currency_code && $currencies->currency_name) {
                                foreach ($currencies->currency_name as $currency_name) {
                                    $supported_currency_arr[] = $currency_name;
                                }
                            }
                            if (!in_array($value, $supported_currency_arr)) {
                                $this->logger->info("Current currency({$value}) is not support in Tryba.");
                                $method_data['errors'][] = "The payment setting of this website is not correct, please contact Administrator";
                            }
                        }
                    }
                    if ($key == 'transaction_id' && empty($value)) {
                        $this->logger->info("It seems that something is wrong with your order. Please try again");
                        $method_data['errors'][] = "It seems that something is wrong with your order. Please try again";
                    }
                    if ($key == 'amount' && (empty($value) || !is_numeric($value))) {
                        $this->logger->info("It seems that you have submitted an invalid price for this order. Please try again");
                        $method_data['errors'][] = "It seems that you have submitted an invalid price for this order. Please try again";
                    }
                    if ($key == 'email' && empty($value)) {
                        $this->logger->info("Your email is empty or not valid. Please check and try again");
                        $method_data['errors'][] = "Your email is empty or not valid. Please check and try again";
                    }
                    if ($key == 'first_name' && empty($value)) {
                        $this->logger->info("Your first name is empty or not valid. Please check and try again");
                        $method_data['errors'][] = "Your first name is empty or not valid. Please check and try again";
                    }
                    if ($key == 'last_name' && empty($value)) {
                        $this->logger->info("Your last name is empty or not valid. Please check and try again");
                        $method_data['errors'][] = "Your last name is empty or not valid. Please check and try again";
                    }
                    if ($key == 'redirect_url' && empty($value)) {
                        $this->logger->info("The payment setting of this website is not correct, please contact Administrator");
                        $method_data['errors'][] = "The payment setting of this website is not correct, please contact Administrator";
                    }
                }
                if (empty($public_key) || empty($secret_key)) {
                    $this->logger->info("The payment setting of this website is not correct, please contact Administrator");
                    $method_data['errors'][] = "The payment setting of this website is not correct, please contact Administrator";
                }
				
                if (!isset($method_data['errors'])) {
                    $this->logger->info("Date sent for creating order " . print_r($api_data, true));
                    // post data to tryba
                    $url = 'https://checkout.tryba.io/api/v1/payment-intent/create';
        
                    $postfields = array(
                        "amount" => $api_data['amount'],
                        "externalId" => $api_data['transaction_id'],
                        "first_name" => $api_data['first_name'],
                        "last_name" => $api_data['last_name'],
                        "meta" => array(),
                        "email" => $api_data['email'],
                        "redirect_url" => $api_data['redirect_url'],
                        "currency" => $api_data['currency']
                    );
                    
                    $curl = curl_init();
                    
                    curl_setopt($curl, CURLOPT_URL, $url);
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($curl, CURLOPT_POST, sizeof($postfields));
                    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($postfields));
                    $authorization = "PUBLIC-KEY: " . $public_key;
                    curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json' , $authorization));
                    $response = curl_exec($curl);
                    $err = curl_error($curl);
                    curl_close($curl);
                    $response = json_decode($response);
                    $this->logger->info("Response from Tryba: ". print_r($response, true));
                    if ($err) {
                        $this->logger->info('Post data to Tryba error');
                        $method_data['errors'][] = "Your payment has some wrong, please contact Administrator";
                    } else {
                        $external_url = $response->externalUrl;
                        $this->setAction($external_url);
                        $this->checkoutSession->setPaymentRequestId($response->paymentIntent->id);
                    }
                }
			} catch(\CurlException $e) {
				// handle exception related to connection to the sever
				$this->logger->info((string)$e);
				$method_data['errors'][] = $e->getMessage();
			} catch(\ValidationException $e) {
				// handle exceptions related to response from the server.
				$this->logger->info($e->getMessage() . " with ");
				if (stristr($e->getMessage(), "Authorization")) {
					$inbox->addCritical("Tryba Authorization Error", $e->getMessage());
				}
				$this->logger->info(print_r($e->getResponse(),true) . "");
				$method_data['errors'] = $e->getErrors();			
			} catch(\Exception $e) {
                // handled common exception messages which will not get caught above.
				$method_data['errors'][] = $e->getMessage();
				$this->logger->info('Error While Creating Order : ' . $e->getMessage());
			}
		} else {
			$this->logger->info('Order with ID ' . $orderId . ' not found. Quitting :-(');
            $method_data['errors'][] = 'Order with ID ' . $orderId . ' not found, please contact Administrator';
		}

        if (isset($method_data['errors']) && is_array($method_data['errors'])) {
            $this->setMessages($method_data['errors']);
        }
	}
}