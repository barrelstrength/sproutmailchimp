<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit8e4a7249d976a77c8997944e6006f5ec
{
    public static $prefixesPsr0 = array (
        'M' => 
        array (
            'Mailchimp' => 
            array (
                0 => __DIR__ . '/..' . '/mailchimp/mailchimp/src',
            ),
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixesPsr0 = ComposerStaticInit8e4a7249d976a77c8997944e6006f5ec::$prefixesPsr0;

        }, null, ClassLoader::class);
    }
}
