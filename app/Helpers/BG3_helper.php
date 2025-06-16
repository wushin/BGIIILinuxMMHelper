<?php

use CodeIgniter\CodeIgniter;

function getUrlPath($path,$slug): string
{
    return $path."/".$slug;
}

function getAbsPath($path,$slug): string
{
    return FCPATH.$path."/".$slug;
}

function getFileUrlPath($path,$key): string
{
    return "/display/".$path."/".preg_replace('/'.str_replace("/", "\/", FCPATH).'(.*?)\//','',$key);
}

function getFileSavePath($path,$key): string
{
    return FCPATH.$path."/".preg_replace('/'.str_replace("/", "\/", FCPATH).'(.*?)\//','',$key);
}

function getFileName($path,$key): string
{
    return preg_replace('/'.str_replace("/", "\/", FCPATH).$path.'\/(.*?)\//','',$key);
}

function getAbsFileName($path,$slug,$key): string
{
    return FCPATH.$path."/".addslashes(urldecode($slug))."/".$key;
}

function formatForm($path,$slug,$key): string
{
    $html = "<div id='fileName'>".getFileName($path,$key)." <button id=\"saveFile\" onclick=\"submitMainForm()\">Save</button>";
    if (substr($key, -3) == "xml") {
      $html .= "<button id=\"saveFile\" onclick=\"addRowToForm('mainForm')\">AddNewRow</button>";
    }
    $html .= "</div><form id=\"mainForm\"><input type=\"hidden\" name=\"fileName\" value=\"".getFileSavePath($path,$key)."\"/> <input type=\"hidden\" name=\"path\" value=\"".$path."\"/> <input type=\"hidden\" name=\"slug\" value=\"".$slug."\"/>";
    return $html;
}

function strip_quotes($str) {
  return trim($str, "\"'"); 
}

function formatData($data, $term) {
  if(!is_null($term)) {
    $escapedTerm = htmlspecialchars($term, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5);
    return preg_replace('/' . preg_quote($escapedTerm, '/') . '/i','<span class="highlight">$0</span>', htmlspecialchars($data, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5));
  } else {
    return htmlspecialchars($data, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5);
  }
}
