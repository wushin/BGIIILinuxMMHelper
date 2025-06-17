<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index(): string
    {
        $data['title'] = "BG3 Mod Manager Linux Helper";
        $readmePath = FCPATH . "/README.md";
        $data['readme'] = file_exists($readmePath) ? file_get_contents($readmePath) : "README not found.";
        return view('templates/header', $data)
            . view('readme')
            . view('templates/footer');
    }
}
