<?php
// Render without XmlEngine as we need the namespace in urlset
// Also use echo because <? short tags will explode if enabled

echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

if ($sitemap) {
    foreach ($sitemap as $item) {
        echo '<url>';

        foreach ($item as $key => $value) {
            echo sprintf('<%s>%s</%s>', $key, $value, $key);
        }

        echo '</url>';
    }
}

echo '</urlset>';