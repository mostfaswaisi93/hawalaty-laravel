<?php

namespace App\Enums;

enum RoleKey: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Staff = 'staff';
}
