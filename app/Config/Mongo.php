<?php
/**
 * This file is part of BGIII Mod Manager Linux Helper.
 *
 * Copyright (C) 2025 Wushin.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Mongo connection config for CI4.
 * - $uri: required connection string (can come from .env)
 * - $db:  default database name
 * - $clientOptions: maps 1:1 to MongoDB URI options (2nd arg to MongoDB\Client)
 * - $driverOptions: low-level driver options (3rd arg to MongoDB\Client) — only used if your Service passes it
 */
class Mongo extends BaseConfig
{
    /** Connection string (can be a SRV record, add credentials if needed) */
    public string $uri;

    /** Default database name */
    public string $db;
    public string $collection;

    public string $ensureIndexes;
    /**
     * Extra options passed as the SECOND argument to MongoDB\Client.
     * These correspond to URI options; putting them here lets you avoid appending them to the URI.
     */
    public array $clientOptions = [];

    /**
     * Extra driver options passed as the THIRD argument to MongoDB\Client.
     * Only used if your Services::mongo() method forwards it.
     */
    public array $driverOptions = [];

    public function __construct()
    {
        parent::__construct();

        $this->uri = (string) env('mongo.default.uri', 'mongodb://bg3mmh-mongo:27017');
        $this->db  = (string) env('mongo.default.db',  'bg3mmh');
        $this->collection  = (string) env('mongo.default.collection',  'files');
        $this->ensureIndexes = (string) env('mongo.default.ensureIndexes', 'false');

        $this->clientOptions = [
            'appName'                   => (string) env('mongo.client.appName', 'bg3mmh'),
            'retryWrites'               => filter_var(env('mongo.client.retryWrites', 'true'), FILTER_VALIDATE_BOOL),
            'serverSelectionTimeoutMS'  => (int) env('mongo.client.serverSelectionTimeoutMS', 5000),
            'connectTimeoutMS'          => (int) env('mongo.client.connectTimeoutMS', 5000),
            'socketTimeoutMS'           => (int) env('mongo.client.socketTimeoutMS', 600000), // 10 min
            'w'                         => (int) env('mongo.client.w', 1),
            'readPreference'          => (string) env('mongo.client.readPreference', 'primary'),
            'readConcernLevel'        => (string) env('mongo.client.readConcernLevel', 'local'),
            // 'username'                => env('mongo.client.username'),
            // 'password'                => env('mongo.client.password'),
            // 'authSource'              => env('mongo.client.authSource', 'admin'),
        ];

        $this->driverOptions = [
            // Make Mongo return plain arrays (nice for JSON responses/logging)
            'typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array'],
        ];
    }
}

?>
