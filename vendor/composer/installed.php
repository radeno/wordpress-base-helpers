<?php return array(
    'root' => array(
        'name' => 'radeno/wordpress-base-helpers',
        'pretty_version' => '1.4.9',
        'version' => '1.4.9.0',
        'reference' => null,
        'type' => 'wordpress-plugin',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'dev' => true,
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
            'pretty_version' => '2.8.0',
            'version' => '2.8.0.0',
            'reference' => '1cd8b3ad2d43e04f4c2c6a240495af44780f809b',
            'type' => 'library',
            'install_path' => __DIR__ . '/../giggsey/locale',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
        'radeno/wordpress-base-helpers' => array(
            'pretty_version' => '1.4.9',
            'version' => '1.4.9.0',
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
    ),
);
