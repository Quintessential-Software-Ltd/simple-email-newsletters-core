<?php
/**
 * Minimal but correct gettext extractor for the plugin (token-based).
 * Usage: php makepot.php <plugin-dir> <output.pot>
 */
$root   = rtrim($argv[1], '/');
$out    = $argv[2];
$domain = 'quintessential-newsletters';

// func => [msgid_argpos, plural_argpos|0, context_argpos|0, domain_argpos]  (1-based)
$FUNCS = [
    '__'           => [1,0,0,2],
    '_e'           => [1,0,0,2],
    'esc_html__'   => [1,0,0,2],
    'esc_html_e'   => [1,0,0,2],
    'esc_attr__'   => [1,0,0,2],
    'esc_attr_e'   => [1,0,0,2],
    '_x'           => [1,0,2,3],
    '_ex'          => [1,0,2,3],
    'esc_attr_x'   => [1,0,2,3],
    'esc_html_x'   => [1,0,2,3],
    '_n'           => [1,2,0,4],
    '_nx'          => [1,2,4,5],
    '_n_noop'      => [1,2,0,3],
    '_nx_noop'     => [1,2,3,4],
];

function decode_literal($tok) {
    // $tok includes surrounding quotes.
    $q = $tok[0];
    $inner = substr($tok, 1, -1);
    if ($q === "'") {
        return str_replace(["\\\\", "\\'"], ["\\", "'"], $inner);
    }
    return stripcslashes($inner); // double-quoted
}

function pot_escape($s) {
    $s = str_replace("\\", "\\\\", $s);
    $s = str_replace("\"", "\\\"", $s);
    $s = str_replace("\t", "\\t", $s);
    $s = str_replace("\r", "", $s);
    $s = str_replace("\n", "\\n", $s);
    return $s;
}

$entries = []; // key => ['msgid'=>, 'plural'=>, 'ctx'=>, 'refs'=>[]]

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($rii as $file) {
    if ($file->getExtension() !== 'php') continue;
    $path = $file->getPathname();
    if (strpos($path, '/vendor/') !== false || strpos($path, '/node_modules/') !== false) continue;
    if (strpos($path, '/addons/') !== false || strpos($path, '/license-server/') !== false) continue; // separate products
    $rel = ltrim(str_replace($root, '', $path), '/');
    $code = file_get_contents($path);
    $tokens = token_get_all($code);
    $n = count($tokens);
    for ($i = 0; $i < $n; $i++) {
        $t = $tokens[$i];
        if (!is_array($t) || $t[0] !== T_STRING || !isset($FUNCS[$t[1]])) continue;
        // Ensure it's a function call: previous non-ws token must not be '->' or '::' (method) and next must be '('
        $j = $i + 1;
        while ($j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) $j++;
        if ($j >= $n || $tokens[$j] !== '(') continue;
        // prev meaningful token
        $p = $i - 1;
        while ($p >= 0 && is_array($tokens[$p]) && $tokens[$p][0] === T_WHITESPACE) $p--;
        if ($p >= 0 && is_array($tokens[$p]) && in_array($tokens[$p][0], [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION], true)) continue;

        $name = $t[1];
        $line = $t[2];
        // Parse args starting after '('
        $k = $j + 1;
        $depth = 1;
        $args = [];           // arg index => string value or null
        $argIndex = 1;
        $curTokens = [];      // meaningful tokens of current arg
        for (; $k < $n && $depth > 0; $k++) {
            $tk = $tokens[$k];
            if (is_array($tk)) {
                if ($tk[0] === T_WHITESPACE || $tk[0] === T_COMMENT || $tk[0] === T_DOC_COMMENT) continue;
                $curTokens[] = $tk;
            } else {
                if ($tk === '(' || $tk === '[') { $depth++; $curTokens[] = $tk; }
                elseif ($tk === ')' || $tk === ']') {
                    $depth--;
                    if ($depth === 0) { // end of call
                        // finalize current arg
                        $args[$argIndex] = (count($curTokens) === 1 && is_array($curTokens[0]) && $curTokens[0][0] === T_CONSTANT_ENCAPSED_STRING) ? $curTokens[0][1] : null;
                        break;
                    }
                    $curTokens[] = $tk;
                }
                elseif ($tk === ',' && $depth === 1) {
                    $args[$argIndex] = (count($curTokens) === 1 && is_array($curTokens[0]) && $curTokens[0][0] === T_CONSTANT_ENCAPSED_STRING) ? $curTokens[0][1] : null;
                    $argIndex++;
                    $curTokens = [];
                }
                else { $curTokens[] = $tk; }
            }
        }

        list($mp, $pp, $cp, $dp) = $FUNCS[$name];
        $domTok = $dp && isset($args[$dp]) ? $args[$dp] : null;
        if ($domTok === null) continue;
        if (decode_literal($domTok) !== $GLOBALS['domain']) continue;
        if (!isset($args[$mp]) || $args[$mp] === null) continue;

        $msgid  = decode_literal($args[$mp]);
        $plural = ($pp && isset($args[$pp]) && $args[$pp] !== null) ? decode_literal($args[$pp]) : null;
        $ctx    = ($cp && isset($args[$cp]) && $args[$cp] !== null) ? decode_literal($args[$cp]) : null;

        if ($msgid === '') continue;
        $key = ($ctx ?? '') . "\x04" . $msgid . "\x04" . ($plural ?? '');
        if (!isset($entries[$key])) {
            $entries[$key] = ['msgid'=>$msgid, 'plural'=>$plural, 'ctx'=>$ctx, 'refs'=>[]];
        }
        $entries[$key]['refs'][] = "$rel:$line";
    }
}

// Sort by file ref for stable output.
ksort($entries);

$date = gmdate('Y-m-d H:iO');
$pot  = <<<HEAD
# Copyright (C) Quintessential Software Ltd
# This file is distributed under the GPL-2.0-or-later license.
msgid ""
msgstr ""
"Project-Id-Version: Quintessential Newsletters\\n"
"Report-Msgid-Bugs-To: https://wordpress.org/support/plugin/quintessential-newsletters\\n"
"Last-Translator: \\n"
"Language-Team: \\n"
"MIME-Version: 1.0\\n"
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"
"POT-Creation-Date: $date\\n"
"X-Domain: quintessential-newsletters\\n"
"Plural-Forms: nplurals=2; plural=(n != 1);\\n"

HEAD;

$body = '';
$count = 0;
foreach ($entries as $e) {
    $refs = array_unique($e['refs']);
    $body .= "\n";
    foreach (array_chunk($refs, 6) as $chunk) {
        $body .= '#: ' . implode(' ', $chunk) . "\n";
    }
    if ($e['ctx'] !== null) {
        $body .= 'msgctxt "' . pot_escape($e['ctx']) . "\"\n";
    }
    $body .= 'msgid "' . pot_escape($e['msgid']) . "\"\n";
    if ($e['plural'] !== null) {
        $body .= 'msgid_plural "' . pot_escape($e['plural']) . "\"\n";
        $body .= "msgstr[0] \"\"\n";
        $body .= "msgstr[1] \"\"\n";
    } else {
        $body .= "msgstr \"\"\n";
    }
    $count++;
}

file_put_contents($out, $pot . $body);
fwrite(STDERR, "Wrote $count strings to $out\n");
