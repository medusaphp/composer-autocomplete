<?php declare(strict_types = 1);
namespace Medusa\Coco;

use Medusa\Coco\Database\CacheFile;
use Medusa\Coco\Database\Database;
use Medusa\EasyCompletion\Argument;
use Medusa\EasyCompletion\ArgumentValueCompletion;
use Medusa\EasyCompletion\ArrayObject;
use function array_column;
use function array_pop;
use function explode;
use function file_get_contents;
use function in_array;
use function json_decode;
use function str_replace;
use const INF;

/**
 * Class PackageCompletion
 * @package medusa/coco
 * @author  Pascal Schnell <pascal.schnell@getmedusa.org>
 */
class ComposerPharArgumentCompletion {

    public const AVAILABLE_COMMAND_ARGUMENTS = [
        'packages' => ['packages', INF],
        //        'version',
        'package'  => 'packages',
        'version'  => 'version',
        //        'setting-key',
        //        'setting-value',
        //        'directory',
        //        'args',
        //        'constraint',
        //        'binary',
        //        'command-name',
        //        'command',
        //        'command_name',
        //        'namespace',
        //        'script',
        //        'tokens',
        //        'file',
    ];

    public const AVAILABLE_OPTIONS_ARGUMENTS = [
        '--working-dir' => 'workingDir',
        //        '--format',
        //        '--dir',
        //        '--file',
        //        '--stability',
        //        '--repository',
        //        '--repository-url',
        //        '--ignore',
        //        '--name',
        //        '--description',
        //        '--author',
        //        '--type[',
        //        '--homepage',
        //        '--require',
        //        '--require-dev',
        //        '--license',
        //        '--timeout',
        //        '--type',
    ];

    public static function workingDir(Argument $argument) {
        exit(ArgumentValueCompletion::PATH_COMPLETION);
    }

    public static function version(Argument $argument) {
        return self::packages($argument);
    }

    public static function packages(Argument $argument) {

        $argumentValues = $argument->getValues();
        $argumentValue = array_pop($argumentValues);
        $argumentValuesTmp = [];

        foreach ($argumentValues as $row) {
            $row = explode('/', explode(':', $row, 2)[0], 2);

            if ($row[1] ?? null) {
                $argumentValuesTmp[$row[0]][] = $row[1];
            }
        }
        $argumentValues = $argumentValuesTmp;
        unset($argumentValuesTmp);

        $packageManager = null;
        if (
            in_array($argument->getName(), ['update', 'show', 'remove'])
            && ComposerJson::exists()) {
            $packageManager = new ComposerJsonPackageManager(ComposerJson::get());
        }

        if (!$packageManager && !$argumentValue) {
            return [];
        }

        $packageManager ??= new PackageManager(Database::getInstance());

        $vendorPackage = explode('/', $argumentValue);
        $vendor = $vendorPackage[0];
        $packageVersion = $vendorPackage[1] ?? null;

        if ($packageVersion === null) {
            $completionData = $packageManager->getVendorsStartsWith($argumentValue);

            if ($completionData->count() === 1) {
                $completionData = $completionData->withSuffix('/');
            }
        } else {
            $packageVersion = explode(':', $packageVersion);
            $package = $packageVersion[0];
            $version = $packageVersion[1] ?? null;
            $filterPackages = $argumentValues[$vendor] ?? [];

            $completionData = $packageManager->getPackagesForVendor($vendor)
                ->filter($package)
                ->filterMatch($filterPackages, false);

            $versions = [];

            if ($version !== null && $completionData->filterMatch($package)->count() === 1) {
                $dbName = $vendor . '_' . $package;
                $versions = (new CacheFile($dbName, 600, function() use ($vendor, $package) {
                    if (!Database::getInstance()->dbExists('meta')) {
                        return [];
                    }
                    $metaUrl = Database::getInstance()->loadFromDb('meta')['metadata-url'] ?? null;
                    if (!$metaUrl) {
                        return [];
                    }
                    $metaUrl = 'https://packagist.org' . str_replace('%package%', $vendor . '/' . $package, $metaUrl);
                    $metaData = json_decode(file_get_contents($metaUrl), true)['packages'][$vendor . '/' . $package] ?? [];
                    return array_column($metaData, 'version');
                }))->get();
            }

            if ($version !== null) {
                $completionData = new ArrayObject($versions);
            } else {
                $completionData = $completionData->withPrefix($vendor . '/');
            }

            $completionData = $completionData->withSuffix(' ');
        }

        return $completionData;
    }
}
