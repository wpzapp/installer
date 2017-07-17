<?php
/**
 * Tests for the WP-ZAPP installer class.
 *
 * @package WPZAPP\Installer
 * @license GPL-3.0
 * @link    https://wpzapp.org
 */

namespace WPZAPP\Installer\Tests;

use WPZAPP\Installer\Plugin;
use Composer\Composer;
use Composer\Config;
use Composer\IO\IOInterface;
use Composer\Installer\InstallationManager;
use PHPUnit\Framework\TestCase;

class PluginTest extends TestCase
{

    public function testActivate()
    {
        $installationManager = $this->getMockBuilder(InstallationManager::class)
            ->setMethods(array('addInstaller'))
            ->disableOriginalConstructor()
            ->getMock();
        $installationManager->expects($this->once())
            ->method('addInstaller');

        $composer = $this->getMockBuilder(Composer::class)
            ->setMethods(array('getInstallationManager'))
            ->disableOriginalConstructor()
            ->getMock();
        $composer->expects($this->once())
            ->method('getInstallationManager')
            ->will($this->returnValue($installationManager));

        $config = new Config();
        $composer->setConfig($config);
        $config->merge(array(
            'config' => array(
                'vendor-dir' => realpath(sys_get_temp_dir()) . DIRECTORY_SEPARATOR . 'wpzapp-test-vendor',
                'bin-dir'    => realpath(sys_get_temp_dir()) . DIRECTORY_SEPARATOR . 'wpzapp-test-bin',
            ),
        ));

        $plugin = new Plugin();
        $plugin->activate($composer, $this->createMock(IOInterface::class));
    }
}
