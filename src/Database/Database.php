<?php declare(strict_types = 1);
namespace Medusa\Coco\Database;

use Medusa\EasyCompletion\Cli;
use function array_merge;
use function file_get_contents;
use function file_put_contents;
use function fopen;
use function fread;
use function fseek;
use function function_exists;
use function igbinary_serialize;
use function igbinary_unserialize;
use function is_file;
use function json_decode;
use function json_encode;
use function serialize;
use function substr;
use function unserialize;

/**
 * Class Database
 * @package medusa/coco
 * @author  Pascal Schnell <pascal.schnell@getmedusa.org>
 */
class Database {

    private string $serializer = '';

    private static Database $instance;

    public function serialize(string|array $data): string {

        switch ($this->serializer) {
            case 'igbinary_serialize':
                return '1' . igbinary_serialize($data);
            case 'json_encode':
                return '2' . json_encode($data);
            case 'serialize':
                return '4' . serialize($data);
        }

        return '8' . $data;
    }

    public function unserialize(string $data): string|array {

        $ident = substr($data, 0, 1);
        $data = substr($data, 1);

        switch ($ident) {
            case '1':
                return igbinary_unserialize($data);
            case '2':
                return json_decode($data, true);
            case '4':
                return unserialize($data);
        }

        return $data;
    }

    public function __construct(private string $storageDirectory) {

        if (function_exists('igbinary_serialize')) {
            $this->serializer = 'igbinary_serialize';
        } elseif (function_exists('serialize')) {
            $this->serializer = 'serialize';
        } else {
            $this->serializer = 'json_encode';
        }
    }

    /**
     * @return string
     */
    public function getStorageDirectory(): string {
        return $this->storageDirectory;
    }

    /**
     * Set Instance
     * @param Database $instance
     */
    public static function setInstance(Database $instance): void {
        self::$instance = $instance;
    }

    /**
     * @return Database
     */
    public static function getInstance(): Database {
        return self::$instance ??= new static(Directory::auto());
    }

    /**
     * @param string $name
     * @return bool
     */
    public function dbExists(string $name): bool {
        return is_file($this->getFilePath($name));
    }

    /**
     * @param string      $db
     * @param string|null $char
     * @return array
     */
    public function loadFromDb(string $db, ?string $char = null): string|array {
        $dbFile = $this->getFilePath($db);
        $dbIndexFile = $this->getFilePath($db . '_index');
        if ($char === null || !is_file($dbIndexFile)) {
            return $this->unserialize(file_get_contents($dbFile));
        }

        $charPos = $this->unserialize(file_get_contents($dbIndexFile))[$char] ?? null;

        if (!$charPos) {
            return [];
        }

        $handle = fopen($dbFile, 'r');

        if ($handle) {
            fseek($handle, $charPos[0]);
            $length = $charPos[1] - $charPos[0];
            $data = fread($handle, $length);

            $data = $this->unserialize($data);
            return $data;
        }

        Cli::stdErr('Error in reading db file');
        Cli::errorExit();
    }

    public function getFilePath(string $key): string {
        return $this->storageDirectory . '/db_' . $key;
    }

    /**
     * @param string $db
     * @param mixed  $data
     */
    public function saveDb(string $db, $data): void {
        $dbFile = $this->getFilePath($db);
        file_put_contents($dbFile, $this->serialize($data));
    }

    /**
     * @param string $db
     * @param mixed  $data
     */
    public function updateDb(string $db, $data): void {
        $dbFile = $this->getFilePath($db);

        if (is_file($dbFile)) {
            $data = array_merge($this->unserialize(file_get_contents($dbFile)), $data);
        }

        file_put_contents($dbFile, $this->serialize($data));
    }
}
