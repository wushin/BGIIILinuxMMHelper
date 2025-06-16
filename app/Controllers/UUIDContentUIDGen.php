<?php

namespace App\Controllers;

use Ramsey\Uuid\Uuid;

class UUIDContentUIDGen extends BaseController
{
    public function index($type = "UUID")
    {
        if ($type === "UUID") {
          $data['ID'] = $this->generateUUID();
        } elseif ($type === "ContentUID") {
          $data['ID'] = $this->generateRandomString();
        } else {
          $data['ID'] = "Unrecognized Option";
        }
        return view('mods/uuidcontentuidshow', $data);
    }

    public function generateUUID() {
          return Uuid::uuid4()->toString();
    }

    public function generateRandomString($length = 37) {
        $characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }
        return $randomString;
    }
}
