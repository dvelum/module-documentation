<?php
return [
    'id' => 'dvelum-module-documentation',
    'version' => '2.0.0',
    'author' => 'Kirill Yegorov',
    'name' => 'DVelum API Documentation',
    'configs' => './configs',
    'locales' => './locales',
    'resources' =>'./resources',
    'vendor'=>'Dvelum',
    'autoloader'=> [
        './classes'
    ],
    'objects' =>[
        'sysdocs_class_method_param',
        'sysdocs_class_method',
        'sysdocs_class_property',
        'sysdocs_class',
        'sysdocs_file',
        'sysdocs_localization'
    ],
    'post-install'=>'\\Dvelum\\Documentation\\Installer'
];