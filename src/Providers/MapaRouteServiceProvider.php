<?php

namespace Mapa\Providers;

use Plenty\Plugin\RouteServiceProvider;
use Plenty\Plugin\Routing\Router;

/**
 * Class MapaRouteServiceProvider
 * @package Mapa\Providers
 */
class MapaRouteServiceProvider extends RouteServiceProvider
{
    /**
     * @param Router $router
     */
    public function map(Router $router)
    {
        $router->get('payment/mapa/style',            'Mapa\Controllers\MapaResponseController@getStyle');
        $router->post('payment/mapa/response',        'Mapa\Controllers\MapaResponseController@handleResponse');
        $router->get('payment/mapa/checkout_failure', 'Mapa\Controllers\MapaResponseController@checkoutFailure');
        $router->get('payment/mapa/checkout_success', 'Mapa\Controllers\MapaResponseController@checkoutSuccess');
    }
}
