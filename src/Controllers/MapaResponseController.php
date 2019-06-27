<?php

namespace Mapa\Controllers;

use Plenty\Plugin\Http\Request;
use Plenty\Plugin\Http\Response;
use Plenty\Plugin\Controller;
use Plenty\Modules\Payment\Contracts\PaymentRepositoryContract;
//use Plenty\Modules\Payment\Method\Contracts\PaymentMethodRepositoryContract;
//use Plenty\Modules\Payment\Contracts\PaymentOrderRelationRepositoryContract;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Payment\Models\Payment;
use Plenty\Modules\Order\Models\Order;
use Plenty\Plugin\ConfigRepository;
use Plenty\Plugin\Templates\Twig;
use Mapa\Helper\PaymentHelper;
use Mapa\Services\SessionStorageService;
use Plenty\Plugin\Log\Loggable;


class MapaResponseController extends Controller
{
    use Loggable;

    /**
     * @var Request
     */
    private $request;
    
    /**
     * @var Response
     */
    private $response;
    
    /**
     * @var PaymentRepositoryContract
     */
    private $paymentRepository;

    /**
     * @var PaymentHelper
     */
    private $paymentHelper;
    
    /**
     * @var OrderRepositoryContract
     */
    private $orderRepo;
    
    /**
     * @var SessionStorageService
     */
    private $sessionStorage;
    
    /**
     * @var ConfigRepository
     */
    private $config;
    
    
     
    
  

    /**
     * PaymentNotificationController constructor.
     * @param Request $request
     * @param Response $response
     * @param PaymentRepositoryContract $paymentRepository
     * @param PaymentHelper $paymentHelper
     * @param SessionStorageService $sessionStorage
     * @param OrderRepositoryContract $orderRepo
     * @param \Mapa\Controllers\ConfigRepository $config
     */
    public function __construct(Request $request,
                                Response $response,
                                PaymentRepositoryContract $paymentRepository,
                                PaymentHelper $paymentHelper,
                                SessionStorageService $sessionStorage,
                                OrderRepositoryContract $orderRepo,                               
                                ConfigRepository $config)
    {
        $this->request            = $request;
        $this->response           = $response;
        $this->paymentRepository  = $paymentRepository;
        $this->paymentHelper      = $paymentHelper;
        $this->orderRepo          = $orderRepo;
        $this->sessionStorage     = $sessionStorage;
        $this->config             = $config;
      
       
    }

    public function getStyle()
    {
      return 'body{background-color: #f00}';
    }
    
    public function checkoutFailure(Twig $twig)
    {
      $this->sessionStorage->setSessionValue('lastPS', $_GET['ps']);
      $this->sessionStorage->setSessionValue('lastPR', $_GET['pr']);
     
      return $this->response->redirectTo('checkout');
    }
    
    public function checkoutSuccess()
    {
      $sender = $this->config->get('Masterpayment.security_sender');
      $chksum = md5($_GET['trxid'].$_GET['uniqueid'].$sender);
      $this->getLogger('MapaResponseController_checkoutSuccess')->info('data', ['get' => $_GET, 'sender' => $sender, 'chksum' => $chksum]);
      if ($_GET['chksum'] != $chksum){
        return $this->response->redirectTo('checkout');
      }
      $this->sessionStorage->setSessionValue('lastTrxID', $_GET['trxid']);
      $this->sessionStorage->setSessionValue('lastUniqueID', $_GET['uniqueid']);
      return $this->response->redirectTo('place-order');
      //return $this->response->redirectTo('confirmation');
    }
    
    public function handleResponse()
    {
        $headers = $this->request->header();

        $body = $this->request->getContent();
        $data = array();
        $tmp = explode('&', $body);
        foreach($tmp AS $v){
          $t = explode('=', $v);
          $data[$t[0]] = $t[1];
        }
        
        $this->getLogger('MapaResponseController_handleResponse')->info('post', ['data' => $data]);
        
        if ($data['PROCESSING.RESULT'] != 'ACK'){
          return urldecode($data['CRITERION.FAILURL'].'?ps='.$data['PROCESSING.STATUS'].'&pr='.$data['PROCESSING.RETURN']);
        } else {
          $sender = $this->config->get('Masterpayment.security_sender');
          $chksum = md5($data['IDENTIFICATION.SHORTID'].$data['IDENTIFICATION.UNIQUEID'].$sender);
          $params = '?trxid='.$data['IDENTIFICATION.SHORTID'].'&uniqueid='.$data['IDENTIFICATION.UNIQUEID'].'&chksum='.$chksum;
          return urldecode($data['CRITERION.SUCCESSURL']).$params;
        }
    }

}
