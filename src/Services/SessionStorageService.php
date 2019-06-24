<?php //strict

namespace Mapa\Services;

use Plenty\Modules\Frontend\Session\Storage\Contracts\FrontendSessionStorageFactoryContract;

/**
 * Class SessionStorageService
 * @package Mapa\Services
 */
class SessionStorageService
{
    const DELIVERY_ADDRESS_ID   = "deliveryAddressId";
    const BILLING_ADDRESS_ID    = "billingAddressId";

    /**
     * @var FrontendSessionStorageFactoryContract
     */
    private $sessionStorage;

    /**
     * SessionStorageService constructor.
     * @param FrontendSessionStorageFactoryContract $sessionStorage
     */
    public function __construct(FrontendSessionStorageFactoryContract $sessionStorage)
    {
        $this->sessionStorage = $sessionStorage;
    }

    /**
     * Set the session value
     *
     * @param string $name
     * @param $value
     */
    public function setSessionValue(string $name, $value)
    {
        $this->sessionStorage->getPlugin()->setValue($name, $value);
    }

    /**
     * Get the session value
     *
     * @param string $name
     * @return mixed
     */
    public function getSessionValue(string $name)
    {
        return $this->sessionStorage->getPlugin()->getValue($name);
    }
}

