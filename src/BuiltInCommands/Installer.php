<?php declare(strict_types = 1);
namespace Medusa\Coco\BuiltInCommands;

use Medusa\Coco\Build\ComposerPharCommandExtractor;
use Medusa\Coco\Database\Database;
use Medusa\Coco\Database\Directory as DbDirectory;
use Medusa\Coco\PackageManager;
use Medusa\EasyCompletion\Build\User;
use Medusa\EasyCompletion\Cli;
use Medusa\EasyCompletion\EasyCompletion;
use Medusa\EasyCompletion\Installer\Directory;
use function count;
use function exec;
use function explode;
use function fwrite;
use function implode;
use function preg_replace;
use function rtrim;
use function str_starts_with;
use function stream_get_meta_data;
use function tmpfile;
use function trim;
use function unlink;
use const PHP_EOL;

/**
 * Class Installer
 * @package medusa/coco
 * @author  Pascal Schnell <pascal.schnell@getmedusa.org>
 */
class Installer {

    /**
     * @param EasyCompletion $completion
     */
    public static function crontabInstall(EasyCompletion $completion): void {
        $user = User::getInstance();
        if (User::getInstance()->isRoot()) {
            $dir = DbDirectory::global();
        } else {
            $dir = DbDirectory::local();
        }
        Directory::ensureExists($dir);
        Directory::ensureReadable($dir);
        Directory::ensureWriteable($dir);
        Database::setInstance(new Database($dir));

        Cli::stdOut('Read current crontab for ' . $user->getName() . PHP_EOL);
        exec('crontab -l -u ' . $user->getName(), $contentRows);
        $phar = \Medusa\EasyCompletion\Installer\Installer::fromGlobals($completion->getName(), null)->getPathToPharFile();
        $cmdLeftPart = '3 0 * * *';
        $cmdRightPart = $phar . ' updatedb > /dev/null';
        $entryExists = false;
        foreach ($contentRows as $row) {

            $row = trim($row);

            if (empty($row) || str_starts_with($row, '#')) {
                continue;
            }

            $row = preg_replace('/\s+/', ' ', $row);
            $row = explode(' ', $row, 6);

            if (count($row) !== 6) {
                continue;
            }

            if ($row[5] === $cmdRightPart) {
                $entryExists = true;
                break;
            }
        }

        if (!$entryExists) {
            $contentRows[] = $cmdLeftPart . ' ' . $cmdRightPart;
            $temp = tmpfile();
            fwrite($temp, rtrim(implode(PHP_EOL, $contentRows)) . PHP_EOL);
            $path = stream_get_meta_data($temp)['uri'];
            exec('crontab ' . $path);
            Cli::stdOut('New crontab installed' . PHP_EOL);
            unlink($path);
        } else {
            Cli::stdOut('Crontab entry already exists. Skip' . PHP_EOL);
        }
    }

    /**
     * @param EasyCompletion $completion
     */
    public static function install(EasyCompletion $completion): void {

        if (User::getInstance()->isRoot()) {
            $dir = DbDirectory::global();
        } else {
            $dir = DbDirectory::local();
        }
        Directory::ensureExists($dir);
        Directory::ensureReadable($dir);
        Directory::ensureWriteable($dir);
        Database::setInstance(new Database($dir));
        $self = new self();
        $self->handlePackageDb();
        $self->handleCmdList();
        self::crontabInstall($completion);
    }

    /**
     * @param EasyCompletion $completion
     */
    public static function updatedb(EasyCompletion $completion): void {

        if (User::getInstance()->isRoot()) {
            $dir = DbDirectory::global();
        } else {
            $dir = DbDirectory::local();
        }
        Directory::ensureExists($dir);
        Directory::ensureReadable($dir);
        Directory::ensureWriteable($dir);
        Database::setInstance(new Database($dir));
        $self = new self();
        $self->handlePackageDb(true);
    }

    private function handlePackageDb(bool $force = false): void {
        Directory::ensureExists(Database::getInstance()->getStorageDirectory());
        $pm = new PackageManager(Database::getInstance());
        $json = $pm->getAllFromPackagesJson($force);
        $pm->updatePackages($json);
    }

    public static function extractCommandList() {

        if (User::getInstance()->isRoot()) {
            $dir = DbDirectory::global();
        } else {
            $dir = DbDirectory::local();
        }
        Directory::ensureExists($dir);
        Directory::ensureReadable($dir);
        Directory::ensureWriteable($dir);
        Database::setInstance(new Database($dir));

        $extractor = new ComposerPharCommandExtractor(Database::getInstance());
        $data = $extractor->extract(true);
        if ($data !== null) {
            Database::getInstance()->saveDb('cmdlist', $data['data']);
            Database::getInstance()->updateDb('meta', ['version' => $data['version']]);
        }

    }

    private function handleCmdList(): void {
        $extractor = new ComposerPharCommandExtractor(Database::getInstance());
        $data = $extractor->extract();
        if ($data !== null) {
            Database::getInstance()->saveDb('cmdlist', $data['data']);
            Database::getInstance()->updateDb('meta', ['version' => $data['version']]);
        }
    }
}
