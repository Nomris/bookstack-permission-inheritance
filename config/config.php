<?php
$GLOBALS['config'] = array(
    'bookstack' => array(
        'apiRateLimit' => '180',
        'baseUri' => '<APP_URL from .env>',
        'ignorCertificate' => true,
        'api' => array(
            'id' => '<TOKEN ID>',
            'secret' => '<TOKEN SECERT>'
        ),
        'permission' => array(
            'tag' => 'prem',
            'entrySeperator' => '|',
            'permissionIdentifier' => '=',
            'ignoreFallbackTag' => 'igf'
        )
    )
);
?>