<?php // strict

namespace Mapa\Methods;

use Plenty\Modules\Account\Contact\Contracts\ContactRepositoryContract;
use Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodService;
use Plenty\Plugin\ConfigRepository;

use Mapa\Methods\MapaPaymentMethodBase;

/**
 * Class MapaPaymentMethod
 * @package Mapa\Methods
 */
class MapaPaymentMethodOTSU extends MapaPaymentMethodBase
{
  var $type = 'OTSU';
}