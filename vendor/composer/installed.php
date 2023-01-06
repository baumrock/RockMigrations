<?php return array(
    'root' => array(
        'name' => 'baumrock/rockmigrations',
        'pretty_version' => 'dev-main',
        'version' => 'dev-main',
        'reference' => 'eb12ce1e4c22ac121e0b0cc1dda9c3008ca46bb7',
        'type' => 'processwire-module',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'dev' => true,
    ),
    'versions' => array(
        'baumrock/rockmigrations' => array(
            'pretty_version' => 'dev-main',
            'version' => 'dev-main',
            'reference' => 'eb12ce1e4c22ac121e0b0cc1dda9c3008ca46bb7',
            'type' => 'processwire-module',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
        'composer/installers' => array(
            'pretty_version' => 'v1.12.0',
            'version' => '1.12.0.0',
            'reference' => 'd20a64ed3c94748397ff5973488761b22f6d3f19',
            'type' => 'composer-plugin',
            'install_path' => __DIR__ . '/./installers',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
        'roundcube/plugin-installer' => array(
            'dev_requirement' => false,
            'replaced' => array(
                0 => '*',
            ),
        ),
        'shama/baton' => array(
            'dev_requirement' => false,
            'replaced' => array(
                0 => '*',
            ),
        ),
        'symfony/polyfill-ctype' => array(
            'pretty_version' => 'v1.27.0',
            'version' => '1.27.0.0',
            'reference' => '5bbc823adecdae860bb64756d639ecfec17b050a',
            'type' => 'library',
            'install_path' => __DIR__ . '/../symfony/polyfill-ctype',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
        'symfony/yaml' => array(
            'pretty_version' => 'v6.2.2',
            'version' => '6.2.2.0',
            'reference' => '6ed8243aa5f2cb5a57009f826b5e7fb3c4200cf3',
            'type' => 'library',
            'install_path' => __DIR__ . '/../symfony/yaml',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
    ),
);
