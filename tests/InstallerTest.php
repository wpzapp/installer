<?php
/**
 * Tests for the WP-ZAPP installer class.
 *
 * @package WPZAPP\Installer
 * @license GPL-3.0
 * @link    https://wpzapp.org
 */

namespace WPZAPP\Installer\Tests;

use WPZAPP\Installer\Installer;
use Composer\Util\Filesystem;
use Composer\Package\Package;
use Composer\Package\RootPackage;
use Composer\Composer;
use Composer\Config;
use Composer\Downloader\DownloadManager;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\IO\IOInterface;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

class InstallerTest extends TestCase
{

    private $composer;

    private $vendorDir;

    private $binDir;

    private $io;

    private $fs;

    public function setUp()
    {
        $this->composer = new Composer();

        $this->vendorDir = realpath(sys_get_temp_dir()) . DIRECTORY_SEPARATOR . 'wpzapp-test-vendor';
        $this->ensureDirectoryExistsAndClear($this->vendorDir);

        $this->binDir = realpath(sys_get_temp_dir()) . DIRECTORY_SEPARATOR . 'wpzapp-test-bin';
        $this->ensureDirectoryExistsAndClear($this->binDir);

        $config = new Config();
        $this->composer->setConfig($config);
        $config->merge(array(
            'config' => array(
                'vendor-dir' => $this->vendorDir,
                'bin-dir'    => $this->binDir,
            ),
        ));

        $dm = $this->getMockBuilder(DownloadManager::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->composer->setDownloadManager($dm);

        $this->io = $this->createMock(IOInterface::class);
    }

    public function tearDown()
    {
        $fs = new Filesystem;
        $fs->removeDirectory($this->vendorDir);
        $fs->removeDirectory($this->binDir);
    }

    /**
     * @dataProvider dataGetInstallPath
     */
    public function testGetInstallPath(string $type, string $name, string $path)
    {
        $package = new Package($name, '1.0.0', '1.0.0');
        $package->setType($type);

        $installer = new Installer($this->io, $this->composer);

        $result = $installer->getInstallPath($package);
        $this->assertSame($path, $result);
    }

    public function dataGetInstallPath(): array
    {
        return array(
            array('wpzapp-lib', 'wpzapp/my-library', 'wp-content/mu-plugins/wpzapp-lib/my-library/'),
            array('wpzapp-module', 'wpzapp/my-module', 'wp-content/mu-plugins/wpzapp-modules/my-module/'),
            array('wpzapp-module', 'no-vendor-module', 'wp-content/mu-plugins/wpzapp-modules/no-vendor-module/'),
        );
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testGetInstallPathInvalidType()
    {
        $package = new Package('wpzapp/invalid', '1.0.0', '1.0.0');
        $package->setType('invalid-lib');

        $installer = new Installer($this->io, $this->composer);

        $result = $installer->getInstallPath($package);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testGetInstallPathInvalidSubtype()
    {
        $package = new Package('wpzapp/invalid', '1.0.0', '1.0.0');
        $package->setType('wpzapp-invalid');

        $installer = new Installer($this->io, $this->composer);

        $result = $installer->getInstallPath($package);
    }

    public function testGetInstallPathCustomInstallerName()
    {
        $package = new Package('wpzapp/my-module', '1.0.0', '1.0.0');
        $package->setType('wpzapp-module');
        $package->setExtra(array(
            'installer-name' => 'custom-module',
        ));

        $installer = new Installer($this->io, $this->composer);

        $result = $installer->getInstallPath($package);
        $this->assertEquals('wp-content/mu-plugins/wpzapp-modules/custom-module/', $result);
    }

    public function testGetInstallPathCustomType()
    {
        $package = new Package('wpzapp/my-module', '1.0.0', '1.0.0');
        $package->setType('wpzapp-module');

        $installer = new Installer($this->io, $this->composer);

        $consumerPackage = new RootPackage('foo/bar', '1.0.0', '1.0.0');
        $consumerPackage->setExtra(array(
            'installer-paths' => array(
                'web/app/mu-plugins/wpzapp-modules/{$name}/' => array(
                    'type:wpzapp-module'
                ),
            ),
        ));

        $this->composer->setPackage($consumerPackage);

        $result = $installer->getInstallPath($package);
        $this->assertEquals('web/app/mu-plugins/wpzapp-modules/my-module/', $result);
    }

    public function testGetInstallPathCustomVendor()
    {
        $package = new Package('custom-vendor/my-module', '1.0.0', '1.0.0');
        $package->setType('wpzapp-module');

        $installer = new Installer($this->io, $this->composer);

        $consumerPackage = new RootPackage('foo/bar', '1.0.0', '1.0.0');
        $consumerPackage->setExtra(array(
            'installer-paths' => array(
                'wp-content/mu-plugins/wpzapp-custom-vendor-modules/{$name}/' => array(
                    'vendor:custom-vendor'
                ),
            ),
        ));

        $this->composer->setPackage($consumerPackage);

        $result = $installer->getInstallPath($package);
        $this->assertEquals('wp-content/mu-plugins/wpzapp-custom-vendor-modules/my-module/', $result);
    }

    public function testGetInstallPathCustomInvalid()
    {
        $package = new Package('wpzapp/my-module', '1.0.0', '1.0.0');
        $package->setType('wpzapp-module');

        $installer = new Installer($this->io, $this->composer);

        // Invalid installer paths will be ignored.
        $consumerPackage = new RootPackage('foo/bar', '1.0.0', '1.0.0');
        $consumerPackage->setExtra(array(
            'installer-paths' => array(
                'wp-content/mu-plugins/wpzapp-custom-vendor-modules/{$name}/' => array(
                    'invalid:whatever'
                ),
            ),
        ));

        $this->composer->setPackage($consumerPackage);

        $result = $installer->getInstallPath($package);
        $this->assertEquals('wp-content/mu-plugins/wpzapp-modules/my-module/', $result);
    }

    /**
     * @dataProvider dataSupports
     */
    public function testSupports(string $type, bool $supported)
    {
        $installer = new Installer($this->io, $this->composer);

        $this->assertSame($supported, $installer->supports($type));
    }

    public function dataSupports(): array
    {
        return array(
            array('wpzapp-lib', true),
            array('wpzapp-module', true),
            array('wpzapp-invalid', false),
            array('another-module', false),
            array('something-else', false),
        );
    }

    public function testUninstall()
    {
        $package = new Package('foo', '1.0.0', '1.0.0');

        $installer = $this->getMockBuilder(Installer::class)
            ->setMethods(array('getInstallPath'))
            ->setConstructorArgs(array($this->io, $this->composer))
            ->getMock();
        $installer->expects($this->once())
            ->method('getInstallPath')
            ->with($package)
            ->will($this->returnValue(sys_get_temp_dir().'/foo/'));

        $repo = $this->createMock(InstalledRepositoryInterface::class);
        $repo->expects($this->once())
            ->method('hasPackage')
            ->with($package)
            ->will($this->returnValue(true));
        $repo->expects($this->once())
            ->method('removePackage')
            ->with($package);

        $installer->uninstall($repo, $package);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testUninstallMissingPackage()
    {
        $package = new Package('foo', '1.0.0', '1.0.0');

        $installer = new Installer($this->io, $this->composer);

        $repo = $this->createMock(InstalledRepositoryInterface::class);
        $repo->expects($this->once())
            ->method('hasPackage')
            ->with($package)
            ->will($this->returnValue(false));

        $installer->uninstall($repo, $package);
    }

    protected function ensureDirectoryExistsAndClear(string $directory)
    {
        $fs = new Filesystem();
        if (is_dir($directory)) {
            $fs->removeDirectory($directory);
        }
        mkdir($directory, 0777, true);
    }
}
