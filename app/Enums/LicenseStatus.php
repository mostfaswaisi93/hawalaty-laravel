<?php

namespace App\Enums;

enum LicenseStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case Suspended = 'suspended';
    case Expired = 'expired';
}
