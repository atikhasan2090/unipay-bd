<?php

namespace Unipay\BD\Enums;

enum GatewayDriver: string
{
    case BKASH = 'bkash';
    case NAGAD = 'nagad';
    case ROCKET = 'rocket';
    case UPAY = 'upay';
    case CELLFIN = 'cellfin';
    case SSLCOMMERZ = 'sslcommerz';
    case SHURJOPAY = 'shurjopay';
}
