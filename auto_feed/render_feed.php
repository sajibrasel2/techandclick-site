<?php

function tc_read_feed_file(string $key): array {
    $baseDir = __DIR__;
    $path = $baseDir . DIRECTORY_SEPARATOR . $key . '.json';
    if (!file_exists($path)) {
        return [];
    }
    $raw = file_get_contents($path);
    if ($raw === false) {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }
    return $decoded;
}

function tc_h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function tc_render_latest_updates(string $key, int $limit = 10): void {
    $items = tc_read_feed_file($key);
    if (!$items) {
        return;
    }

    $items = array_slice($items, 0, max(0, $limit));

    echo '<h2>Latest updates</h2>';
    echo '<ul class="list">';

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $text = isset($item['text']) ? (string)$item['text'] : '';
        $url = isset($item['url']) ? (string)$item['url'] : '';
        $date = isset($item['date']) ? (string)$item['date'] : '';

        $label = $text;
        if ($date) {
            $label = $date . ' — ' . $label;
        }

        if ($url) {
            echo '<li><a href="' . tc_h($url) . '" target="_blank" rel="noopener">' . tc_h($label) . '</a></li>';
        } else {
            echo '<li>' . tc_h($label) . '</li>';
        }
    }

    echo '</ul>';
}
