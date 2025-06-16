<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index(): string
    {
        $data['title'] = "BG3 Mod Manager Linux Helper";
        $data['readme'] = file_get_contents(FCPATH."/README.md");
        return view('templates/header', $data)
            . view('readme')
            . view('templates/footer');
    }
}
