<?php
/**
 * WP-ZAPP installer plugin for Composer.
 *
 * @package WPZAPP\Installer
 * @license GPL-3.0
 * @link    https://wpzapp.org
 */

namespace WPZAPP\Installer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

/**
 * Plugin class.
 *
 * Adds the custom installer to Composer.
 *
 * @since 1.0.0
 */
class Plugin implements PluginInterface
{

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $installer = new Installer($io, $composer);
        $composer->getInstallationManager()->addInstaller($installer);
    }
}
