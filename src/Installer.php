<?php
/**
 * WP-ZAPP installer plugin for Composer.
 *
 * @package WPZAPP\Installer
 * @license GPL-3.0
 * @link    https://wpzapp.org
 */

namespace Composer\Installers;

use Composer\IO\IOInterface;
use Composer\Installer\LibraryInstaller;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;

/**
 * Installer class.
 *
 * Handles installation of WP-ZAPP packages.
 *
 * @since 1.0.0
 */
class Installer extends LibraryInstaller
{

    /**
     * @var string Package type identifier.
     */
    const NAME = 'wpzapp';

    /**
     * @var array Associative array of subpackage types and their locations.
     */
    const LOCATIONS = array(
        'lib'    => 'wp-content/mu-plugins/wpzapp-lib/{$name}/',
        'module' => 'wp-content/mu-plugins/wpzapp-modules/{$name}/'
    );

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    public function getInstallPath(PackageInterface $package)
    {
        $type = $package->getType();
        if (substr($type, 0, strlen(self::NAME)) !== self::NAME) {
            throw new \InvalidArgumentException(
                'Sorry the package type of this package is not yet supported.'
            );
        }

        $subPackageType = substr($type, strlen(self::NAME) + 1);

        $prettyName = $package->getPrettyName();
        if (strpos($prettyName, '/') !== false) {
            list($vendor, $name) = explode('/', $prettyName);
        } else {
            $vendor = '';
            $name = $prettyName;
        }

        $extra = $package->getExtra();
        if (!empty($extra['installer-name'])) {
            $name = $extra['installer-name'];
        }

        $availableVars = compact('name', 'vendor', 'type');

        if ($this->composer->getPackage()) {
            $extra = $this->composer->getPackage()->getExtra();
            if (!empty($extra['installer-paths'])) {
                $customPath = $this->mapCustomInstallPaths($extra['installer-paths'], $prettyName, $type, $vendor);
                if (!empty($customPath)) {
                    return $this->templatePath($customPath, $availableVars);
                }
            }
        }

        $locations = self::LOCATIONS;
        if (!isset($locations[$subPackageType])) {
            throw new \InvalidArgumentException(sprintf('Sub-package type "%s" is not supported', $type));
        }

        return $this->templatePath($locations[$subPackageType], $availableVars);
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        if (!$repo->hasPackage($package)) {
            throw new \InvalidArgumentException('Package is not installed: ' . $package);
        }

        $repo->removePackage($package);

        $installPath = $this->getInstallPath($package);

        $message = '<error>not deleted</error>';
        if ($this->filesystem->removeDirectory($installPath)) {
            $message = '<comment>deleted</comment>';
        }

        $this->io->write(sprintf('Deleting %s - %s', $installPath, $message));
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    public function supports($packageType)
    {
        if (substr($packageType, 0, strlen(self::NAME)) !== self::NAME) {
            return false;
        }

        $locationPattern = $this->getLocationPattern();

        return preg_match('#' . self::NAME . '-' . $locationPattern . '#', $packageType, $matches) === 1;
    }

    /**
     * Replace placeholder variables in a path.
     *
     * @since 1.0.0
     *
     * @param string $path Path to replace variables in.
     * @param array  $vars Associative array of variable names and their values.
     * @return string Path with placeholders replaced with values.
     */
    protected function templatePath(string $path, array $vars = array()): string
    {
        if (strpos($path, '{') !== false) {
            extract($vars);

            preg_match_all('@\{\$([A-Za-z0-9_]*)\}@i', $path, $matches);
            if (!empty($matches[1])) {
                foreach ($matches[1] as $var) {
                    $path = str_replace('{$' . $var . '}', $$var, $path);
                }
            }
        }

        return $path;
    }

    /**
     * Get the second part of the regular expression to check for support of a package type.
     *
     * @since 1.0.0
     *
     * @return string Location pattern.
     */
    protected function getLocationPattern(): string
    {
        return '(' . implode('|', array_keys(self::LOCATIONS)) . ')';
    }

    /**
     * Search through a paths array for a custom install path.
     *
     * @since 1.0.0
     *
     * @param array  $paths  Associative array of paths and their identifying names.
     * @param string $name   Vendor and package name of the current package.
     * @param string $type   Package type of the current package.
     * @param string $vendor Optional. Vendor name of the current package. Default empty string.
     * @return string Custom installation path, or empty string if no match found.
     */
    protected function mapCustomInstallPaths(array $paths, string $name, string $type, string $vendor = ''): string
    {
        foreach ($paths as $path => $names) {
            if (in_array($name, $names) || in_array('type:' . $type, $names)) {
                return $path;
            }

            if (!empty($vendor) && in_array('vendor:' . $vendor, $names)) {
                return $path;
            }
        }

        return '';
    }
}
