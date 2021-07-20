<?php declare(strict_types = 1);
namespace Medusa\Coco\Database;

use Closure;
use function call_user_func;
use function filemtime;
use function is_file;
use function time;

/**
 * Class CacheFile
 * @package medusa/coco
 * @author  Pascal Schnell <pascal.schnell@getmedusa.org>
 */
class CacheFile {

    private array $data;

    public function __construct(private string $name, int $ttl, private Closure $loadFn) {
        $db = Database::getInstance();
        $path = $db->getFilePath($name);
        $time = time();
        $expired = true;
        if (is_file($path)) {
            $expireTime = filemtime($path) + $ttl;
            $expired = $expireTime < $time;
        }

        if ($expired) {
            $data = call_user_func($loadFn) ?? [];
            $db->saveDb($name, $data);
        } else {
            $data = $db->loadFromDb($name);
        }

        $this->data = $data;
    }

    public function get(): array {
        return $this->data;
    }
}
