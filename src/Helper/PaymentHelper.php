<?php //strict

namespace Mapa\Helper;

use Plenty\Modules\Payment\Models\PaymentProperty;
use Plenty\Plugin\ConfigRepository;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodRepositoryContract;
use Plenty\Modules\Payment\Contracts\PaymentOrderRelationRepositoryContract;
use Plenty\Modules\Payment\Contracts\PaymentRepositoryContract;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Payment\Method\Models\PaymentMethod;
use Plenty\Modules\Payment\Models\Payment;
use Plenty\Modules\Order\Models\Order;
use Plenty\Plugin\Log\Loggable;

use Mapa\Services\SessionStorageService;

/**
 * Class PaymentHelper
 * @package Mapa\Helper
 */
class PaymentHelper
{
  use Loggable;
    /**
     * @var PaymentMethodRepositoryContract
     */
    private $paymentMethodRepository;

    /**
     * @var ConfigRepository
     */
    private $config;

    /**
     * @var SessionStorageService
     */
    private $sessionService;

    /**
     * @var PaymentOrderRelationRepositoryContract
     */
    private $paymentOrderRelationRepo;

    /**
     * @var PaymentRepositoryContract
     */
    private $paymentRepository;

    /**
     * @var OrderRepositoryContract
     */
    private $orderRepo;

    /**
     * @var array
     */
    private $statusMap = array();

    /**
     * PaymentHelper constructor.
     *
     * @param PaymentMethodRepositoryContract $paymentMethodRepository
     * @param PaymentRepositoryContract $paymentRepo
     * @param PaymentOrderRelationRepositoryContract $paymentOrderRelationRepo
     * @param ConfigRepository $config
     * @param SessionStorageService $sessionService
     * @param OrderRepositoryContract $orderRepo
     */
    public function __construct(PaymentMethodRepositoryContract $paymentMethodRepository,
                                PaymentRepositoryContract $paymentRepo,
                                PaymentOrderRelationRepositoryContract $paymentOrderRelationRepo,
                                ConfigRepository $config,
                                SessionStorageService $sessionService,
                                OrderRepositoryContract $orderRepo)
    {
        $this->config                                   = $config;
        $this->sessionService                           = $sessionService;
        $this->paymentMethodRepository                  = $paymentMethodRepository;
        $this->paymentOrderRelationRepo                 = $paymentOrderRelationRepo;
        $this->paymentRepository                        = $paymentRepo;
        $this->orderRepo                                = $orderRepo;
        $this->statusMap                                = array();
    }

    /**
     * Create the ID of the payment method if it doesn't exist yet
     */
    public function createMopIfNotExists($paymethod, $payname)
    {
        // Check whether the ID of the Pay upon pickup payment method has been created
        if($this->getPaymentMethod($paymethod) == 'no_paymentmethod_found')
        {
            $paymentMethodData = array( 'pluginKey'   => 'mapa',
                                        'paymentKey'  => 'MAPA'.$paymethod,
                                        'name'        => 'Masterpayment '.$payname);
 
            $this->paymentMethodRepository->createPaymentMethod($paymentMethodData);
        }
    }
    
    /**
     * Load the ID of the payment method for the given plugin key
     * Return the ID for the payment method
     *
     * @param string $paymethod Paymethod Shortcut i.e. CC
     * @return string|int
     */
    public function getPaymentMethod($paymethod)
    {
        $paymentMethods = $this->paymentMethodRepository->allForPlugin('mapa');
 
        if( !is_null($paymentMethods) )
        {
            foreach($paymentMethods as $paymentMethod)
            {
                if($paymentMethod->paymentKey == 'MAPA'.$paymethod)
                {
                    return $paymentMethod->id;
                }
            }
        }
 
        return 'no_paymentmethod_found';
    }
    
