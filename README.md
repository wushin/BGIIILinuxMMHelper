# BGIII Mod Manager Linux Helper

A lightweight web service that assists with Baldur's Gate 3 mod development on Linux.\
This tool is a companion to [LSLIB](https://github.com/Norbyte/lslib) and [BG3 Mod Manager (BGIIIMM)](https://github.com/LaughingLeader/BG3ModManager).
Icons from BG3 Modding Community [Items](https://cdn.discordapp.com/attachments/1366564158371266560/1366564396364726272/Icons_Items.zip?ex=6857f005&is=68569e85&hm=82c1536c4e6c6370e9708e4298dcd34d1cc1cde8d50986e8eefc09fc86ea6d7c&) [Skills](https://cdn.discordapp.com/attachments/1366564158371266560/1366564395802693722/Icons_Skills.zip?ex=6857f005&is=68569e85&hm=f2227a4cac93fcf734fe56ac4f3e607a7cfc40633878f2b9d08c1b2e43355297&) [Backgrounds](https://cdn.discordapp.com/attachments/1366557663135010866/1366557888029659176/icon_bg_png.zip?ex=6857e9f5&is=68569875&hm=fa333a16fc63dda74b55eb4c182db0e6b47c63b6c810e732f40757ed9cd50e97&)

## ✨ Features

- 🔧 **UUID & Content UID Generator**
- 🧽 **Browse & View Mod Files**
  - Supports `.xml`, `.lsx`, `.txt`, `.khn`, `.dds`, `.png`
- 🖼️ **Automatic `.dds` → `.png` Conversion**
- 🔍 **Full-Text Search** (MongoDB)
  - Search by filename, content, or UUIDs
  - Live filter by subdirectory and file type
  - Search history dropdown and pagination
- ✏️ **Edit & Save Support** for most files
  - In-browser editor with content highlighting
  - Syncs with local changes automatically on refresh
- ↩️ **Replace Utility** to batch-replace keys/values in files
- 🐳 Fully Dockerized for easy setup and isolation

## 🚀 Getting Started

1. **Install **[**Docker**](https://docs.docker.com/engine/install/)

2. Copy and edit the `docker-compose.yml.example`:

   ```bash
   cp docker-compose.yml.example docker-compose.yml
   ```

   Set your local mod paths in the new file.

3. Build and launch the containers:

   ```bash
   bash set-up.sh
   ```

   Or manually:

   ```bash
   sudo docker-compose up -d --build --remove-orphans
   ```

4. Index your game data for search:

   ```bash
   sudo docker exec -it bg3mmh php ./spark mongoindex:scan --rebuild
   ```

5. Open your browser to:\
   👉 `http://localhost:8080/`

6. To stop and clean up:

   ```bash
   bash destroy.sh
   ```

## 🧪 Notes

- Docker may require `sudo` on Debian-based systems.
- Local edits sync seamlessly with the browser-based editor.
- All search results are stored in MongoDB and updated on reindex.

---

## 📜 License

This project is licensed under the **GNU General Public License v3.0**.\
See the full [LICENSE](./LICENSE.txt) for details.
[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)

