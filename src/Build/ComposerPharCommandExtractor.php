<?php declare(strict_types = 1);
namespace Medusa\Coco\Build;

use Medusa\Coco\ComposerPharArgumentCompletion;
use Medusa\Coco\Database\Database;
use Medusa\EasyCompletion\Cli;
use function array_map;
use function array_merge;
use function array_shift;
use function array_values;
use function exec;
use function explode;
use function is_array;
use function ltrim;
use function preg_match;
use function str_contains;
use function str_starts_with;
use function trim;
use function version_compare;
use const INF;
use const PHP_EOL;

/**
 * Class ComposerPharCommandExtractor
 * @package medusa/coco
 * @author  Pascal Schnell <pascal.schnell@getmedusa.org>
 */
class ComposerPharCommandExtractor {

    /**
     * ComposerPharCommandExtractor constructor.
     * @param Database $database
     */
    public function __construct(private Database $database) {
    }

    /**
     * @param bool $foreRenew
     * @return array|null
     */
    public function extract(bool $foreRenew = false): ?array {
        $currentComposerVersion = exec('composer -V -n 2>/dev/null');
        preg_match('/\d+.\d+.\d+/', $currentComposerVersion, $matches);
        $currentComposerVersion = $matches[0];
        $metaDbExists = $this->database->dbExists('meta');

        if (!$metaDbExists) {
            $foreRenew = true;
        } elseif (!$foreRenew) {
            $version = $this->database->loadFromDb('meta')['version'] ?? '0';
            $foreRenew = version_compare($currentComposerVersion, $version) !== 0;
        }

        if (!$foreRenew) {
            Cli::stdOut('Command list for composer version "' . $version . '" already exists. Skip extraction.' . PHP_EOL);
            return null;
        }

        $version = exec('composer -V -n 2>/dev/null');
        preg_match('/\d+.\d+.\d+/', $version, $matches);

        Cli::stdOut('Start command extraction from composer.phar' . PHP_EOL);
        exec('composer -n list 2>/dev/null', $out);

        $collection = $this->extractRecursive($out);

        return [
            'version' => $currentComposerVersion,
            'data'    => $collection,
        ];
    }

    /**
     * @param $out
     * @return array
     */
    private function extractRecursive($out): array {

        $collection = [];
        $collect = null;
        foreach ($out as $item) {

            $item = trim($item);

            if ($item === 'Options:') {
                $collect = 'options';
                continue;
            } elseif ($item === 'Arguments:') {
                $collect = 'arguments';
                continue;
            } elseif ($item === 'Available commands:') {
                $collect = 'commands';
                continue;
            } elseif ($item === '') {
                $collect = null;
                continue;
            }

            if (!$collect) {
                continue;
            }

            if ($collect === 'commands') {
                $name = explode(' ', $item, 2)[0];
                $outTmp = [];
                Cli::stdOut('Extract arguments and options for "' . $name . '"' . PHP_EOL);
                exec('composer -n ' . $name . ' -h 2>/dev/null', $outTmp);
                $collection['cmd'][$name] = $this->extractRecursive($outTmp);
                continue;
            }

            if ($collect === 'arguments') {
                $argName = explode(' ', $item, 2)[0];
                $callback = ComposerPharArgumentCompletion::AVAILABLE_COMMAND_ARGUMENTS[$argName] ?? null;
                if ($callback) {

                    $cnt = 1;
                    if (is_array($callback)) {
                        $cnt = $callback[1];
                        $callback = $callback[0];
                        if ($cnt === INF) {
                            $collection['arg'] = [ComposerPharArgumentCompletion::class, $callback];
                            continue;
                        }
                    }

                    for ($i = 0; $i < $cnt; $i++) {
                        $collection['arg'][] = [ComposerPharArgumentCompletion::class, $callback];
                    }
                } else {
                    $collection['arg'][] = true;
                }
                continue;
            }

            if ($collect === 'options') {
                $result = $this->extractOption($item);

                if ($result) {
                    $collection['opt'][$result[0]] = $result[1];
                }
                continue;
            }
        }

        return $collection;
    }

    /**
     * @param string $item
     * @return array|null
     */
    private function extractOption(string $item): ?array {
        $options = [];
        $items = explode(',', $item);
        $optionNeedsValue = false;

        foreach ($items as $row) {
            $row = trim($row);
            if (!preg_match('/^(-{1,2})[a-z0-9]/i', $row, $match)) {
                continue;
            }
            $option = explode('=', explode(' ', $row)[0])[0];

            if (!$optionNeedsValue) {
                $optionNeedsValue = str_contains($row, '=');
            }
            if (str_contains($option, '|')) {
                $tmp = array_map(fn($val) => $match[1] . $val, explode('|', ltrim($option, '-')));
            } else {
                $tmp = [$option];
            }
            $options = array_merge($options, $tmp);
        }

        if (!$options) {
            return null;
        }

        $aliases = [];
        $name = null;
        foreach ($options as $option) {

            if (!$name && str_starts_with($option, '--')) {
                $name = $option;
                continue;
            }
            $aliases[] = $option;
        }

        $name ??= array_shift($aliases);
        $result = [];

        if ($aliases) {
            $result['alias'] = array_values($aliases);
        }
        if ($optionNeedsValue) {
            $callback = ComposerPharArgumentCompletion::AVAILABLE_OPTIONS_ARGUMENTS[$name] ?? null;
            if ($callback) {
                $result['arg'][] = [ComposerPharArgumentCompletion::class, $callback];
            } else {
                $result['arg'][] = true;
            }
        }

        return [$name, $result];
    }
}
