<?php
/*
 * Created by 2C2P
 * Date 20 June 2017
 * This Response action method is responsible for handle the 2c2p payment gateway response.
 */

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;

if (interface_exists("Magento\Framework\App\CsrfAwareActionInterface"))
    include __DIR__ . "/m2_23.php";
else
    include __DIR__ . "/m2_22.php";