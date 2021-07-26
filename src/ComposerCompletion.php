<?php declare(strict_types = 1);
namespace Medusa\Coco;

use Medusa\Coco\BuiltInCommands\Installer;
use Medusa\Coco\Database\Database;
use Medusa\EasyCompletion\EasyCompletion;

/**
 * Class ComposerCompletion
 * @package medusa/coco
 * @author  Pascal Schnell <pascal.schnell@getmedusa.org>
 */
class ComposerCompletion {

    public const VERSION = '0.0.9';

    public static function run(): void {

        (new EasyCompletion(
            [
                'name' => 'composer',
            ], []
        ))->commandsForPharFile(
            [
                'extractCommandList' => [Installer::class, 'extractCommandList'],
                'install'            => [Installer::class, 'install'],
                'updatedb'           => [Installer::class, 'updateDb'],
                'crontab'            => [Installer::class, 'crontabInstall'],
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
    }
}
