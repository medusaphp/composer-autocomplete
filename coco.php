<?php declare(strict_types = 1);

use Medusa\Coco\BuiltInCommands\Installer;
use Medusa\Coco\ComposerJson;
use Medusa\Coco\Database\Database;
use Medusa\EasyCompletion\EasyCompletion;

require_once __DIR__ . '/vendor/autoload.php';

(new EasyCompletion(
    [
        'name' => 'composer',
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