    public function createPlentyPayment($data, $trxid, $uniqueid)
    {
      $this
            ->getLogger('PaymentHandler::createPlentyPayment')
            ->info('createPlentyPayment params', [
              'data' => $data,
              'trxid' => $trxid,
              'uniqueid' => $uniqueid,
            ]);
      
        /** @var Payment $payment */
        $payment = pluginApp( \Plenty\Modules\Payment\Models\Payment::class );

        $payment->mopId             = (int)$data['REQUEST']['CRITERION.MOPID'];
        $payment->transactionType   = Payment::TRANSACTION_TYPE_BOOKED_POSTING;
        $payment->status            = 2; // 1=wait approve 2=approve 3=CP 4=PartCP 5=cancel 6=refused 9=RF 10=PartRF
        $payment->currency          = $data['REQUEST']['PRESENTATION.CURRENCY'];
        $payment->amount            = $data['REQUEST']['PRESENTATION.AMOUNT'];
        $payment->receivedAt        = date('YmdHis');
        //$payment->method            = $this->paymentMethodRepository->findByPaymentMethodId((int)$data['REQUEST']['CRITERION.MOPID']);
      
        $this
            ->getLogger('PaymentHandler::createPlentyPayment')
            ->info('createPlentyPayment payment', [
              'payment' => $payment,
            ]);
        
        //try {
          $paymentProperty = [];
          $paymentProperty[] = $this->getPaymentProperty(PaymentProperty::TYPE_BOOKING_TEXT, 'TransactionID: '.(string)$trxid);
          //$paymentProperty[] = $this->getPaymentProperty(PaymentProperty::TYPE_REFERENCE_ID, (string)$uniqueid);
          $paymentProperty[] = $this->getPaymentProperty(PaymentProperty::TYPE_TRANSACTION_ID, $uniqueid);
          $paymentProperty[] = $this->getPaymentProperty(PaymentProperty::TYPE_ORIGIN, Payment::ORIGIN_PLUGIN);

          $this
            ->getLogger('PaymentHandler::createPlentyPayment')
            ->info('createPlentyPayment props', [
              'props' => $paymentProperty,
            ]);
          
          $payment->properties = $paymentProperty;

          $payment = $this->paymentRepository->createPayment($payment);
          
          $this
            ->getLogger('PaymentHandler::createPlentyPayment')
            ->info('createPlentyPayment payment after create', [
              'payment' => $payment,
            ]);
        /*
        } catch (Exception $e) {
          $this
           ->getLogger('PaymentHelper::createPlentyPayment')
           ->info('catch', [
            $e->getMessage()
          ]);
        }
        */
        
        return $payment;
    }
    
    /**
     * Assign the payment to an order in plentymarkets
     *
     * @param Payment $payment
     * @param int $orderId
     */
    public function assignPlentyPaymentToPlentyOrder(Payment $payment, int $orderId)
    {
      
      $this
            ->getLogger('PaymentHandler::assignPlentyPaymentToPlentyOrder')
            ->info('assignPlentyPaymentToPlentyOrder params', [
              'payment' => $payment,
              'orderId' => $orderId,
            ]);
      
      //try {
        // Get the order by the given order ID
        $order = $this->orderRepo->findOrderById($orderId);
        
        $this
            ->getLogger('PaymentHandler::assignPlentyPaymentToPlentyOrder')
            ->info('assignPlentyPaymentToPlentyOrder order', [
              'order' => $order,
            ]);

        // Check whether the order truly exists in plentymarkets
        if(!is_null($order) && $order instanceof Order)
        {
            // Assign the given payment to the given order
            $this->paymentOrderRelationRepo->createOrderRelation($payment, $order);
        }
      /*
      } catch (Exception $e) {
          $this
           ->getLogger('PaymentHelper::assignPlentyPaymentToPlentyOrder')
           ->info('catch', [
            $e->getMessage()
          ]);
      }
      */
    }
    
    /**
     * Returns a PaymentProperty with the given params
     *
     * @param Payment $payment
     * @param array $data
     * @return PaymentProperty
     */
    private function getPaymentProperty($typeId, $value)
    {
        /** @var PaymentProperty $paymentProperty */
        $paymentProperty = pluginApp( \Plenty\Modules\Payment\Models\PaymentProperty::class );

        $paymentProperty->typeId = $typeId;
        $paymentProperty->value = (string)$value;

        return $paymentProperty;
    }
    
}
