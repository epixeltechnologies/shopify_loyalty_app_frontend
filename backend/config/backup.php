<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Backup GPG recipient
    |--------------------------------------------------------------------------
    | The GPG key ID/email backups are encrypted to when `db:backup
    | --encrypt` is used. Requires the corresponding public key to
    | already be imported into the server's GPG keyring — see
    | docs/BACKUP_AND_RECOVERY.md for the full setup procedure. This is
    | NOT configured by default; `--encrypt` without this set fails
    | loudly rather than silently storing an unencrypted backup.
    */
    'gpg_recipient' => env('BACKUP_GPG_RECIPIENT'),
];
