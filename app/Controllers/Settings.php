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
            // Paths
            'GameData'        => env($cfg->envGameData,     $cfg->defaultGameData),
            'MyMods'          => env($cfg->envMyMods,       $cfg->defaultMyMods),
            'UnpackedMods'    => env($cfg->envUnpackedMods, $cfg->defaultUnpackedMods),

            // Mongo
            'mongo.default.uri'        => env('mongo.default.uri', ''),
            'mongo.default.db'         => env('mongo.default.db', ''),
            'mongo.default.collection' => env('mongo.default.collection', ''),
            'mongo.client.appName'     => env('mongo.client.appName', ''),
            'mongo.client.retryWrites' => env('mongo.client.retryWrites', ''),
            'mongo.client.serverSelectionTimeoutMS' => env('mongo.client.serverSelectionTimeoutMS', ''),
            'mongo.client.connectTimeoutMS'        => env('mongo.client.connectTimeoutMS', ''),
            'mongo.client.socketTimeoutMS'         => env('mongo.client.socketTimeoutMS', ''),
            'mongo.client.w'           => env('mongo.client.w', ''),
            'mongo.client.readPreference' => env('mongo.client.readPreference', ''),
            'mongo.client.username'    => env('mongo.client.username', ''),
            'mongo.client.password'    => env('mongo.client.password', ''),
            'mongo.client.authSource'  => env('mongo.client.authSource', ''),

            // App
            'app.baseURL'   => env('app.baseURL', ''),
            'app.indexPage' => env('app.indexPage', ''),
        ];

        return view('settings/index', [
            'cfg'    => $cfg,
            'values' => $values,
        ]);
    }
public function save(): ResponseInterface
    {
        /** @var BG3Paths $cfg */
        $cfg = config(BG3Paths::class);

        // Helper to fetch dotted or underscored POST keys
        $fetch = function(string $key) {
            $dotted = $this->request->getPost($key);
            if ($dotted !== null) return $dotted;
            $underscored = str_replace('.', '_', $key);
            return $this->request->getPost($underscored);
        };

        $pairs = [];

        // Paths (write only when provided and not empty)
        $v = $this->request->getPost('GameData');     if ($v !== null && $v !== '') { $pairs[$cfg->envGameData] = (string)$v; }
        $v = $this->request->getPost('MyMods');       if ($v !== null && $v !== '') { $pairs[$cfg->envMyMods]   = (string)$v; }
        $v = $this->request->getPost('UnpackedMods'); if ($v !== null && $v !== '') { $pairs[$cfg->envUnpackedMods] = (string)$v; }

        // Mongo generic keys (except retryWrites and w which we normalize below)
        foreach ([
            'mongo.default.uri',
            'mongo.default.db',
            'mongo.default.collection',
            'mongo.client.appName',
            'mongo.client.serverSelectionTimeoutMS',
            'mongo.client.connectTimeoutMS',
            'mongo.client.socketTimeoutMS',
            'mongo.client.readPreference',
            'mongo.client.username',
            'mongo.client.password',
            'mongo.client.authSource',
            'app.baseURL',
            'app.indexPage',
        ] as $k) {
            $val = $fetch($k);
            if ($val !== null && $val !== '') {
                $pairs[$k] = (string)$val;
            }
        }

        // Normalize retryWrites to true/false
        $v = $fetch('mongo.client.retryWrites');
        if ($v !== null && $v !== '') {
            $vl = strtolower((string)$v);
            if (in_array($vl, ['true','1','yes','on']))       { $pairs['mongo.client.retryWrites'] = 'true'; }
            elseif (in_array($vl, ['false','0','no','off']))  { $pairs['mongo.client.retryWrites'] = 'false'; }
        }

        // Normalize write concern 'w'
        $v = $fetch('mongo.client.w');
        if ($v !== null && $v !== '') {
            $vl = strtolower((string)$v);
            if (in_array($vl, ['true','yes','on']))       { $pairs['mongo.client.w'] = '1'; }
            elseif (in_array($vl, ['false','no','off']))  { $pairs['mongo.client.w'] = '0'; }
            else                                          { $pairs['mongo.client.w'] = (string)$v; }
        }

        if (!empty($pairs)) {
            (new EnvWriter())->setMany($pairs);
        }

        return $this->response->setJSON(['ok' => true, 'saved' => $pairs]);
    }
}
?>
