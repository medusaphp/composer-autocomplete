<?php declare(strict_types = 1);
namespace Medusa\Coco;

use Medusa\Coco\Database\Database;
use Medusa\EasyCompletion\ArrayObject;
use Medusa\EasyCompletion\Cli;
use function array_values;
use function count;
use function explode;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function filemtime;
use function intval;
use function json_decode;
use function mb_str_split;
use function mb_substr;
use function strlen;
use function time;
use const FILE_APPEND;
use const PHP_EOL;

/**
 * Class PackageManager
 * @package medusa/coco
 * @author  Pascal Schnell <pascal.schnell@getmedusa.org>
 */
class PackageManager {

    public function __construct(private Database $database) {
    }

    public function getVendorsStartsWith(string $chars): ArrayObject {
        $start = mb_substr($chars, 0, 1);
        $d = $this->database->loadFromDb('vendor', $start);
        return (new ArrayObject($d))->filter($chars);
    }

    public function getPackagesForVendor(string $vendor): ArrayObject {
        return new ArrayObject($this->database->loadFromDb('packages', $vendor));
    }

    public function updatePackages(array $packagesJson = null) {

        $packagesJson ??= $this->getAllFromPackagesJson(true);
        $packages = $packagesJson['packageNames'];

        Cli::stdOut('Update db: ' . PHP_EOL);
        Cli::stdOut('Packages loaded: ' . count($packages) . PHP_EOL);

        $vendors = [];
        $packagesByVendor = [];
        $packagesByChars = [];
        $files = [];

        foreach ($packages as $vendorPackage) {
            [$vendor, $package] = explode('/', $vendorPackage);
            $vendors[$vendor] = true;
            $packagesByVendor[$vendor][$package] = $package;
            $files[mb_substr($vendor, 0, 1)][$vendor] = $vendor;
            $vendorChars = mb_str_split($vendor, 1);
            $tmp = &$packagesByChars;

            foreach ($vendorChars as $vendorChar) {
                if (false === ($tmp[$vendorChar] ?? false)) {
                    $tmp[$vendorChar] = [];
                }

                $tmp = &$tmp[$vendorChar];
            }

            $tmp['_p'][] = $package;
            unset($tmp);
        }

        Cli::stdOut('Vendors loaded: ' . count($vendors) . PHP_EOL);

        $pathVendorDb = $this->database->getFilePath('vendor');
        $pathPackagesDb = $this->database->getFilePath('packages');

        file_put_contents($pathVendorDb, '');
        file_put_contents($pathPackagesDb, '');

        $pointerVendorDb = 0;
        $pointerPackagesDb = 0;

        $vendorIndex = [];
        $packageIndex = [];

        foreach ($files as $char => $vendors) {

            $vendors = array_values($vendors);
            $vendorsSerialized = $this->database->serialize($vendors);
            $end = $pointerVendorDb + strlen($vendorsSerialized);
            file_put_contents($pathVendorDb, $vendorsSerialized, FILE_APPEND);
            $vendorIndex[$char] = [$pointerVendorDb, $end];
            $pointerVendorDb = $end;

            foreach ($vendors as $vendor) {
                $packages = array_values($packagesByVendor[$vendor]);
                $packagesSerialized = $this->database->serialize($packages);
                $end = $pointerPackagesDb + strlen($packagesSerialized);
                file_put_contents($pathPackagesDb, $packagesSerialized, FILE_APPEND);
                $packageIndex[$vendor] = [$pointerPackagesDb, $end];
                $pointerPackagesDb = $end;
            }
        }

        Cli::stdOut('Vendor db file created' . PHP_EOL);
        Cli::stdOut('Package db file created' . PHP_EOL);

        file_put_contents($this->database->getFilePath('vendor_index'), $this->database->serialize($vendorIndex));
        file_put_contents($this->database->getFilePath('packages_index'), $this->database->serialize($packageIndex));

        Cli::stdOut('Vendor index file created' . PHP_EOL);
        Cli::stdOut('Package index file created' . PHP_EOL);
    }

    public function getAllFromPackagesJson(bool $forceDownload = false): array {
        $rawPackages = $this->database->getStorageDirectory() . '/packages.json';
        $rawList = $this->database->getStorageDirectory() . '/packages_list.json';

        if (file_exists($rawList) && !$forceDownload) {
            $exp = filemtime($rawList) + (60 * 60 * 24) - time();
            if ($exp > 0) {
                $exp = intval($exp / 60);
                Cli::stdOut('packages.json: Use cached packages.json (expires in ' . $exp . ' minutes' . PHP_EOL);
                return json_decode(file_get_contents($rawList), true);
            }
        }

        Cli::stdOut('Download packages from packagist.org' . PHP_EOL);
        $packages = file_get_contents('https://packagist.org/packages.json');
        $packagesDecoded = json_decode($packages, true);
        $list = file_get_contents($packagesDecoded['list']);
        file_put_contents($rawList, $list);
        file_put_contents($rawPackages, $packages);

        $this->database->updateDb('meta', [
            'metadata-url' => $packagesDecoded['metadata-url']
        ]);

        return json_decode($list, true);
    }
}
