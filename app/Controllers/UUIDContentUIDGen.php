<?php

namespace App\Controllers;

class UUIDContentUIDGen extends BaseController
{
    public function index($type = "UUID"): string
    {
        switch ($type) {
            case 'UUID':
                $data['ID'] = $this->generateUUID();
                break;
            case 'ContentUID':
                $data['ID'] = $this->generateRandomString();
                break;
            default:
                $data['ID'] = 'Unrecognized Option';
        }
        return view('mods/uuidcontentuidshow', $data);
    }

    private function generateUUID(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    private function generateRandomString($length = 37): string
    {
        $characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }
        return $randomString;
    }
}
?>
