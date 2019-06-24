<?php

namespace Mapa\Containers;
 
use Plenty\Plugin\Templates\Twig;

use Mapa\Services\SessionStorageService;
 
class MapaErrorContainer
{
    /**
     * @var SessionStorageService
     */
    private $sessionStorage;
  
    public function call(Twig $twig, SessionStorageService $sessionStorage)
    {
        $status = $sessionStorage->getSessionValue('lastPS');
        $result = $sessionStorage->getSessionValue('lastPR');
        if (!empty($status)){
          $errorMSG = $status.': '.$result;
          $sessionStorage->setSessionValue('lastPS', NULL);
          $sessionStorage->setSessionValue('lastPR', NULL);
          return $twig->render('Masterpayment::content.error', ['errorText' => $errorMSG]);
        } else {
          return '';
        }
    }
}
