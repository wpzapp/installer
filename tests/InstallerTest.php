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

class InstallerTest extends TestCase
{

    private $composer;

    private $config;

    private $vendorDir;

    private $binDir;

    private $dm;

    private $repository;

    private $io;

    private $fs;

    public function setUp()
    {
        $this->fs = new Filesystem;

        $this->composer = new Composer();
        $this->config = new Config();
        $this->composer->setConfig($this->config);

        $this->vendorDir = realpath(sys_get_temp_dir()) . DIRECTORY_SEPARATOR . 'baton-test-vendor';
        $this->ensureDirectoryExistsAndClear($this->vendorDir);

        $this->binDir = realpath(sys_get_temp_dir()) . DIRECTORY_SEPARATOR . 'baton-test-bin';
        $this->ensureDirectoryExistsAndClear($this->binDir);

        $this->config->merge(array(
            'config' => array(
                'vendor-dir' => $this->vendorDir,
                'bin-dir'    => $this->binDir,
            ),
        ));

        $this->dm = $this->getMockBuilder(DownloadManager::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->composer->setDownloadManager($this->dm);

        $this->repository = $this->createMock(InstalledRepositoryInterface::class);
        $this->io = $this->createMock(IOInterface::class);
    }

    public function tearDown()
    {
        $this->fs->removeDirectory($this->vendorDir);
        $this->fs->removeDirectory($this->binDir);
    }

    /**
     * @dataProvider dataGetInstallPath
     */
    public function testGetInstallPath(string $type, string $name, string $path)
    {
        $installer = new Installer($this->io, $this->composer);
        $package = new Package($name, '1.0.0', '1.0.0');

        $package->setType($type);
        $result = $installer->getInstallPath($package);

        $this->assertSame($path, $result);
    }

    public function dataGetInstallPath(): array
    {
        return array(
            array('wpzapp-lib', 'wpzapp/my-library', 'wp-content/mu-plugins/wpzapp-lib/my-library/'),
            array('wpzapp-module', 'wpzapp/my-module', 'wp-content/mu-plugins/wpzapp-modules/my-module/'),
        );
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

    protected function ensureDirectoryExistsAndClear(string $directory)
    {
        $fs = new Filesystem();
        if (is_dir($directory)) {
            $fs->removeDirectory($directory);
        }
        mkdir($directory, 0777, true);
    }
}
