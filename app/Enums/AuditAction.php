<?php

namespace App\Enums;

/**
 * Actions recorded in a tenant's own `audit_log`. Deliberately a SMALLER
 * set than a much earlier design draft's platform-level action list:
 * `suspended` / `activated` / `planChanged` are license/plan concepts that
 * belong to the platform's own `activity_logs` table, not a tenant's
 * internal audit trail.
 */
enum AuditAction: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case SignedOut = 'signedOut';
    case LoggedIn = 'loggedIn';
}
