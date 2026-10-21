<?php

return [
    'locales' => ['ar' => 'العربية', 'fr' => 'Français', 'en' => 'English'],
    'licenses' => ['all_rights_reserved', 'public_domain', 'cc_by', 'cc_by_sa', 'permission'],
    'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', env('TRUSTED_PROXIES', ''))))),
    'scanner' => env('VIRUS_SCANNER', 'clamscan'),
    'max_file_kb' => 20480,
    'max_import_bytes' => 5 * 1024 * 1024,
    'contact_email' => env('CONTACT_EMAIL'),
];
