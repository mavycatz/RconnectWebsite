<?php

/**
 * Canonical role list for administrator accounts.
 *
 * Kept in its own side-effect-free file so the API authorization guard and the
 * models that write the users.role column enforce exactly the same set. This
 * file performs no output, starts no session and opens no connection, so it is
 * safe to require from a model.
 *
 * To add a role, add it here. Nothing else needs to change.
 */

return [
    // Roles any administrator account may hold.
    'assignable' => [
        'admin',
        'super_admin',
    ],

    // The privileged role. Only a holder of this role may grant or change it.
    'privileged' => 'super_admin',
];
