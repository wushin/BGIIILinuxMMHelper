<?php
namespace App\Controllers;

class Home extends BaseController
{
    public function index()
    {
        $readmePath = ROOTPATH . 'README.md';
        $md = '';
        try {
            $md = @file_get_contents($readmePath) ?: '';
        } catch (\Throwable $e) {
            $md = '';
        }

        $md = preg_replace("/\\\\\r?\n/", "  \n", $md);

        $html = '';
        if ($md !== '') {
            $html = service('markdown')->convert($md)->getContent();
        }

        return view('home/index', [
            'title'      => 'Home — BG3 Linux Helper',
            'readmeHtml' => $html,
        ]);
    }
}
?>
