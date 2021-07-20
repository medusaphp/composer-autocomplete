<?php declare(strict_types = 1);
namespace Medusa\Coco;

use Medusa\EasyCompletion\ArrayObject;
use function array_keys;
use function explode;
use function str_starts_with;

/**
 * Class PackageManager
 * @package medusa/coco
 * @author  Pascal Schnell <pascal.schnell@getmedusa.org>
 */
class ComposerJsonPackageManager {

    public function __construct(private ComposerJson $composerJson) {
    }

    public function getVendorsStartsWith(string $chars): ArrayObject {

        $vendors = [];
        foreach ($this->composerJson->getPackages() as $tmp) {
            $tmp = explode('/', $tmp)[0];
            if (str_starts_with($tmp, $chars)) {
                $vendors[$tmp] = true;
            }
        }
        return new ArrayObject(array_keys($vendors));
    }

    public function getPackagesForVendor(string $vendorCompare): ArrayObject {

        $packages = [];
        foreach ($this->composerJson->getPackages() as $tmp) {
            [$vendor, $package] = explode('/', $tmp);

            if ($vendor === $vendorCompare) {
                $packages[$package] = true;
            }
        }

        return new ArrayObject(array_keys($packages));
    }
}
