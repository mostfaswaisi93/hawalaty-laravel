<?php

namespace App\Enums;

/** The 3 built-in tenant roles - see D:\Angular\Hawalaty\src\app\data\roles.ts. */
enum RoleKey: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Staff = 'staff';
}
