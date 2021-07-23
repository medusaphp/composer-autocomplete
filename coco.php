<?php declare(strict_types = 1);

use Medusa\Coco\BuiltInCommands\Installer;
use Medusa\Coco\ComposerJson;
use Medusa\Coco\Database\Database;
use Medusa\EasyCompletion\EasyCompletion;

require_once __DIR__ . '/vendor/autoload.php';
### START EXEC EXPORT ###
function currentCommand(string|array $path, ?array $args = null) {

    $result = new stdClass();
    $result->cmd = null;
    $result->opt = [];
    $result->arg = [];

    if (is_array($path)) {
        $pathParts = $path;
    } else {
        $pathParts = explode('/', $path);
    }

    $args ??= $_SERVER['argv'];

    $pathParts = array_values($pathParts);
    $collectArguments = false;
    $collectOptions = false;
    for ($j = 0, $maxJ = count($pathParts); $j < $maxJ; $j++) {
        $pathPart = $pathParts[$j] ?? null;
        if (!$args || !$pathPart) {
            return false;
        }

        $args = array_values($args);

        if ($collectOptions && !str_starts_with($pathPart, '-')) {
            $collectOptions = false;
        }

        if ($collectArguments) {

            if ($pathPart === 'inf') {
                for ($i = 0, $max = count($args); $i < $max; $i++) {
                    $arg = $args[$i];
                    if (str_starts_with($arg, '-')) {
                        continue;
                    }
                    unset($args[$i]);
                    $result->arg[] = $arg;
                }
                continue;
            }

            for ($i = 0, $max = (int)$pathPart; $i < $max; $i++) {
                $arg = $args[$i] ?? null;

                if ($arg === null) {
                    return false;
                }

                if (str_starts_with($arg, '-')) {
                    $max += 1;
                    continue;
                }
                unset($args[$i]);
                $result->arg[] = $arg;
            }

            $collectArguments = false;
            continue;
        } elseif ($collectOptions) {
            $optionMatch = false;
            for ($i = 0, $max = count($args); $i < $max; $i++) {
                $arg = $args[$i];
                if (!str_starts_with($arg, '-')) {
                    break;
                }

                if ($pathPart === $arg) {
                    $optionMatch = true;
                    unset($args[$i]);
                    $result->opt[] = $arg;
                    break;
                }
            }

            if (!$optionMatch) {
                return false;
            }

            continue;
        }

        if ($pathPart === 'cmd') {
            for ($i = 0, $max = count($args); $i < $max; $i++) {
                $arg = $args[$i];
                if (str_starts_with($arg, '-')) {
                    unset($args[$i]);
                    continue;
                }

                $result->cmd = $arg;
                break;
            }
        } elseif ($pathPart === 'arg') {
            $collectArguments = true;
        } elseif ($pathPart === 'opt') {
            $collectOptions = true;
        } else {

            if (!isset($pathParts[$j]) || !isset($args[0]) || $args[0] !== $pathParts[$j]) {
                return false;
            }

            unset($args[0]);
            continue;
        }
    }

    return $result;
}

### STOP EXEC EXPORT ###

(new EasyCompletion(
    [
        'name' => 'composer',
        'exec' => function() {

            unset($_SERVER['argv'][0]);

            if ($result = currentCommand('cmd/require/arg/inf')) {

                foreach ($result->arg as $vendorPackageVersion) {

                    // CHECK PACKAGE
                    if ($vendorPackageVersion === 'foo/bar') {
                        $addRepo = strtolower(
                                mecStdIn(
                                    sprintf(
                                        'Add repo for external package "%s" to your composer.json? (y/n) ',
                                        $vendorPackageVersion
                                    ),
                                    ['y', 'n', 'j']
                                )
                            ) !== 'n';
                        var_dump($addRepo);
                        die;
                    }
                }

//                $arg = implode(' ', array_map('escapeshellarg', $_SERVER['argv']));
//                echo "SIMULATE PACKAGE INSTALLATION\n";
//                die('RUN /usr/bin/composer ' . $arg . PHP_EOL);
            }

            $arg = implode(' ', array_map('escapeshellarg', $_SERVER['argv']));
            exit(mecExec('composer ' . $arg));
        },
    ], []
))->commandsForPharFile(
    [
        'extractCommandList'  => [Installer::class, 'extractCommandList'],
        'install'  => [Installer::class, 'install'],
        'updatedb' => [Installer::class, 'updateDb'],
        'crontab'  => [Installer::class, 'crontabInstall'],
    ]
)->run(function(EasyCompletion $completion) {
    $json = ComposerJson::get();
    $map = Database::getInstance()->loadFromDb('cmdlist');
    if ($json) {
        foreach ($json->getScripts() as $script) {
            $map['cmd'][$script] = [];
        }
    }
    $completion->setMap($map);
});
