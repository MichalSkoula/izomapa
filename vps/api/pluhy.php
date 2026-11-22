<?php
// Sypače a pluhy ŘSD pro Apache + PHP (náhrada za functions/api/pluhy.js z Cloudflare).
// Prohlížeč se ptá na api/pluhy?lamin=…&lamax=…&lomin=…&lomax=… (.htaccess přesměruje sem).
// API ŘSD (https://udrzba.rsd.cz) je bez klíče, ale bez CORS, proto se ptá server. Cache v souborech
// pro všechny návštěvníky: polohy 5 s, trasy 60 s. Odpověď stejná jako u Cloudflare verze.
const API = 'https://udrzba.rsd.cz/data/api/v1.0/GPSMapsAPI/';
const AGE = ['toThirty' => 0, 'fromThirtyToSixty' => 1, 'fromSixtyToThreeHours' => 2, 'fromThreeHoursToSixHours' => 3, 'fromSixHoursToTwelveHours' => 4];

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=5');

function cached($path, $ttl) {
  $f = sys_get_temp_dir() . '/izomapa_pluhy_' . md5($path) . '.json';
  if (is_file($f) && time() - filemtime($f) < $ttl) return json_decode(file_get_contents($f), true);
  $ctx = stream_context_create(['http' => ['timeout' => 6, 'header' => "User-Agent: izomapa (mapa sypacu)\r\n"]]);
  $txt = @file_get_contents(API . $path, false, $ctx);
  if ($txt === false) return is_file($f) ? json_decode(file_get_contents($f), true) : null;   // výpadek: poslední známá data
  $d = json_decode($txt, true);
  if ($d === null) return null;
  @file_put_contents($f . '.tmp', $txt); @rename($f . '.tmp', $f);
  return $d;
}

$q = [];
foreach (['lamin', 'lamax', 'lomin', 'lomax'] as $k) $q[$k] = isset($_GET[$k]) && is_numeric($_GET[$k]) ? (float) $_GET[$k] : NAN;
[$la0, $la1, $lo0, $lo1] = [$q['lamin'], $q['lamax'], $q['lomin'], $q['lomax']];
if (is_nan($la0) || is_nan($la1) || is_nan($lo0) || is_nan($lo1) || $la1 - $la0 > 2 || $lo1 - $lo0 > 3) {
  http_response_code(400); echo '{"pts":[],"trasy":[]}'; exit;
}
$in = function ($c) use ($la0, $la1, $lo0, $lo1) { return $c[1] >= $la0 && $c[1] <= $la1 && $c[0] >= $lo0 && $c[0] <= $lo1; };

$P = cached('GetGEOJSONWholeLivePoints', 5);
$L = cached('GetGEOJSONWholeLive/2/0/0/0/0', 60);
$O = cached('GetGEOJSONWholeOld/2/0/0/0/0', 60);

$pts = [];
foreach (($P['features'] ?? []) as $f) {
  if (empty($f['geometry']) || !$in($f['geometry']['coordinates'])) continue;
  $p = $f['properties'];
  $pts[] = ['id' => $p['name'] ?? null, 'lon' => $f['geometry']['coordinates'][0], 'lat' => $f['geometry']['coordinates'][1],
            't' => $p['time'] ?? null, 'v' => $p['speed'] ?? null, 'z' => $p['control'] ?? null, 's' => $p['roadType'] ?? null];
}
$trasy = [];
foreach (array_merge($L['features'] ?? [], $O['features'] ?? []) as $f) {
  $nm = $f['properties']['name'] ?? null;
  if (!isset(AGE[$nm]) || empty($f['geometry'])) continue;
  $a = AGE[$nm]; $z = $f['properties']['control'] ?? null; $s = $f['properties']['roadType'] ?? null;
  $lines = $f['geometry']['type'] === 'LineString' ? [$f['geometry']['coordinates']] : $f['geometry']['coordinates'];
  foreach ($lines as $l) {
    $run = []; $n = count($l);
    for ($i = 0; $i < $n; $i++) {                 // jen části uvnitř výřezu (s jedním bodem přesahu kvůli okraji)
      if ($in($l[$i]) || ($i + 1 < $n && $in($l[$i + 1])) || ($i > 0 && $in($l[$i - 1]))) $run[] = [round($l[$i][0], 6), round($l[$i][1], 6)];
      elseif ($run) { if (count($run) > 1) $trasy[] = ['a' => $a, 'z' => $z, 's' => $s, 'l' => $run]; $run = []; }
    }
    if (count($run) > 1) $trasy[] = ['a' => $a, 'z' => $z, 's' => $s, 'l' => $run];
  }
}
echo json_encode(['pts' => $pts, 'trasy' => $trasy], JSON_UNESCAPED_UNICODE);
