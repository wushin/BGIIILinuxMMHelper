<?php
namespace Config;

use CodeIgniter\Config\BaseConfig;

class Mongo extends BaseConfig
{
    /** Default connection info */
    public string $defaultUri = 'mongodb://bg3mmh-mongo:27017';
    public string $defaultDb  = 'bg3mmh';

    public function __construct()
    {
        parent::__construct();
        $this->defaultUri = env('mongo.default.uri', $this->defaultUri);
        $this->defaultDb  = env('mongo.default.db',  $this->defaultDb);
    }
}
?>
