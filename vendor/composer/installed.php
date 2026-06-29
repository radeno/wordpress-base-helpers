<?php return array(
    'root' => array(
        'name' => 'radeno/wordpress-base-helpers',
        'pretty_version' => '1.4.7',
        'version' => '1.4.7.0',
        'reference' => null,
        'type' => 'wordpress-plugin',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'dev' => false,
    ),
    'versions' => array(
        'giggsey/libphonenumber-for-php' => array(
            'pretty_version' => '8.13.55',
            'version' => '8.13.55.0',
            'reference' => '6e28b3d53cf96d7f41c83d9b80b6021ecbd00537',
            'type' => 'library',
            'install_path' => __DIR__ . '/../giggsey/libphonenumber-for-php',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
        'giggsey/libphonenumber-for-php-lite' => array(
            'dev_requirement' => false,
            'replaced' => array(
                0 => '8.13.55',
            ),
        ),
        'giggsey/locale' => array(
            'pretty_version' => '2.9.0',
            'version' => '2.9.0.0',
            'reference' => 'fe741e99ae6ccbe8132f3d63d8ec89924e689778',
            'type' => 'library',
            'install_path' => __DIR__ . '/../giggsey/locale',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
        'radeno/wordpress-base-helpers' => array(
            'pretty_version' => '1.4.7',
            'version' => '1.4.7.0',
            'reference' => null,
            'type' => 'wordpress-plugin',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
        'symfony/polyfill-mbstring' => array(
            'dev_requirement' => false,
            'replaced' => array(
                0 => '*',
            ),
        ),
        'symfony/polyfill-php80' => array(
            'dev_requirement' => false,
            'replaced' => array(
                0 => '*',
            ),
        ),
    ),
);
