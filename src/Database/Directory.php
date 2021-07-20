<?php declare(strict_types = 1);
namespace Medusa\Coco\Database;

use Medusa\EasyCompletion\Build\User;
use function dirname;
use function is_dir;
use function realpath;

/**
 * Class Directory
 * @package medusa/coco
 * @author  Pascal Schnell <pascal.schnell@getmedusa.org>
 */
class Directory {

    public static function auto(): string {

        $executable = dirname(realpath($_SERVER['argv'][0]));

        if ($executable === '/etc/easy_completion') {
            return self::global();
        }

        $local = self::local();

        if (is_dir($local)) {
            return $local;
        }

        return self::global();
    }

    public static function global(): string {
        return '/etc/coco';
    }

    public static function local(): string {

        $home = User::getInstance()->getHome();
        return $home . '/.local/share/coco';
    }
}