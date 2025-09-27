<?php
namespace App\Controllers;

class Home extends BaseController
{
    public function index()
    {
        $readmePath = ROOTPATH . 'README.md';
        $md = is_file($readmePath) ? file_get_contents($readmePath) : '';

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
