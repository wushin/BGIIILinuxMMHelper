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
