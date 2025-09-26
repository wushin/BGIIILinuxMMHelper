<?php
namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use App\Libraries\EnvWriter;
use Config\BG3LinuxHelper as Bg3Cfg;
use Config\Mongo as MongoCfg;

class Settings extends BaseController
{
    public function index(): ResponseInterface
    {
        $bg3   = config(Bg3Cfg::class) ?? new Bg3Cfg();
        $mongo = config(MongoCfg::class) ?? new MongoCfg();

        return $this->response->setBody(view('settings/index', [
            'pageTitle' => 'Settings',
            'activeTab' => 'settings',    // useful if your nav highlights the active tab
            'bg3'       => $bg3,
            'mongo'     => $mongo,
        ]));
    }

    public function save(): ResponseInterface
    {
        $pairs = [
            'bg3LinuxHelper.MyMods'   => (string)$this->request->getPost('bg3LinuxHelper_MyMods'),
            'bg3LinuxHelper.AllMods'  => (string)$this->request->getPost('bg3LinuxHelper_AllMods'),
            'bg3LinuxHelper.GameData' => (string)$this->request->getPost('bg3LinuxHelper_GameData'),
            'mongo.default.uri'       => (string)$this->request->getPost('mongo_default_uri'),
            'mongo.default.db'        => (string)$this->request->getPost('mongo_default_db'),
        ];

        (new EnvWriter())->setMany($pairs);

        return $this->response->setJSON(['ok' => true, 'saved' => $pairs]);
    }
}
?>
