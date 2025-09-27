<?php
namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use App\Libraries\EnvWriter;
use Config\BG3Paths;

class Settings extends BaseController
{
    public function index()
    {
        /** @var BG3Paths $cfg */
        $cfg = config(BG3Paths::class);

        $values = [
            'GameData'     => env($cfg->envGameData,     $cfg->defaultGameData),
            'MyMods'       => env($cfg->envMyMods,       $cfg->defaultMyMods),
            'UnpackedMods' => env($cfg->envUnpackedMods, $cfg->defaultUnpackedMods),
        ];

        return view('settings/index', [
            'title'  => 'Settings — BG3 Linux Helper',
            'cfg'    => $cfg,
            'values' => $values,
        ]);
    }

    public function save(): ResponseInterface
    {
        /** @var BG3Paths $cfg */
        $cfg = config(BG3Paths::class);

        $pairs = [
            $cfg->envGameData     => (string) $this->request->getPost('GameData'),
            $cfg->envMyMods       => (string) $this->request->getPost('MyMods'),
            $cfg->envUnpackedMods => (string) $this->request->getPost('UnpackedMods'),
        ];

        (new EnvWriter())->setMany($pairs);

        // JSON response to keep it simple for the page JS
        return $this->response->setJSON(['ok' => true, 'saved' => $pairs]);
    }
}
?>
