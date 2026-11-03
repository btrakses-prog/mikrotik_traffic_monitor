<?php
header('Content-Type: application/json; charset=utf-8');

$config = require __DIR__ . '/config.php';
require __DIR__ . '/routeros_api.class.php';

function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$API = new RouterosAPI();
$API->port = (int)($config['api_port'] ?? 8728);
$API->ssl = (bool)($config['api_ssl'] ?? false);
$API->timeout = 3;

if (!$API->connect($config['host'], $config['user'], $config['pass'])) {
    respond(['ok'=>false, 'error'=>'Gagal terhubung ke MikroTik API. Periksa IP, username/password, port, dan service API.'], 502);
}

$action = $_GET['action'] ?? 'traffic';

if ($action === 'interfaces') {
    $rows = $API->comm('/interface/print');
    $items = [];
    foreach ($rows as $row) {
        if (!isset($row['name'])) continue;
        $name = $row['name'];
        $items[] = [
            'name' => $name,
            'running' => (($row['running'] ?? '') === 'true'),
            'disabled' => (($row['disabled'] ?? '') === 'true'),
            'type' => $row['type'] ?? '',
        ];
    }
    respond(['ok'=>true, 'interfaces'=>$items, 'monitored'=>$config['interfaces'] ?? []]);
}

if ($action === 'save') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(['ok'=>false,'error'=>'Method POST diperlukan.'],405);
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    if (!is_array($payload) || !isset($payload['interfaces']) || !is_array($payload['interfaces'])) {
        respond(['ok'=>false,'error'=>'Data interface tidak valid.'],400);
    }

    $clean = [];
    foreach ($payload['interfaces'] as $name => $item) {
        $name = trim((string)$name);
        if ($name === '' || !preg_match('/^[^"\r\n]{1,128}$/', $name)) continue;
        $label = trim((string)($item['label'] ?? $name));
        $role = (string)($item['role'] ?? 'other');
        if (!in_array($role, ['source','output','other'], true)) $role = 'other';
        if ($label === '') $label = $name;
        $clean[$name] = ['label'=>$label, 'role'=>$role];
    }

    $newConfig = $config;
    $newConfig['interfaces'] = $clean;
    $php = "<?php\nreturn " . var_export($newConfig, true) . ";\n";
    $file = __DIR__ . '/config.php';
    if (!is_writable($file)) respond(['ok'=>false,'error'=>'config.php tidak dapat ditulis. Beri izin tulis pada file tersebut.'],500);
    if (file_put_contents($file, $php, LOCK_EX) === false) respond(['ok'=>false,'error'=>'Gagal menyimpan config.php.'],500);
    respond(['ok'=>true,'message'=>'Konfigurasi interface berhasil disimpan.']);
}

$monitored = $config['interfaces'] ?? [];

// Ambil status aktual semua interface dari RouterOS.
// Jangan menentukan status running dari /interface/monitor-traffic,
// karena interface yang link-nya DOWN tetap bisa mengembalikan nilai 0.
$interfaceRows = $API->comm('/interface/print');
$interfaceState = [];
foreach ($interfaceRows as $row) {
    if (!isset($row['name'])) continue;
    $interfaceState[$row['name']] = [
        'running' => (($row['running'] ?? '') === 'true'),
        'disabled' => (($row['disabled'] ?? '') === 'true'),
    ];
}

$data = [
    'ok'=>true,
    'timestamp'=>time(),
    'router'=>['host'=>$config['host'], 'identity'=>'-', 'uptime'=>'-', 'version'=>'-', 'board'=>'-', 'architecture'=>'-', 'cpu'=>'-', 'memory_free'=>null, 'memory_total'=>null],
    'interfaces'=>[],
    'summary'=>['source_rx'=>0,'source_tx'=>0,'output_rx'=>0,'output_tx'=>0,'total_rx'=>0,'total_tx'=>0],
];

$res = $API->comm('/system/resource/print');
if (!empty($res[0])) {
    $data['router']['uptime'] = $res[0]['uptime'] ?? '-';
    $data['router']['version'] = $res[0]['version'] ?? '-';
    $data['router']['board'] = $res[0]['board-name'] ?? ($res[0]['platform'] ?? '-');
    $data['router']['architecture'] = $res[0]['architecture-name'] ?? '-';
    $data['router']['cpu'] = isset($res[0]['cpu-load']) ? (int)$res[0]['cpu-load'] : null;
    $data['router']['memory_free'] = isset($res[0]['free-memory']) ? (int)$res[0]['free-memory'] : null;
    $data['router']['memory_total'] = isset($res[0]['total-memory']) ? (int)$res[0]['total-memory'] : null;
}
$idres = $API->comm('/system/identity/print');
if (!empty($idres[0]['name'])) {
    $data['router']['identity'] = $idres[0]['name'];
}

// Beberapa versi RouterOS dapat mengembalikan field resource dengan nama
// yang sedikit berbeda. Sediakan fallback agar informasi router tetap tampil.
if (!empty($res[0])) {
    if ($data['router']['board'] === '-' && !empty($res[0]['board-name'])) {
        $data['router']['board'] = $res[0]['board-name'];
    }
    if ($data['router']['architecture'] === '-' && !empty($res[0]['architecture-name'])) {
        $data['router']['architecture'] = $res[0]['architecture-name'];
    }
}

foreach ($monitored as $name => $meta) {
    $item = [
        'name'=>$name,
        'label'=>$meta['label'] ?? $name,
        'role'=>$meta['role'] ?? 'other',
        'rx'=>0,'tx'=>0,'rx_packets'=>0,'tx_packets'=>0,'running'=>false,'disabled'=>false,'error'=>null
    ];

    // Status interface harus mengikuti flag RouterOS:
    // running=true  -> link/interface aktif
    // running=false -> DOWN/OFFLINE
    // disabled=true -> OFFLINE
    $state = $interfaceState[$name] ?? null;
    if ($state !== null) {
        $item['running'] = $state['running'] && !$state['disabled'];
        $item['disabled'] = $state['disabled'];
    } else {
        $item['error'] = 'Interface tidak ditemukan di RouterOS';
    }

    $r = $API->comm('/interface/monitor-traffic', ['interface'=>$name, 'once'=>'']);
    if (isset($r[0])) {
        $item['rx'] = (int)($r[0]['rx-bits-per-second'] ?? 0);
        $item['tx'] = (int)($r[0]['tx-bits-per-second'] ?? 0);
        $item['rx_packets'] = (int)($r[0]['rx-packets-per-second'] ?? 0);
        $item['tx_packets'] = (int)($r[0]['tx-packets-per-second'] ?? 0);

        // Traffic hanya dihitung jika interface benar-benar running.
        if ($item['running']) {
            if ($item['role'] === 'source') {
            $data['summary']['source_rx'] += $item['rx'];
            $data['summary']['source_tx'] += $item['tx'];
        } elseif ($item['role'] === 'output') {
            $data['summary']['output_rx'] += $item['rx'];
            $data['summary']['output_tx'] += $item['tx'];
        }
            $data['summary']['total_rx'] += $item['rx'];
            $data['summary']['total_tx'] += $item['tx'];
        }
    } else {
        // Jika monitor-traffic gagal, pertahankan status aktual dari /interface/print.
        $item['error'] = 'Interface tidak merespons monitor-traffic';
    }
    $data['interfaces'][] = $item;
}
respond($data);
