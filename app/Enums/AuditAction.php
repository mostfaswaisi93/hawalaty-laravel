<?php

namespace App\Enums;

enum AuditAction: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case SignedOut = 'signedOut';
    case LoggedIn = 'loggedIn';
}
