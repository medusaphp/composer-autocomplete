<?php declare(strict_types = 1);
namespace Medusa\Coco;

use function array_diff_key;
use function array_filter;
use function array_flip;
use function array_keys;
use function array_merge;
use function file_get_contents;
use function getcwd;
use function is_file;
use function json_decode;
use function str_contains;

/**
 * Class ComposerJson
 * @package medusa/coco
 * @author  Pascal Schnell <pascal.schnell@getmedusa.org>
 */
class ComposerJson {

    private const COMPOSER_JSON_COMMANDS = [
        'pre-install-cmd',
        'post-install-cmd',
        'pre-update-cmd',
        'post-update-cmd',
        'pre-status-cmd',
        'post-status-cmd',
        'pre-archive-cmd',
        'post-archive-cmd',
        'pre-autoload-dump',
        'post-autoload-dump',
        'post-root-package-install',
        'post-create-project-cmd',
        'pre-operations-exec',
        'pre-package-install',
        'post-package-install',
        'pre-package-update',
        'post-package-update',
        'pre-package-uninstall',
        'post-package-uninstall',
        'init',
        'command',
        'pre-file-download',
        'post-file-download',
        'pre-command-run',
        'pre-pool-create',
    ];

    private array $scripts;
    private array $packages;

    public function __construct(array $composerJson) {
        $this->scripts = array_keys(array_diff_key($composerJson['scripts'] ?? [], array_flip(static::COMPOSER_JSON_COMMANDS)));
        $this->packages = array_filter(array_merge(array_keys($composerJson['require'] ?? []), array_keys($composerJson['require-dev'] ?? [])), function($package) {
            return str_contains($package, '/');
        });
    }

    private static array $instanceStack = [];

    public static function exists(): bool {
        $cwd = getcwd();
        $tmp = self::$instanceStack[$cwd] ?? null;

        if ($tmp == null) {
            $tmp = self::create() ?? false;
        }

        self::$instanceStack[$cwd] = $tmp;
        return self::$instanceStack[$cwd] !== false;
    }

    public static function get(): ?static {
        return self::$instanceStack[getcwd()] ??= self::create();
    }

    public static function create(): ?static {

        if (!is_file(getcwd() . '/composer.json')) {
            return null;
        }
        $composerJson = json_decode(file_get_contents(getcwd() . '/composer.json'), true);
        return new static($composerJson);
    }

    /**
     * @return array
     */
    public function getPackages(): array {
        return $this->packages;
    }

    /**
     * @return array
     */
    public function getScripts(): array {
        return $this->scripts;
    }
}